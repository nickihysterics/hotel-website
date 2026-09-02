<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

/**
 * Выполняет транзакционные операции бронирования и централизованно считает суммы.
 */
final class BookingService
{
    public function __construct(
        private PDO $pdo,
        private AvailabilityService $availability
    ) {
    }

    /**
     * Создаёт бронь гостя после серверной проверки дат, вместимости и доступности.
     *
     * @param array{room_type_id: int, date_in: string, date_out: string, adults: int, children: int, guest_comment?: string} $data
     * @param array<int, int> $serviceQuantities Соответствие ID услуги и количества.
     */
    public function createForClient(int $clientId, array $data, array $serviceQuantities = []): int
    {
        $roomTypeId = (int) $data['room_type_id'];
        $dateIn = $data['date_in'];
        $dateOut = $data['date_out'];
        $adults = max(1, (int) $data['adults']);
        $children = max(0, (int) $data['children']);
        $start = new DateTimeImmutable($dateIn);
        $end = new DateTimeImmutable($dateOut);
        $nights = (int) $start->diff($end)->days;

        if ($end <= $start || $nights < 1) {
            throw new DomainException('Дата выезда должна быть позже даты заезда.');
        }

        if ($start < new DateTimeImmutable('today')) {
            throw new DomainException('Нельзя оформить бронирование на прошедшую дату.');
        }

        // Цена, свободный фонд, бронь и услуги фиксируются одной транзакцией.
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, name, base_price, capacity_adults, capacity_children'
                . ' FROM room_types WHERE id = :id AND active = 1 FOR UPDATE'
            );
            $stmt->execute(['id' => $roomTypeId]);
            $roomType = $stmt->fetch();
            if (!$roomType) {
                throw new DomainException('Выбранный тип номера не найден.');
            }

            if ($adults > (int) $roomType['capacity_adults'] || $children > (int) $roomType['capacity_children']) {
                throw new DomainException('Выбранный номер не подходит для указанного количества гостей.');
            }

            // Блокируем фонд типа до повторной проверки, чтобы не продать последний номер дважды.
            $this->pdo->prepare('SELECT id FROM rooms WHERE room_type_id = :id FOR UPDATE')
                ->execute(['id' => $roomTypeId]);
            if (!$this->availability->isAvailable($roomTypeId, $dateIn, $dateOut)) {
                throw new DomainException('На выбранные даты номер уже недоступен.');
            }

            $accommodationTotal = round((float) $roomType['base_price'] * $nights, 2);
            $code = $this->generateCode();
            $insert = $this->pdo->prepare(
                'INSERT INTO bookings ('
                . ' code, client_id, room_type_id, date_in, date_out, status, adults, children, source,'
                . ' guest_comment, accommodation_total, services_total, discount_total, total'
                . ' ) VALUES ('
                . ' :code, :client_id, :room_type_id, :date_in, :date_out, :status, :adults, :children, :source,'
                . ' :guest_comment, :accommodation_total, 0, 0, :total'
                . ' )'
            );
            $insert->execute([
                'code' => $code,
                'client_id' => $clientId,
                'room_type_id' => $roomTypeId,
                'date_in' => $dateIn,
                'date_out' => $dateOut,
                'status' => 'pending',
                'adults' => $adults,
                'children' => $children,
                'source' => 'website',
                'guest_comment' => trim((string) ($data['guest_comment'] ?? '')) ?: null,
                'accommodation_total' => $accommodationTotal,
                'total' => $accommodationTotal,
            ]);
            $bookingId = (int) $this->pdo->lastInsertId();

            // Цена услуги копируется в заказ и не меняется задним числом при обновлении каталога.
            foreach ($serviceQuantities as $serviceId => $quantity) {
                if ($quantity > 0) {
                    $this->addService($bookingId, (int) $serviceId, $quantity, $nights);
                }
            }
            $this->recalculateTotals($bookingId);
            audit_log($this->pdo, 'booking.created', 'booking', (string) $bookingId, ['code' => $code]);
            $this->pdo->commit();
            return $bookingId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** Добавляет услугу и применяет её способ тарификации к текущей брони. */
    public function addService(int $bookingId, int $serviceId, int $quantity, ?int $nights = null): int
    {
        $stmt = $this->pdo->prepare('SELECT id, price, pricing_type FROM services WHERE id = :id AND active = 1');
        $stmt->execute(['id' => $serviceId]);
        $service = $stmt->fetch();
        if (!$service) {
            throw new DomainException('Услуга недоступна.');
        }

        $bookingStmt = $this->pdo->prepare('SELECT DATEDIFF(date_out, date_in) AS nights, adults + children AS guests FROM bookings WHERE id = :id');
        $bookingStmt->execute(['id' => $bookingId]);
        $booking = $bookingStmt->fetch();
        if (!$booking) {
            throw new DomainException('Бронирование не найдено.');
        }
        $nights = $nights ?? max(1, (int) $booking['nights']);
        // per_day умножается на ночи, per_person — на всех гостей, остальные типы — на количество.
        $multiplier = match ($service['pricing_type']) {
            'per_day' => $nights,
            'per_person' => max(1, (int) $booking['guests']),
            default => 1,
        };
        $total = round((float) $service['price'] * max(1, $quantity) * $multiplier, 2);
        $insert = $this->pdo->prepare(
            'INSERT INTO booking_services (booking_id, service_id, quantity, unit_price, total, status)'
            . ' VALUES (:booking_id, :service_id, :quantity, :unit_price, :total, :status)'
        );
        $insert->execute([
            'booking_id' => $bookingId,
            'service_id' => $serviceId,
            'quantity' => max(1, $quantity),
            'unit_price' => $service['price'],
            'total' => $total,
            'status' => 'new',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Пересчитывает услуги и общий итог, исключая отменённые позиции. */
    public function recalculateTotals(int $bookingId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END), 0)"
            . ' FROM booking_services WHERE booking_id = :booking_id'
        );
        $stmt->execute(['booking_id' => $bookingId]);
        $servicesTotal = (float) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'UPDATE bookings SET services_total = :services_total,'
            . ' total = GREATEST(0, accommodation_total + :services_total_again - discount_total)'
            . ' WHERE id = :id'
        );
        $stmt->execute([
            'services_total' => $servicesTotal,
            'services_total_again' => $servicesTotal,
            'id' => $bookingId,
        ]);
    }

    /** Отменяет только собственную будущую бронь гостя и оформляет демонстрационный возврат. */
    public function cancelByClient(int $bookingId, int $clientId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM bookings WHERE id = :id AND client_id = :client_id'
                . " AND status IN ('pending', 'confirmed') AND date_in > CURDATE() FOR UPDATE"
            );
            $stmt->execute(['id' => $bookingId, 'client_id' => $clientId]);
            if (!$stmt->fetchColumn()) {
                $this->pdo->rollBack();
                return false;
            }
            $this->pdo->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = :id")
                ->execute(['id' => $bookingId]);
            $this->pdo->prepare(
                "UPDATE payments SET status = 'refunded' WHERE booking_id = :id AND status = 'paid'"
            )->execute(['id' => $bookingId]);
            audit_log($this->pdo, 'booking.cancelled_by_guest', 'booking', (string) $bookingId);
            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function generateCode(): string
    {
        return 'HTL-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
