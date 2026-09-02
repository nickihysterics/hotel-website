<?php

declare(strict_types=1);
require __DIR__ . '/../../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;

$auth = new Auth($pdo);
if (!$auth->can('operations.manage') && !$auth->can('reports.view')) {
    $auth->requirePermission('reports.view');
}

if (is_post()) {
    // Просмотр доступен отчётным ролям, а изменение финансовых статусов — только операционным.
    $auth->requirePermission('operations.manage');
    if (!verify_csrf($_POST['_token'] ?? null)) {
        flash('error', 'Сессия формы истекла.');
        redirect('/admin/payments.php');
    }
    $action = (string) ($_POST['action'] ?? 'add');
    try {
        // Запись платежа или возврата и аудит фиксируются как единая операция.
        $pdo->beginTransaction();
        if ($action === 'refund') {
            $paymentId = (int) ($_POST['payment_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE payments SET status = 'refunded' WHERE id = :id AND status = 'paid'");
            $stmt->execute(['id' => $paymentId]);
            if ($stmt->rowCount() !== 1) {
                throw new DomainException('Платёж уже возвращён или недоступен.');
            }
            audit_log($pdo, 'payment.refunded_by_staff', 'payment', (string) $paymentId);
        } else {
            $bookingId = (int) ($_POST['booking_id'] ?? 0);
            $method = (string) ($_POST['method'] ?? 'cash');
            $amount = round((float) ($_POST['amount'] ?? 0), 2);
            if (!in_array($method, ['cash', 'card'], true)) {
                throw new DomainException('Некорректный способ оплаты.');
            }
            $stmt = $pdo->prepare('SELECT total, status FROM bookings WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $bookingId]);
            $booking = $stmt->fetch();
            $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = :id AND status = 'paid'");
            $paidStmt->execute(['id' => $bookingId]);
            $balance = $booking ? max(0, (float) $booking['total'] - (float) $paidStmt->fetchColumn()) : 0;
            if (!$booking || $amount <= 0 || $amount > $balance || $booking['status'] === 'cancelled') {
                throw new DomainException('Сумма должна быть больше нуля и не превышать остаток по брони.');
            }
            $pdo->prepare("INSERT INTO payments(booking_id,amount,method,status,provider_reference,paid_at) VALUES(:booking_id,:amount,:method,'paid',:reference,NOW())")
                ->execute(['booking_id' => $bookingId, 'amount' => $amount, 'method' => $method, 'reference' => 'desk_' . bin2hex(random_bytes(5))]);
            $paymentId = (int) $pdo->lastInsertId();
            if ($booking['status'] === 'pending') {
                $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = :id")->execute(['id' => $bookingId]);
            }
            audit_log($pdo, 'payment.recorded_by_staff', 'payment', (string) $paymentId, ['amount' => $amount, 'method' => $method]);
        }
        $pdo->commit();
        flash('success', $action === 'refund' ? 'Возврат зарегистрирован.' : 'Платёж добавлен.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception instanceof DomainException ? $exception->getMessage() : 'Не удалось обработать платёж.');
    }
    redirect('/admin/payments.php');
}

$payments = $pdo->query('SELECT p.*,b.code,CONCAT(c.last_name," ",c.first_name) client_name FROM payments p INNER JOIN bookings b ON b.id=p.booking_id INNER JOIN clients c ON c.id=b.client_id ORDER BY p.created_at DESC LIMIT 300')->fetchAll();
$bookings = $pdo->query("SELECT b.id, b.code, b.total-COALESCE(p.paid,0) balance, CONCAT(c.last_name,' ',c.first_name) client_name FROM bookings b INNER JOIN clients c ON c.id=b.client_id LEFT JOIN (SELECT booking_id,SUM(amount) paid FROM payments WHERE status='paid' GROUP BY booking_id) p ON p.booking_id=b.id WHERE b.status NOT IN ('cancelled','no_show') AND b.total-COALESCE(p.paid,0)>0 ORDER BY b.date_in DESC")->fetchAll();
$title = 'Платежи';
require __DIR__ . '/../../templates/admin/header.php';
?>
<?php if ($auth->can('operations.manage')): ?><section class="admin-card"><h2>Зарегистрировать оплату</h2><form class="admin-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add"><div class="form-grid"><label class="form-field">Бронирование<select name="booking_id" required><option value="">Выберите</option><?php foreach ($bookings as $row): ?><option value="<?= e((string) $row['id']) ?>"><?= e((string) $row['code']) ?> · <?= e((string) $row['client_name']) ?> · остаток <?= e(money($row['balance'])) ?></option><?php endforeach; ?></select></label><label class="form-field">Сумма<input type="number" name="amount" min="0.01" step="0.01" required></label><label class="form-field">Способ<select name="method"><option value="cash">Наличные</option><option value="card">Карта на стойке</option></select></label></div><button class="button">Добавить платёж</button></form></section><?php endif; ?>
<section class="admin-card"><h2>Реестр операций</h2><div class="table-scroll"><table class="admin-table"><thead><tr><th>Дата</th><th>Бронь / гость</th><th>Сумма</th><th>Метод</th><th>Статус</th><th>Reference</th><th></th></tr></thead><tbody><?php foreach ($payments as $row): ?><tr><td><?= e((string) $row['created_at']) ?></td><td><strong><?= e((string) $row['code']) ?></strong><br><small><?= e((string) $row['client_name']) ?></small></td><td><?= e(money($row['amount'])) ?></td><td><?= e((string) $row['method']) ?></td><td><span class="status status-<?= e((string) $row['status']) ?>"><?= e((string) $row['status']) ?></span></td><td><small><?= e((string) $row['provider_reference']) ?></small></td><td><?php if ($auth->can('operations.manage') && $row['status'] === 'paid'): ?><form method="post" data-confirm="Зарегистрировать возврат всего платежа?"><?= csrf_field() ?><input type="hidden" name="action" value="refund"><input type="hidden" name="payment_id" value="<?= e((string) $row['id']) ?>"><button class="button button-secondary">Возврат</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php require __DIR__ . '/../../templates/admin/footer.php'; ?>
