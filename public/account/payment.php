<?php

declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;

$auth = new Auth($pdo);
$auth->requireGuest();
$bookingId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT bookings.id, bookings.code, bookings.status, bookings.total, room_types.name,'
    . " COALESCE(SUM(CASE WHEN payments.status = 'paid' THEN payments.amount ELSE 0 END), 0) AS paid"
    . ' FROM bookings INNER JOIN room_types ON room_types.id = bookings.room_type_id'
    . ' LEFT JOIN payments ON payments.booking_id = bookings.id'
    . ' WHERE bookings.id = :id AND bookings.client_id = :client_id GROUP BY bookings.id'
);
$stmt->execute(['id' => $bookingId, 'client_id' => $auth->clientId()]);
$booking = $stmt->fetch();
if (!$booking) {
    http_response_code(404);
    exit('Бронирование не найдено.');
}
$balance = max(0, (float) $booking['total'] - (float) $booking['paid']);

if (is_post()) {
    if (!verify_csrf($_POST['_token'] ?? null)) {
        flash('error', 'Платёж недоступен.');
        redirect('/account/booking.php?id=' . $bookingId);
    }

    // Блокировка брони не даёт двум вкладкам одновременно оплатить один и тот же остаток.
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare(
            'SELECT total, status FROM bookings WHERE id = :id AND client_id = :client_id FOR UPDATE'
        );
        $lock->execute(['id' => $bookingId, 'client_id' => $auth->clientId()]);
        $lockedBooking = $lock->fetch();
        $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = :id AND status = 'paid'");
        $paidStmt->execute(['id' => $bookingId]);
        // Остаток считается заново внутри транзакции, а не берётся из ранее показанной страницы.
        $currentBalance = $lockedBooking ? max(0, (float) $lockedBooking['total'] - (float) $paidStmt->fetchColumn()) : 0;
        if (!$lockedBooking || $currentBalance <= 0 || $lockedBooking['status'] === 'cancelled') {
            throw new DomainException('Бронирование уже оплачено или платёж недоступен.');
        }
        // Sandbox хранит только результат симуляции и никогда не принимает банковские реквизиты.
        $result = (string) ($_POST['sandbox_result'] ?? 'success');
        $status = $result === 'success' ? 'paid' : 'failed';
        $reference = 'sandbox_' . bin2hex(random_bytes(6));
        $insert = $pdo->prepare(
            'INSERT INTO payments (booking_id, amount, method, status, provider_reference, paid_at)'
            . ' VALUES (:booking_id, :amount, :method, :status, :reference, :paid_at)'
        );
        $insert->execute([
            'booking_id' => $bookingId,
            'amount' => $currentBalance,
            'method' => 'sandbox',
            'status' => $status,
            'reference' => $reference,
            'paid_at' => $status === 'paid' ? date('Y-m-d H:i:s') : null,
        ]);
        if ($status === 'paid' && $lockedBooking['status'] === 'pending') {
            $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = :id")
                ->execute(['id' => $bookingId]);
        }
        audit_log($pdo, 'payment.' . $status, 'booking', (string) $bookingId, ['reference' => $reference, 'amount' => $currentBalance]);
        $pdo->commit();
        flash($status === 'paid' ? 'success' : 'error', $status === 'paid' ? 'Тестовая оплата прошла успешно.' : 'Платёж отклонён в тестовом режиме.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception instanceof DomainException ? $exception->getMessage() : 'Платёж не выполнен.');
    }
    redirect('/account/booking.php?id=' . $bookingId);
}

$title = 'Тестовая оплата';
$active = 'account';
require __DIR__ . '/../../templates/site/header.php';
?>
<main class="product-page auth-page">
  <section class="product-container payment-shell">
    <div class="product-card payment-card">
      <span class="sandbox-badge">SANDBOX · деньги не списываются</span>
      <span class="eyebrow">Бронирование <?= e((string) $booking['code']) ?></span>
      <h1><?= e((string) $booking['name']) ?></h1>
      <div class="payment-amount"><small>К оплате</small><strong><?= e(money($balance)) ?></strong></div>
      <form class="product-form fake-card" method="post">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $bookingId) ?>">
        <label>Номер карты<input value="4242 4242 4242 4242" inputmode="numeric" readonly></label>
        <div class="form-row two"><label>Срок<input value="12/30" readonly></label><label>CVC<input value="123" readonly></label></div>
        <button class="product-button full" name="sandbox_result" value="success">Успешная оплата</button>
        <button class="product-button full secondary" name="sandbox_result" value="failed">Симулировать отказ</button>
      </form>
      <a class="back-link centered" href="/account/booking.php?id=<?= e((string) $bookingId) ?>">Вернуться без оплаты</a>
    </div>
  </section>
</main>
<?php require __DIR__ . '/../../templates/site/footer.php'; ?>
