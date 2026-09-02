<?php

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;

$auth = new Auth($pdo);
$stmt = $pdo->prepare(
    'SELECT room_types.*, categories.name AS category_name, room_views.name AS view_name,'
    . ' bathroom_types.name AS bathroom_name, COUNT(rooms.id) AS inventory'
    . ' FROM room_types INNER JOIN categories ON categories.id = room_types.category_id'
    . ' INNER JOIN room_views ON room_views.id = room_types.view_id'
    . ' INNER JOIN bathroom_types ON bathroom_types.id = room_types.bathroom_type_id'
    . ' LEFT JOIN rooms ON rooms.room_type_id = room_types.id AND rooms.active = 1'
    . ' WHERE room_types.id = :id AND room_types.active = 1 GROUP BY room_types.id'
);
$stmt->execute(['id' => (int) ($_GET['id'] ?? 0)]);
$room = $stmt->fetch();
if (!$room) {
    http_response_code(404);
    exit('Номер не найден.');
}
$dateIn = (string) ($_GET['date_in'] ?? (new DateTimeImmutable('tomorrow'))->format('Y-m-d'));
$dateOut = (string) ($_GET['date_out'] ?? (new DateTimeImmutable('+3 days'))->format('Y-m-d'));
$adults = min((int) $room['capacity_adults'], max(1, (int) ($_GET['adults'] ?? 2)));
$children = min((int) $room['capacity_children'], max(0, (int) ($_GET['children'] ?? 0)));
$title = (string) $room['name'];
$active = 'rooms';
require __DIR__ . '/../templates/site/header.php';
?>
<main class="product-page room-detail-page">
  <section class="room-detail-hero" style="background-image:url('/images/<?= e((string) $room['photo']) ?>')"><div class="overlay"></div><div class="product-container"><a class="back-link light" href="/rooms.php">← К доступным номерам</a><span class="eyebrow light"><?= e((string) $room['category_name']) ?></span><h1><?= e((string) $room['name']) ?></h1><p><?= e((string) $room['short_description']) ?></p></div></section>
  <section class="product-container room-detail-layout">
    <article class="room-story"><span class="eyebrow">О номере</span><h2>Продуманный комфорт<br>без лишнего шума</h2><p><?= e((string) $room['description']) ?></p><div class="amenity-grid"><div><strong><?= e((string) $room['size_sqm']) ?> м²</strong><span>Площадь</span></div><div><strong><?= e((string) $room['capacity_adults']) ?> + <?= e((string) $room['capacity_children']) ?></strong><span>Взрослые + дети</span></div><div><strong><?= e((string) $room['bed_count']) ?></strong><span>Спальных мест</span></div><div><strong><?= e((string) $room['view_name']) ?></strong><span>Вид из окна</span></div><div><strong><?= e((string) $room['bathroom_name']) ?></strong><span>Санузел</span></div><div><strong>Wi-Fi</strong><span>Включён</span></div></div></article>
    <aside class="product-card booking-widget"><span class="eyebrow">Прямое бронирование</span><div class="widget-price"><strong><?= e(money($room['base_price'])) ?></strong><span>/ ночь</span></div><form class="product-form" action="/book.php" method="get"><input type="hidden" name="room_type_id" value="<?= e((string) $room['id']) ?>"><div class="form-row two"><label>Заезд<input type="date" name="date_in" value="<?= e($dateIn) ?>" min="<?= e(date('Y-m-d')) ?>" required></label><label>Выезд<input type="date" name="date_out" value="<?= e($dateOut) ?>" min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" required></label></div><div class="form-row two"><label>Взрослые<input type="number" name="adults" min="1" max="<?= e((string) $room['capacity_adults']) ?>" value="<?= $adults ?>"></label><label>Дети<input type="number" name="children" min="0" max="<?= e((string) $room['capacity_children']) ?>" value="<?= $children ?>"></label></div><button class="product-button full">Проверить и забронировать</button></form><p class="widget-note">Без комиссии · моментальное подтверждение после тестовой оплаты</p></aside>
  </section>
</main>
<?php require __DIR__ . '/../templates/site/footer.php'; ?>
