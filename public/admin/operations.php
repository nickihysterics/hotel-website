<?php

declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;
use App\Service\AvailabilityService;

$auth = new Auth($pdo);
$auth->requirePermission('operations.manage');

if (is_post()) {
    if (!verify_csrf($_POST['_token'] ?? null)) {
        flash('error', 'Сессия формы истекла.');
        redirect('/admin/operations.php');
    }
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id');
    $stmt->execute(['id' => $bookingId]);
    $booking = $stmt->fetch();
    if (!$booking) {
        flash('error', 'Бронирование не найдено.');
        redirect('/admin/operations.php');
    }
    try {
        // Статус брони, физической комнаты и задача уборки меняются атомарно.
        $pdo->beginTransaction();
        if ($action === 'confirm' && $booking['status'] === 'pending') {
            $pdo->prepare("UPDATE bookings SET status='confirmed', associate_id=:associate_id WHERE id=:id")
                ->execute(['associate_id' => $auth->user()['associate_id'] ?: null, 'id' => $bookingId]);
        } elseif ($action === 'check_in' && in_array($booking['status'], ['pending', 'confirmed'], true)) {
            $roomId = (int) ($_POST['room_id'] ?? 0);
            $available = (new AvailabilityService($pdo))->availablePhysicalRooms((int) $booking['room_type_id'], (string) $booking['date_in'], (string) $booking['date_out']);
            if (!in_array($roomId, array_map('intval', array_column($available, 'id')), true)) {
                throw new DomainException('Выберите свободный номер нужного типа.');
            }
            $pdo->prepare("UPDATE bookings SET status='checked_in', room_id=:room_id, associate_id=:associate_id, checked_in_at=NOW() WHERE id=:id")
                ->execute(['room_id' => $roomId, 'associate_id' => $auth->user()['associate_id'] ?: null, 'id' => $bookingId]);
            $pdo->prepare("UPDATE rooms SET status='occupied' WHERE id=:id")->execute(['id' => $roomId]);
        } elseif ($action === 'check_out' && $booking['status'] === 'checked_in' && $booking['room_id']) {
            // Выезд сразу освобождает бронь, но номер вернётся в продажу только после уборки.
            $pdo->prepare("UPDATE bookings SET status='checked_out', checked_out_at=NOW() WHERE id=:id")->execute(['id' => $bookingId]);
            $pdo->prepare("UPDATE rooms SET status='dirty' WHERE id=:id")->execute(['id' => $booking['room_id']]);
            $pdo->prepare("INSERT INTO housekeeping_tasks (room_id, booking_id, task_type, status, priority, due_at, notes) VALUES (:room_id,:booking_id,'cleaning','pending','high',NOW(),'Уборка после выезда')")
                ->execute(['room_id' => $booking['room_id'], 'booking_id' => $bookingId]);
        } elseif (in_array($action, ['cancel', 'no_show'], true) && in_array($booking['status'], ['pending', 'confirmed'], true)) {
            $newStatus = $action === 'cancel' ? 'cancelled' : 'no_show';
            $pdo->prepare("UPDATE bookings SET status=:status, cancelled_at=IF(:status_again='cancelled',NOW(),cancelled_at) WHERE id=:id")
                ->execute(['status' => $newStatus, 'status_again' => $newStatus, 'id' => $bookingId]);
        } else {
            throw new DomainException('Переход между статусами недоступен.');
        }
        audit_log($pdo, 'booking.' . $action, 'booking', (string) $bookingId);
        $pdo->commit();
        flash('success', 'Операция выполнена.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception instanceof DomainException ? $exception->getMessage() : 'Не удалось выполнить операцию.');
    }
    redirect('/admin/operations.php');
}

$rows = $pdo->query('SELECT b.*, CONCAT(c.last_name," ",c.first_name) client_name, rt.name room_type_name, r.number room_number FROM bookings b INNER JOIN clients c ON c.id=b.client_id INNER JOIN room_types rt ON rt.id=b.room_type_id LEFT JOIN rooms r ON r.id=b.room_id WHERE (b.date_in=CURDATE() AND b.status IN ("pending","confirmed")) OR b.status="checked_in" OR (b.date_out=CURDATE() AND b.status="confirmed") ORDER BY b.date_in,b.code')->fetchAll();
$availability = new AvailabilityService($pdo);
$title = 'Операционный день';
require __DIR__ . '/../../templates/admin/header.php';
?>
<section class="admin-card"><h2>Заезды, проживающие и выезды</h2><p class="muted">Рабочая лента ресепшена на сегодня. Заселение назначает физический номер, выезд автоматически ставит задачу уборки.</p><div class="table-scroll"><table class="admin-table"><thead><tr><th>Бронь / гость</th><th>Даты</th><th>Размещение</th><th>Статус</th><th>Действие</th></tr></thead><tbody>
<?php foreach ($rows as $row): $freeRooms = $availability->availablePhysicalRooms((int) $row['room_type_id'], (string) $row['date_in'], (string) $row['date_out']); ?><tr><td><strong><?= e((string) $row['code']) ?></strong><br><?= e((string) $row['client_name']) ?></td><td><?= e((string) $row['date_in']) ?> → <?= e((string) $row['date_out']) ?></td><td><?= e((string) $row['room_type_name']) ?><br><small><?= $row['room_number'] ? '№ ' . e((string) $row['room_number']) : 'не назначен' ?></small></td><td><span class="status status-<?= e((string) $row['status']) ?>"><?= e(booking_status_label((string) $row['status'])) ?></span></td><td><div class="table-actions">
<?php if ($row['status'] === 'pending'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= e((string) $row['id']) ?>"><button class="button" name="action" value="confirm">Подтвердить</button></form><?php endif; ?>
<?php if (in_array($row['status'], ['pending','confirmed'], true)): ?><form class="filter-row" method="post"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= e((string) $row['id']) ?>"><select name="room_id" required><option value="">Номер</option><?php foreach ($freeRooms as $room): ?><option value="<?= e((string) $room['id']) ?>">№ <?= e((string) $room['number']) ?> · <?= e((string) $room['floor_name']) ?></option><?php endforeach; ?></select><button class="button" name="action" value="check_in">Заселить</button><button class="button button-secondary" name="action" value="no_show">Неявка</button></form><?php endif; ?>
<?php if ($row['status'] === 'checked_in'): ?><form method="post" data-confirm="Оформить выезд и отправить номер на уборку?"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= e((string) $row['id']) ?>"><button class="button" name="action" value="check_out">Выселить</button></form><?php endif; ?>
</div></td></tr><?php endforeach; ?><?php if ($rows === []): ?><tr><td colspan="5">На сегодня операций нет.</td></tr><?php endif; ?></tbody></table></div></section>
<?php require __DIR__ . '/../../templates/admin/footer.php'; ?>
