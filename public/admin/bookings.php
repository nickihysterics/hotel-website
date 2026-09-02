<?php

declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;

$auth = new Auth($pdo);
$auth->requirePermission('bookings.manage');
$title = 'Бронирования';
$status = (string) ($_GET['status'] ?? '');
$query = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT bookings.*, CONCAT(clients.last_name, " ", clients.first_name) AS client_name,'
    . ' clients.phone, room_types.name AS room_type_name, rooms.number AS room_number,'
    . ' COALESCE(paid.amount, 0) AS paid_amount'
    . ' FROM bookings INNER JOIN clients ON clients.id = bookings.client_id'
    . ' INNER JOIN room_types ON room_types.id = bookings.room_type_id'
    . ' LEFT JOIN rooms ON rooms.id = bookings.room_id'
    . " LEFT JOIN (SELECT booking_id, SUM(amount) amount FROM payments WHERE status = 'paid' GROUP BY booking_id) paid ON paid.booking_id = bookings.id"
    . ' WHERE 1=1';
$params = [];
if ($status !== '') {
    $sql .= ' AND bookings.status = :status';
    $params['status'] = $status;
}
if ($query !== '') {
    $sql .= ' AND (bookings.code LIKE :query OR clients.last_name LIKE :query OR clients.phone LIKE :query)';
    $params['query'] = '%' . $query . '%';
}
$sql .= ' ORDER BY FIELD(bookings.status, "checked_in", "confirmed", "pending", "checked_out", "no_show", "cancelled"), bookings.date_in DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();
$statuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show'];

require __DIR__ . '/../../templates/admin/header.php';
?>
<section class="admin-card">
  <div class="filter-row"><form class="filter-row" method="get"><input name="q" value="<?= e($query) ?>" placeholder="Код, фамилия или телефон"><select name="status"><option value="">Все статусы</option><?php foreach ($statuses as $item): ?><option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= e(booking_status_label($item)) ?></option><?php endforeach; ?></select><button class="button" type="submit">Найти</button></form><a class="button button-link" href="/admin/booking-edit.php">+ Новая бронь</a></div>
  <div class="table-scroll"><table class="admin-table"><thead><tr><th>Бронь</th><th>Гость</th><th>Проживание</th><th>Номер</th><th>Статус</th><th>Сумма</th><th></th></tr></thead><tbody>
  <?php foreach ($bookings as $booking): ?><tr><td><strong><?= e((string) $booking['code']) ?></strong><br><small><?= e((string) $booking['source']) ?></small></td><td><?= e((string) $booking['client_name']) ?><br><small><?= e((string) $booking['phone']) ?></small></td><td><?= e((new DateTimeImmutable((string) $booking['date_in']))->format('d.m.Y')) ?> → <?= e((new DateTimeImmutable((string) $booking['date_out']))->format('d.m.Y')) ?><br><small><?= e((string) $booking['adults']) ?> взр. · <?= e((string) $booking['children']) ?> дет.</small></td><td><?= e((string) $booking['room_type_name']) ?><br><small><?= $booking['room_number'] ? '№ ' . e((string) $booking['room_number']) : 'Не назначен' ?></small></td><td><span class="status status-<?= e((string) $booking['status']) ?>"><?= e(booking_status_label((string) $booking['status'])) ?></span></td><td><strong><?= e(money($booking['total'])) ?></strong><br><small>оплачено <?= e(money($booking['paid_amount'])) ?></small></td><td><a class="button button-link" href="/admin/booking-edit.php?id=<?= e((string) $booking['id']) ?>">Открыть</a></td></tr><?php endforeach; ?>
  <?php if ($bookings === []): ?><tr><td colspan="7">Ничего не найдено.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<?php require __DIR__ . '/../../templates/admin/footer.php'; ?>
