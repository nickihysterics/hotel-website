<?php

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;

$auth = new Auth($pdo);
$services = $pdo->query(
    'SELECT services.*, service_categories.name AS category_name FROM services'
    . ' INNER JOIN service_categories ON service_categories.id = services.category_id'
    . ' WHERE services.active = 1 ORDER BY service_categories.sort_order, services.name'
)->fetchAll();
$title = 'Услуги';
$active = 'services';
require __DIR__ . '/../templates/site/header.php';
?>
<main class="product-page services-page"><section class="simple-hero"><div class="product-container"><span class="eyebrow light">Больше, чем проживание</span><h1>Добавьте комфорта<br>в свою поездку</h1><p>Закажите услуги вместе с номером или позже — из личного кабинета.</p></div></section><section class="product-container catalog-content"><div class="section-heading"><span class="eyebrow">Сервис отеля</span><h2>Всё нужное — в пару кликов</h2></div><div class="service-catalog"><?php foreach ($services as $service): ?><article class="product-card service-card"><div class="service-icon large">✦</div><span class="eyebrow"><?= e((string) $service['category_name']) ?></span><h3><?= e((string) $service['name']) ?></h3><p><?= e((string) $service['description']) ?></p><div><strong><?= e(money($service['price'])) ?></strong><span><?= e(['per_order' => 'за заказ', 'per_day' => 'за сутки', 'per_person' => 'за гостя', 'per_unit' => 'за единицу'][$service['pricing_type']] ?? '') ?></span></div></article><?php endforeach; ?></div><div class="product-cta"><div><span class="eyebrow light">Готовы к поездке?</span><h2>Сначала выберите номер</h2><p>Все подходящие услуги можно добавить на последнем шаге бронирования.</p></div><a class="product-button light" href="/rooms.php">Найти номер</a></div></section></main>
<?php require __DIR__ . '/../templates/site/footer.php'; ?>
