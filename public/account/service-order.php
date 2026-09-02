<?php

declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;
use App\Service\AvailabilityService;
use App\Service\BookingService;

$auth = new Auth($pdo);
$auth->requireGuest();
$bookingId = (int) ($_POST['booking_id'] ?? 0);
if (!is_post() || !verify_csrf($_POST['_token'] ?? null)) {
    flash('error', 'Не удалось проверить запрос.');
    redirect('/account/');
}

$stmt = $pdo->prepare(
    'SELECT id FROM bookings WHERE id = :id AND client_id = :client_id'
    . " AND status IN ('pending', 'confirmed', 'checked_in')"
);
$stmt->execute(['id' => $bookingId, 'client_id' => $auth->clientId()]);
if (!$stmt->fetchColumn()) {
    flash('error', 'Для этого бронирования нельзя заказать услугу.');
    redirect('/account/');
}

$pdo->beginTransaction();
try {
    $bookingService = new BookingService($pdo, new AvailabilityService($pdo));
    $orderId = $bookingService->addService(
        $bookingId,
        (int) ($_POST['service_id'] ?? 0),
        min(20, max(1, (int) ($_POST['quantity'] ?? 1)))
    );
    $update = $pdo->prepare(
        'UPDATE booking_services SET scheduled_for = :scheduled_for, guest_note = :guest_note WHERE id = :id'
    );
    $update->execute([
        'scheduled_for' => trim((string) ($_POST['scheduled_for'] ?? '')) ?: null,
        'guest_note' => substr(trim((string) ($_POST['guest_note'] ?? '')), 0, 500) ?: null,
        'id' => $orderId,
    ]);
    $bookingService->recalculateTotals($bookingId);
    audit_log($pdo, 'service.ordered', 'booking_service', (string) $orderId, ['booking_id' => $bookingId]);
    $pdo->commit();
    flash('success', 'Услуга добавлена. Итоговый счёт обновлён.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $exception instanceof DomainException ? $exception->getMessage() : 'Не удалось добавить услугу.');
}
redirect('/account/booking.php?id=' . $bookingId);
