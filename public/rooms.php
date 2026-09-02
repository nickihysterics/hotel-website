<?php

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;
use App\Service\AvailabilityService;

$auth = new Auth($pdo);
$dateIn = (string) ($_GET['date_in'] ?? (new DateTimeImmutable('tomorrow'))->format('Y-m-d'));
$dateOut = (string) ($_GET['date_out'] ?? (new DateTimeImmutable('+3 days'))->format('Y-m-d'));
$adults = min(6, max(1, (int) ($_GET['adults'] ?? 2)));
$children = min(4, max(0, (int) ($_GET['children'] ?? 0)));
$categoryId = max(0, (int) ($_GET['category_id'] ?? 0));
$maxPrice = max(0, (float) ($_GET['max_price'] ?? 0));
$searchError = null;

try {
    $start = new DateTimeImmutable($dateIn);
    $end = new DateTimeImmutable($dateOut);
    if ($end <= $start) {
        throw new RuntimeException('Дата выезда должна быть позже даты заезда.');
    }
    $rooms = (new AvailabilityService($pdo))->search($dateIn, $dateOut, [
        'adults' => $adults,
        'children' => $children,
        'category_id' => $categoryId,
        'max_price' => $maxPrice,
    ]);
} catch (Throwable $exception) {
    $rooms = [];
    $searchError = 'Проверьте выбранные даты.';
    $dateIn = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
    $dateOut = (new DateTimeImmutable('+3 days'))->format('Y-m-d');
    $start = new DateTimeImmutable($dateIn);
    $end = new DateTimeImmutable($dateOut);
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$nights = max(1, (int) $start->diff($end)->days);
$title = 'Номера';
$active = 'rooms';
require __DIR__ . '/../templates/site/header.php';
?>
<main class="product-page catalog-page">
  <section class="catalog-hero">
    <div class="product-container">
      <span class="eyebrow light">Прямое бронирование</span>
      <h1>Найдите номер<br>для своей поездки</h1>
      <p>Актуальная доступность, прозрачная цена и дополнительные услуги — без звонков и ожидания.</p>
    </div>
  </section>

  <section class="product-container search-overlap">
    <form class="search-card" method="get">
      <label><span>Заезд</span><input type="date" name="date_in" value="<?= e($dateIn) ?>" min="<?= e(date('Y-m-d')) ?>" required></label>
      <label><span>Выезд</span><input type="date" name="date_out" value="<?= e($dateOut) ?>" min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" required></label>
      <label><span>Взрослые</span><select name="adults"><?php for ($i = 1; $i <= 6; ++$i): ?><option value="<?= $i ?>" <?= $adults === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?></select></label>
      <label><span>Дети</span><select name="children"><?php for ($i = 0; $i <= 4; ++$i): ?><option value="<?= $i ?>" <?= $children === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?></select></label>
      <button class="product-button" type="submit">Найти</button>
      <details class="search-more"><summary>Фильтры</summary><div><label><span>Категория</span><select name="category_id"><option value="0">Все</option><?php foreach ($categories as $category): ?><option value="<?= e((string) $category['id']) ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e((string) $category['name']) ?></option><?php endforeach; ?></select></label><label><span>Цена до</span><input type="number" name="max_price" value="<?= $maxPrice > 0 ? e((string) $maxPrice) : '' ?>" min="0" step="500" placeholder="Без ограничения"></label></div></details>
    </form>
  </section>

  <section class="product-container catalog-content">
    <div class="section-heading row-heading"><div><span class="eyebrow">Доступно на выбранные даты</span><h2><?= count($rooms) ?> вариантов · <?= $nights ?> <?= $nights === 1 ? 'ночь' : 'ночи' ?></h2></div><p><?= e((new DateTimeImmutable($dateIn))->format('d.m')) ?> — <?= e((new DateTimeImmutable($dateOut))->format('d.m.Y')) ?></p></div>
    <?php if ($searchError): ?><div class="form-alert"><?= e($searchError) ?></div><?php endif; ?>
    <div class="room-grid product-room-grid">
      <?php foreach ($rooms as $room): ?>
        <article class="product-room-card">
          <a class="room-photo" href="/rooms-single.php?id=<?= e((string) $room['id']) ?>&date_in=<?= e($dateIn) ?>&date_out=<?= e($dateOut) ?>&adults=<?= $adults ?>&children=<?= $children ?>" style="background-image:url('/images/<?= e((string) $room['photo']) ?>')"><span class="availability-chip">Свободно: <?= e((string) $room['available_rooms']) ?></span><?php if ((int) $room['featured'] === 1): ?><span class="featured-chip">Выбор гостей</span><?php endif; ?></a>
          <div class="room-card-body">
            <span class="eyebrow"><?= e((string) $room['category_name']) ?> · <?= e((string) $room['size_sqm']) ?> м²</span>
            <h3><a href="/rooms-single.php?id=<?= e((string) $room['id']) ?>"><?= e((string) $room['name']) ?></a></h3>
            <p><?= e((string) $room['short_description']) ?></p>
            <div class="room-tags"><span><?= e((string) $room['capacity_adults']) ?> взрослых</span><span><?= e((string) $room['bed_count']) ?> кров.</span><span><?= e((string) $room['view_name']) ?></span></div>
            <div class="room-price-row"><div><small>за <?= $nights ?> ноч.</small><strong><?= e(money((float) $room['base_price'] * $nights)) ?></strong><span><?= e(money($room['base_price'])) ?> / ночь</span></div><a class="product-button small" href="/book.php?room_type_id=<?= e((string) $room['id']) ?>&date_in=<?= e($dateIn) ?>&date_out=<?= e($dateOut) ?>&adults=<?= $adults ?>&children=<?= $children ?>">Выбрать</a></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if ($rooms === [] && !$searchError): ?><div class="product-card empty-state"><span>⌁</span><h2>На эти даты всё занято</h2><p>Попробуйте изменить даты или количество гостей.</p></div><?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/../templates/site/footer.php'; ?>
