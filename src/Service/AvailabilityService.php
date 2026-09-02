<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/** Рассчитывает продаваемый остаток типов номеров и свободные физические комнаты. */
final class AvailabilityService
{
    /** Эти статусы занимают фонд и участвуют в проверке пересечений. */
    private const ACTIVE_BOOKING_STATUSES = ['pending', 'confirmed', 'checked_in'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Возвращает подходящие типы номеров вместе с остатком на выбранный интервал.
     *
     * @param array{adults?: int, children?: int, category_id?: int, max_price?: float} $filters
     * @return list<array<string, mixed>>
     */
    public function search(string $dateIn, string $dateOut, array $filters = []): array
    {
        // Остаток = активный физический фонд без ремонта − пересекающиеся активные брони.
        $sql = 'SELECT room_types.*, categories.name AS category_name, room_views.name AS view_name,'
            . ' bathroom_types.name AS bathroom_name, inventory.total_rooms,'
            . ' inventory.total_rooms - COALESCE(booking_usage.booked_rooms, 0) AS available_rooms'
            . ' FROM room_types'
            . ' INNER JOIN categories ON categories.id = room_types.category_id'
            . ' INNER JOIN room_views ON room_views.id = room_types.view_id'
            . ' INNER JOIN bathroom_types ON bathroom_types.id = room_types.bathroom_type_id'
            . ' INNER JOIN ('
            . '   SELECT room_type_id, COUNT(*) AS total_rooms FROM rooms'
            . "   WHERE active = 1 AND status != 'maintenance' GROUP BY room_type_id"
            . ' ) inventory ON inventory.room_type_id = room_types.id'
            . ' LEFT JOIN ('
            . '   SELECT room_type_id, COUNT(*) AS booked_rooms FROM bookings'
            . "   WHERE status IN ('pending', 'confirmed', 'checked_in')"
            . '   AND NOT (date_out <= :date_in OR date_in >= :date_out)'
            . '   GROUP BY room_type_id'
            . ' ) booking_usage ON booking_usage.room_type_id = room_types.id'
            . ' WHERE room_types.active = 1'
            . ' AND room_types.capacity_adults >= :adults'
            . ' AND room_types.capacity_children >= :children';

        $params = [
            'date_in' => $dateIn,
            'date_out' => $dateOut,
            'adults' => max(1, (int) ($filters['adults'] ?? 1)),
            'children' => max(0, (int) ($filters['children'] ?? 0)),
        ];

        if (($filters['category_id'] ?? 0) > 0) {
            $sql .= ' AND room_types.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (($filters['max_price'] ?? 0) > 0) {
            $sql .= ' AND room_types.base_price <= :max_price';
            $params['max_price'] = (float) $filters['max_price'];
        }

        $sql .= ' HAVING available_rooms > 0 ORDER BY room_types.featured DESC, room_types.base_price';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Проверяет наличие единицы фонда, при редактировании исключая текущую бронь. */
    public function isAvailable(
        int $roomTypeId,
        string $dateIn,
        string $dateOut,
        ?int $excludeBookingId = null
    ): bool {
        $inventoryStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rooms WHERE room_type_id = :room_type_id AND active = 1 AND status != 'maintenance'"
        );
        $inventoryStmt->execute(['room_type_id' => $roomTypeId]);
        $inventory = (int) $inventoryStmt->fetchColumn();
        if ($inventory === 0) {
            return false;
        }

        $statusPlaceholders = implode(', ', array_fill(0, count(self::ACTIVE_BOOKING_STATUSES), '?'));
        // Интервалы полуоткрытые: выезд в день следующего заезда не создаёт конфликт.
        $sql = 'SELECT COUNT(*) FROM bookings WHERE room_type_id = ?'
            . " AND status IN ({$statusPlaceholders})"
            . ' AND NOT (date_out <= ? OR date_in >= ?)';
        $params = array_merge([$roomTypeId], self::ACTIVE_BOOKING_STATUSES, [$dateIn, $dateOut]);
        if ($excludeBookingId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeBookingId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() < $inventory;
    }

    /**
     * Возвращает конкретные комнаты, которые можно назначить при заселении.
     *
     * @return list<array<string, mixed>>
     */
    public function availablePhysicalRooms(int $roomTypeId, string $dateIn, string $dateOut): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rooms.id, rooms.number, rooms.status, floors.name AS floor_name'
            . ' FROM rooms INNER JOIN floors ON floors.id = rooms.floor_id'
            . ' WHERE rooms.room_type_id = :room_type_id AND rooms.active = 1'
            . " AND rooms.status != 'maintenance'"
            . ' AND NOT EXISTS ('
            . '   SELECT 1 FROM bookings'
            . '   WHERE bookings.room_id = rooms.id'
            . "   AND bookings.status IN ('pending', 'confirmed', 'checked_in')"
            . '   AND NOT (bookings.date_out <= :date_in OR bookings.date_in >= :date_out)'
            . ' ) ORDER BY floors.level, rooms.number'
        );
        $stmt->execute(['room_type_id' => $roomTypeId, 'date_in' => $dateIn, 'date_out' => $dateOut]);
        return $stmt->fetchAll();
    }
}
