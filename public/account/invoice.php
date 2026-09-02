<?php

declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;

$auth = new Auth($pdo);
$auth->requireGuest();
$stmt = $pdo->prepare(
    'SELECT bookings.*, room_types.name AS room_type_name, clients.first_name, clients.last_name, clients.email'
    . ' FROM bookings INNER JOIN room_types ON room_types.id = bookings.room_type_id'
    . ' INNER JOIN clients ON clients.id = bookings.client_id'
    . ' WHERE bookings.id = :id AND bookings.client_id = :client_id'
);
$stmt->execute(['id' => (int) ($_GET['id'] ?? 0), 'client_id' => $auth->clientId()]);
$booking = $stmt->fetch();
if (!$booking) {
    http_response_code(404);
    exit('Счёт не найден.');
}
$serviceStmt = $pdo->prepare(
    'SELECT services.name, booking_services.quantity, booking_services.unit_price, booking_services.total'
    . ' FROM booking_services INNER JOIN services ON services.id = booking_services.service_id'
    . " WHERE booking_services.booking_id = :id AND booking_services.status != 'cancelled'"
);
$serviceStmt->execute(['id' => $booking['id']]);
$services = $serviceStmt->fetchAll();
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Счёт <?= e((string) $booking['code']) ?></title><link rel="stylesheet" href="/css/product.css"></head><body class="invoice-body"><main class="invoice"><header><div><span class="brand-mark">H</span><strong>HOTEL</strong></div><button onclick="window.print()">Печать / PDF</button></header><section class="invoice-title"><div><small>СЧЁТ-ПОДТВЕРЖДЕНИЕ</small><h1><?= e((string) $booking['code']) ?></h1></div><div><strong><?= e((string) $booking['last_name'] . ' ' . $booking['first_name']) ?></strong><span><?= e((string) $booking['email']) ?></span></div></section><table><thead><tr><th>Позиция</th><th>Количество</th><th>Цена</th><th>Сумма</th></tr></thead><tbody><tr><td><?= e((string) $booking['room_type_name']) ?><small><?= e((string) $booking['date_in']) ?> — <?= e((string) $booking['date_out']) ?></small></td><td><?= e((string) max(1, (new DateTimeImmutable((string) $booking['date_in']))->diff(new DateTimeImmutable((string) $booking['date_out']))->days)) ?> ноч.</td><td></td><td><?= e(money($booking['accommodation_total'])) ?></td></tr><?php foreach ($services as $service): ?><tr><td><?= e((string) $service['name']) ?></td><td><?= e((string) $service['quantity']) ?></td><td><?= e(money($service['unit_price'])) ?></td><td><?= e(money($service['total'])) ?></td></tr><?php endforeach; ?></tbody></table><footer><div><span>Статус</span><strong><?= e(booking_status_label((string) $booking['status'])) ?></strong></div><div class="invoice-total"><span>Итого</span><strong><?= e(money($booking['total'])) ?></strong></div></footer><p class="invoice-note">Документ сформирован демонстрационной системой Hotel. Для учебного проекта не является фискальным чеком.</p></main></body></html>
