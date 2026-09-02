<?php
/** @var string $title */
/** @var App\Security\Auth $auth */
$adminCssVersion = @filemtime(__DIR__ . '/../../public/css/admin.css') ?: time();
$adminUser = $auth->user();
$roleLabels = ['admin' => 'Администратор', 'manager' => 'Менеджер', 'reception' => 'Ресепшен', 'housekeeping' => 'Хаускипинг', 'analyst' => 'Аналитик'];
?>
<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Hotel Operations</title>
    <link rel="stylesheet" href="/css/admin.css?v=<?= e((string) $adminCssVersion) ?>">
  </head>
  <body class="admin-app">
    <aside class="admin-sidebar">
      <a class="admin-brand" href="/admin/"><span>H</span><strong>Hotel<small>Operations</small></strong></a>
      <nav class="admin-nav">
        <?php if ($auth->can('dashboard.view')): ?><a href="/admin/index.php">Обзор</a><?php endif; ?>
        <?php if ($auth->can('operations.manage')): ?><a href="/admin/operations.php">Операционный день</a><?php endif; ?>
        <?php if ($auth->can('bookings.manage')): ?><a href="/admin/bookings.php">Бронирования</a><?php endif; ?>
        <?php if ($auth->can('operations.manage') || $auth->can('reports.view')): ?><a href="/admin/payments.php">Платежи</a><?php endif; ?>
        <?php if ($auth->can('services.orders') || $auth->can('services.manage')): ?><a href="/admin/service_orders.php">Заказы услуг</a><?php endif; ?>
        <?php if ($auth->can('housekeeping.manage') || $auth->can('operations.manage')): ?><a href="/admin/housekeeping.php">Уборка</a><?php endif; ?>
        <?php if ($auth->can('clients.manage')): ?><a href="/admin/clients.php">Гости</a><?php endif; ?>
        <?php if ($auth->can('catalog.manage')): ?>
          <details><summary>Управление отелем</summary><div>
            <a href="/admin/rooms.php">Типы номеров</a><a href="/admin/room_units.php">Номерной фонд</a>
            <a href="/admin/categories.php">Категории</a><a href="/admin/floors.php">Этажи</a>
            <a href="/admin/room_views.php">Виды</a><a href="/admin/bathroom_types.php">Санузлы</a>
            <a href="/admin/associates.php">Сотрудники</a>
          </div></details>
        <?php endif; ?>
        <?php if ($auth->can('services.manage')): ?><details><summary>Каталог услуг</summary><div><a href="/admin/services.php">Услуги</a><a href="/admin/service_categories.php">Категории услуг</a></div></details><?php endif; ?>
        <?php if ($auth->can('reports.view')): ?><a href="/admin/analytics.php">Аналитика</a><a href="/admin/reviews.php">Отзывы</a><?php endif; ?>
        <?php if ($auth->can('reports.export')): ?><a href="/admin/exports.php">Экспорт</a><?php endif; ?>
        <?php if ($auth->role() === 'admin'): ?><a href="/admin/users.php">Пользователи</a><a href="/admin/audit.php">Журнал действий</a><?php endif; ?>
      </nav>
      <div class="admin-profile"><span><?= e(strtoupper(substr((string) ($adminUser['login'] ?? 'U'), 0, 1))) ?></span><div><strong><?= e((string) ($adminUser['login'] ?? '')) ?></strong><small><?= e($roleLabels[$auth->role() ?? ''] ?? 'Сотрудник') ?></small></div></div>
      <div class="admin-sidebar-links"><a href="/">Открыть сайт ↗</a><a href="/admin/logout.php">Выйти</a></div>
    </aside>
    <main class="admin-main">
      <header class="admin-page-header"><div><span class="admin-kicker">Hotel / <?= e($roleLabels[$auth->role() ?? ''] ?? '') ?></span><h1><?= e($title) ?></h1></div><time><?= e((new DateTimeImmutable())->format('d.m.Y')) ?></time></header>
      <?php if ($message = flash('success')): ?><div class="flash flash-success"><?= e($message) ?></div><?php endif; ?>
      <?php if ($message = flash('error')): ?><div class="flash flash-error"><?= e($message) ?></div><?php endif; ?>
