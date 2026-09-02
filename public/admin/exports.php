<?php

declare(strict_types=1);
require __DIR__.'/../../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;

$auth = new Auth($pdo);
$auth->requirePermission('reports.export');
$exports = ['bookings' => ['Бронирования','Гости, даты, размещение, суммы и статусы'],'rooms' => ['Номерной фонд','Физические комнаты и их готовность'],'clients' => ['Клиенты','Контакты, предпочтения и согласия'],'services' => ['Заказы услуг','Состав, исполнение и выручка дополнительных услуг'],'payments' => ['Платежи','Операции и статусы оплаты'],'associates' => ['Сотрудники','Команда и контактные данные']];
$title = 'Экспорт в Excel';
require __DIR__.'/../../templates/admin/header.php';
?>
<section class="admin-card"><h2>Готовые отчёты .xlsx</h2><p class="muted">Настоящие книги Excel: заголовки, фильтры и закреплённая первая строка. Формируются из актуальных данных.</p><div class="analytics-grid"><?php foreach ($exports as $type => $item):?><article class="stat-card"><p class="stat-label"><?=e($item[0])?></p><p class="stat-hint"><?=e($item[1])?></p><a class="button" href="/admin/export.php?type=<?=e($type)?>">Скачать .xlsx</a></article><?php endforeach;?></div></section>
<?php require __DIR__.'/../../templates/admin/footer.php';?>
