<?php

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;
use App\Service\AvailabilityService;
use App\Service\BookingService;

$auth = new Auth($pdo);
$auth->requireGuest();
$roomTypeId = (int) ($_POST['room_type_id'] ?? $_GET['room_type_id'] ?? 0);
$dateIn = (string) ($_POST['date_in'] ?? $_GET['date_in'] ?? '');
$dateOut = (string) ($_POST['date_out'] ?? $_GET['date_out'] ?? '');
$adults = max(1, (int) ($_POST['adults'] ?? $_GET['adults'] ?? 1));
$children = max(0, (int) ($_POST['children'] ?? $_GET['children'] ?? 0));

$stmt = $pdo->prepare('SELECT * FROM room_types WHERE id = :id AND active = 1');
$stmt->execute(['id' => $roomTypeId]);
$room = $stmt->fetch();
if (!$room) {
    flash('error', 'Выбранный номер не найден.');
    redirect('/rooms.php');
}

try {
    $start = new DateTimeImmutable($dateIn);
    $end = new DateTimeImmutable($dateOut);
    $nights = (int) $start->diff($end)->days;
    if ($dateIn === '' || $dateOut === '' || $end <= $start || $nights < 1) {
        throw new RuntimeException();
    }
} catch (Throwable) {
    flash('error', 'Проверьте даты бронирования.');
    redirect('/rooms.php');
}

$services = $pdo->query(
    'SELECT services.*, service_categories.name AS category_name FROM services'
    . ' INNER JOIN service_categories ON service_categories.id = services.category_id'
    . ' WHERE services.active = 1 ORDER BY service_categories.sort_order, services.name'
)->fetchAll();

if (is_post()) {
    if (!verify_csrf($_POST['_token'] ?? null)) {
        flash('error', 'Сессия формы истекла. Повторите попытку.');
    } else {
        // Клиентские количества ограничиваются, но цены и множители повторно определяет серверный сервис.
        $selectedServices = [];
        foreach ((array) ($_POST['services'] ?? []) as $serviceId => $quantity) {
            $selectedServices[(int) $serviceId] = min(20, max(0, (int) $quantity));
        }
        try {
            $bookingId = (new BookingService($pdo, new AvailabilityService($pdo)))->createForClient(
                (int) $auth->clientId(),
                [
                    'room_type_id' => $roomTypeId,
                    'date_in' => $dateIn,
                    'date_out' => $dateOut,
                    'adults' => $adults,
                    'children' => $children,
                    'guest_comment' => trim((string) ($_POST['guest_comment'] ?? '')),
                ],
                $selectedServices
            );
            flash('success', 'Бронирование создано. Осталось подтвердить оплату.');
            redirect('/account/booking.php?id=' . $bookingId);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }
    }
}

$title = 'Оформление бронирования';
$active = 'rooms';
require __DIR__ . '/../templates/site/header.php';
?>
<main class="product-page checkout-page">
  <section class="product-container checkout-heading">
    <a class="back-link" href="/rooms.php?date_in=<?= e($dateIn) ?>&date_out=<?= e($dateOut) ?>&adults=<?= $adults ?>&children=<?= $children ?>">← Вернуться к вариантам</a>
    <span class="eyebrow">Последний шаг</span><h1>Оформление бронирования</h1>
  </section>
  <section class="product-container checkout-grid">
    <form class="checkout-main" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="room_type_id" value="<?= e((string) $roomTypeId) ?>">
      <input type="hidden" name="date_in" value="<?= e($dateIn) ?>"><input type="hidden" name="date_out" value="<?= e($dateOut) ?>">
      <input type="hidden" name="adults" value="<?= $adults ?>"><input type="hidden" name="children" value="<?= $children ?>">
      <article class="product-card">
        <div class="section-heading compact"><span class="step-number">01</span><span class="eyebrow">Ваше проживание</span><h2><?= e((string) $room['name']) ?></h2></div>
        <div class="booking-facts large"><span><strong>Заезд</strong><?= e($start->format('d.m.Y')) ?></span><span><strong>Выезд</strong><?= e($end->format('d.m.Y')) ?></span><span><strong>Ночей</strong><?= $nights ?></span><span><strong>Гости</strong><?= $adults ?> + <?= $children ?></span></div>
      </article>
      <article class="product-card">
        <div class="section-heading compact"><span class="step-number">02</span><span class="eyebrow">Дополнительные услуги</span><h2>Сделать поездку комфортнее</h2></div>
        <div class="checkout-services">
          <?php foreach ($services as $service): ?>
            <?php $multiplier = $service['pricing_type'] === 'per_day' ? $nights : ($service['pricing_type'] === 'per_person' ? $adults + $children : 1); ?>
            <label class="checkout-service">
              <input type="checkbox" data-service-toggle="service-<?= e((string) $service['id']) ?>"><span class="service-icon">✦</span>
              <span><small><?= e((string) $service['category_name']) ?></small><strong><?= e((string) $service['name']) ?></strong><em><?= e((string) $service['description']) ?></em></span>
              <span class="checkout-service-price"><strong><?= e(money((float) $service['price'] * $multiplier)) ?></strong><input id="service-<?= e((string) $service['id']) ?>" type="number" name="services[<?= e((string) $service['id']) ?>]" min="0" max="20" value="0" data-price="<?= e((string) $service['price']) ?>" data-multiplier="<?= $multiplier ?>" disabled></span>
            </label>
          <?php endforeach; ?>
        </div>
      </article>
      <article class="product-card"><div class="section-heading compact"><span class="step-number">03</span><span class="eyebrow">Пожелания</span><h2>Что нам подготовить?</h2></div><label class="product-form">Комментарий к бронированию<textarea name="guest_comment" rows="4" maxlength="1000" placeholder="Тихий номер, детская кроватка, время прибытия..."></textarea></label></article>
      <button class="product-button checkout-submit" type="submit">Создать бронирование</button>
    </form>
    <aside class="checkout-sidebar"><div class="product-card checkout-summary"><div class="checkout-photo" style="background-image:url('/images/<?= e((string) $room['photo']) ?>')"></div><span class="eyebrow">Итого</span><div class="price-line"><span>Проживание, <?= $nights ?> ноч.</span><strong data-accommodation-total="<?= e((string) ((float) $room['base_price'] * $nights)) ?>"><?= e(money((float) $room['base_price'] * $nights)) ?></strong></div><div class="price-line"><span>Услуги</span><strong data-services-total>0 ₽</strong></div><div class="price-line total"><span>К оплате</span><strong data-grand-total><?= e(money((float) $room['base_price'] * $nights)) ?></strong></div><p>Бронь появится в личном кабинете. Оплата выполняется в безопасном демонстрационном sandbox.</p></div></aside>
  </section>
</main>
<?php require __DIR__ . '/../templates/site/footer.php'; ?>
