<?php

declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;

$auth = new Auth($pdo);
$auth->requireGuest();
$clientId = (int) $auth->clientId();

$profileStmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id');
$profileStmt->execute(['id' => $clientId]);
$profile = $profileStmt->fetch();

$bookingStmt = $pdo->prepare(
    'SELECT bookings.*, room_types.name AS room_type_name, room_types.photo,'
    . ' rooms.number AS room_number,'
    . ' COALESCE(payment_totals.paid, 0) AS paid_total'
    . ' FROM bookings'
    . ' INNER JOIN room_types ON room_types.id = bookings.room_type_id'
    . ' LEFT JOIN rooms ON rooms.id = bookings.room_id'
    . ' LEFT JOIN ('
    . "   SELECT booking_id, SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) AS paid"
    . '   FROM payments GROUP BY booking_id'
    . ' ) payment_totals ON payment_totals.booking_id = bookings.id'
    . ' WHERE bookings.client_id = :client_id'
    . ' ORDER BY bookings.date_in DESC, bookings.id DESC'
);
$bookingStmt->execute(['client_id' => $clientId]);
$bookings = $bookingStmt->fetchAll();

$title = 'Мои поездки';
$active = 'account';
require __DIR__ . '/../../templates/site/header.php';
?>
<main class="product-page account-page">
  <section class="product-container account-hero">
    <div>
      <span class="eyebrow">Личный кабинет</span>
      <h1><?= e((string) $profile['first_name']) ?>, ваши поездки</h1>
      <p><?= e((string) $profile['email']) ?> · <?= e((string) $profile['phone']) ?></p>
    </div>
    <div class="button-group"><a class="product-button secondary" href="/account/profile.php">Профиль</a><a class="product-button" href="/rooms.php">Новое бронирование</a></div>
  </section>

  <section class="product-container booking-stack">
    <?php foreach ($bookings as $booking): ?>
      <?php
        $balance = max(0, (float) $booking['total'] - (float) $booking['paid_total']);
        $canCancel = in_array($booking['status'], ['pending', 'confirmed'], true) && $booking['date_in'] > date('Y-m-d');
        ?>
      <article class="product-card booking-card">
        <div class="booking-image" style="background-image:url('/images/<?= e((string) $booking['photo']) ?>')"></div>
        <div class="booking-content">
          <div class="booking-title-row">
            <div><span class="eyebrow">№ <?= e((string) $booking['code']) ?></span><h2><?= e((string) $booking['room_type_name']) ?></h2></div>
            <span class="status-pill status-<?= e((string) $booking['status']) ?>"><?= e(booking_status_label((string) $booking['status'])) ?></span>
          </div>
          <div class="booking-facts">
            <span><strong>Заезд</strong><?= e((new DateTimeImmutable((string) $booking['date_in']))->format('d.m.Y')) ?></span>
            <span><strong>Выезд</strong><?= e((new DateTimeImmutable((string) $booking['date_out']))->format('d.m.Y')) ?></span>
            <span><strong>Гости</strong><?= e((string) $booking['adults']) ?> взр. · <?= e((string) $booking['children']) ?> дет.</span>
            <span><strong>Номер</strong><?= e((string) ($booking['room_number'] ?? 'назначит отель')) ?></span>
          </div>
          <div class="booking-total-row">
            <div class="booking-price"><small>Стоимость</small><strong><?= e(money($booking['total'])) ?></strong><?php if ($balance > 0): ?><span>К оплате <?= e(money($balance)) ?></span><?php else: ?><span class="paid-mark">Оплачено</span><?php endif; ?></div>
            <div class="button-group">
              <a class="product-button small secondary" href="/account/booking.php?id=<?= e((string) $booking['id']) ?>">Подробнее</a>
              <?php if ($booking['status'] === 'checked_out'): ?><a class="product-button small secondary" href="/account/review.php?id=<?= e((string) $booking['id']) ?>">Отзыв</a><?php endif; ?>
              <?php if ($balance > 0 && $booking['status'] !== 'cancelled'): ?><a class="product-button small" href="/account/payment.php?id=<?= e((string) $booking['id']) ?>">Оплатить</a><?php endif; ?>
              <?php if ($canCancel): ?>
                <form method="post" action="/account/cancel-booking.php" data-confirm="Отменить бронирование?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $booking['id']) ?>"><button class="text-button danger" type="submit">Отменить</button></form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if ($bookings === []): ?>
      <div class="product-card empty-state"><span>✦</span><h2>Пока нет бронирований</h2><p>Выберите даты и номер — всё остальное займёт пару минут.</p><a class="product-button" href="/rooms.php">Найти номер</a></div>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/../../templates/site/footer.php'; ?>
