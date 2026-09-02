<?php

declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;

$auth = new Auth($pdo);
$auth->requireGuest();
$clientId = (int) $auth->clientId();
$bookingId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT bookings.*, room_types.name AS room_type_name, room_types.photo, room_types.description,'
    . ' rooms.number AS room_number, floors.name AS floor_name,'
    . ' COALESCE(payment_totals.paid, 0) AS paid_total'
    . ' FROM bookings'
    . ' INNER JOIN room_types ON room_types.id = bookings.room_type_id'
    . ' LEFT JOIN rooms ON rooms.id = bookings.room_id'
    . ' LEFT JOIN floors ON floors.id = rooms.floor_id'
    . ' LEFT JOIN ('
    . " SELECT booking_id, SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) AS paid"
    . ' FROM payments GROUP BY booking_id'
    . ' ) payment_totals ON payment_totals.booking_id = bookings.id'
    . ' WHERE bookings.id = :id AND bookings.client_id = :client_id'
);
$stmt->execute(['id' => $bookingId, 'client_id' => $clientId]);
$booking = $stmt->fetch();
if (!$booking) {
    http_response_code(404);
    exit('Бронирование не найдено.');
}

$serviceStmt = $pdo->prepare(
    'SELECT booking_services.*, services.name, services.icon, services.pricing_type'
    . ' FROM booking_services INNER JOIN services ON services.id = booking_services.service_id'
    . ' WHERE booking_services.booking_id = :booking_id ORDER BY booking_services.created_at DESC'
);
$serviceStmt->execute(['booking_id' => $bookingId]);
$orderedServices = $serviceStmt->fetchAll();

$availableServices = $pdo->query(
    'SELECT services.*, service_categories.name AS category_name FROM services'
    . ' INNER JOIN service_categories ON service_categories.id = services.category_id'
    . ' WHERE services.active = 1 ORDER BY service_categories.sort_order, services.name'
)->fetchAll();

$paymentStmt = $pdo->prepare('SELECT * FROM payments WHERE booking_id = :booking_id ORDER BY created_at DESC');
$paymentStmt->execute(['booking_id' => $bookingId]);
$payments = $paymentStmt->fetchAll();

$balance = max(0, (float) $booking['total'] - (float) $booking['paid_total']);
$canOrder = in_array($booking['status'], ['pending', 'confirmed', 'checked_in'], true);
$title = 'Бронирование ' . $booking['code'];
$active = 'account';
require __DIR__ . '/../../templates/site/header.php';
?>
<main class="product-page account-page">
  <section class="product-container detail-heading">
    <div><a class="back-link" href="/account/">← Все поездки</a><span class="eyebrow">Бронирование № <?= e((string) $booking['code']) ?></span><h1><?= e((string) $booking['room_type_name']) ?></h1></div>
    <span class="status-pill status-<?= e((string) $booking['status']) ?>"><?= e(booking_status_label((string) $booking['status'])) ?></span>
  </section>

  <section class="product-container detail-grid">
    <div class="detail-main">
      <article class="product-card stay-summary">
        <div class="stay-photo" style="background-image:url('/images/<?= e((string) $booking['photo']) ?>')"></div>
        <div class="booking-facts large">
          <span><strong>Заезд</strong><?= e((new DateTimeImmutable((string) $booking['date_in']))->format('d.m.Y')) ?><small>после 14:00</small></span>
          <span><strong>Выезд</strong><?= e((new DateTimeImmutable((string) $booking['date_out']))->format('d.m.Y')) ?><small>до 12:00</small></span>
          <span><strong>Гости</strong><?= e((string) $booking['adults']) ?> взрослых · <?= e((string) $booking['children']) ?> детей</span>
          <span><strong>Номер</strong><?= e((string) ($booking['room_number'] ?? 'Назначит отель')) ?><?= $booking['floor_name'] ? '<small>' . e((string) $booking['floor_name']) . '</small>' : '' ?></span>
        </div>
        <?php if ($booking['guest_comment']): ?><p class="guest-note"><strong>Ваш комментарий:</strong> <?= e((string) $booking['guest_comment']) ?></p><?php endif; ?>
      </article>

      <article class="product-card">
        <div class="section-heading row-heading"><div><span class="eyebrow">Дополнительный комфорт</span><h2>Заказанные услуги</h2></div><?php if ($canOrder): ?><button class="product-button small secondary" type="button" data-toggle-panel="services-panel">Добавить услугу</button><?php endif; ?></div>
        <div class="order-list">
          <?php foreach ($orderedServices as $service): ?>
            <div class="order-row"><div class="service-icon">✦</div><div><strong><?= e((string) $service['name']) ?></strong><span><?= e((string) $service['quantity']) ?> × <?= e(money($service['unit_price'])) ?></span></div><strong><?= e(money($service['total'])) ?></strong><span class="status-pill compact status-<?= e((string) $service['status']) ?>"><?= e(service_status_label((string) $service['status'])) ?></span></div>
          <?php endforeach; ?>
          <?php if ($orderedServices === []): ?><p class="muted">Услуги пока не добавлены.</p><?php endif; ?>
        </div>
      </article>

      <?php if ($canOrder): ?>
        <article id="services-panel" class="product-card service-order-panel" hidden>
          <div class="section-heading compact"><span class="eyebrow">Заказать онлайн</span><h2>Выберите услугу</h2></div>
          <form class="product-form" method="post" action="/account/service-order.php">
            <?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= e((string) $bookingId) ?>">
            <div class="service-radio-grid">
              <?php foreach ($availableServices as $service): ?>
                <label class="service-radio"><input type="radio" name="service_id" value="<?= e((string) $service['id']) ?>" required><span><small><?= e((string) $service['category_name']) ?></small><strong><?= e((string) $service['name']) ?></strong><em><?= e(money($service['price'])) ?></em></span></label>
              <?php endforeach; ?>
            </div>
            <div class="form-row two"><label>Количество<input type="number" name="quantity" min="1" max="20" value="1" required></label><label>Когда выполнить<input type="datetime-local" name="scheduled_for"></label></div>
            <label>Комментарий<textarea name="guest_note" rows="3" placeholder="Например: без лактозы или детское кресло"></textarea></label>
            <button class="product-button" type="submit">Добавить к бронированию</button>
          </form>
        </article>
      <?php endif; ?>
    </div>

    <aside class="detail-sidebar">
      <article class="product-card price-card">
        <span class="eyebrow">Итоговый счёт</span>
        <div class="price-line"><span>Проживание</span><strong><?= e(money($booking['accommodation_total'])) ?></strong></div>
        <div class="price-line"><span>Услуги</span><strong><?= e(money($booking['services_total'])) ?></strong></div>
        <?php if ((float) $booking['discount_total'] > 0): ?><div class="price-line discount"><span>Скидка</span><strong>−<?= e(money($booking['discount_total'])) ?></strong></div><?php endif; ?>
        <div class="price-line total"><span>Итого</span><strong><?= e(money($booking['total'])) ?></strong></div>
        <div class="price-line"><span>Оплачено</span><strong><?= e(money($booking['paid_total'])) ?></strong></div>
        <div class="price-line balance"><span>Осталось</span><strong><?= e(money($balance)) ?></strong></div>
        <?php if ($balance > 0 && $booking['status'] !== 'cancelled'): ?><a class="product-button full" href="/account/payment.php?id=<?= e((string) $bookingId) ?>">Оплатить в sandbox</a><?php endif; ?>
        <a class="product-button full secondary" href="/account/invoice.php?id=<?= e((string) $bookingId) ?>" target="_blank">Открыть счёт</a>
      </article>
      <article class="product-card timeline-card"><span class="eyebrow">История оплаты</span><?php foreach ($payments as $payment): ?><div class="timeline-item"><span></span><div><strong><?= e(money($payment['amount'])) ?></strong><small><?= e((string) $payment['status']) ?> · <?= e((string) $payment['created_at']) ?></small></div></div><?php endforeach; ?><?php if ($payments === []): ?><p class="muted">Платежей пока нет.</p><?php endif; ?></article>
    </aside>
  </section>
</main>
<?php require __DIR__ . '/../../templates/site/footer.php'; ?>
