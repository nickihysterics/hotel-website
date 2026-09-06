<?php
/** @var string $title Заголовок страницы. */
/** @var string $active Ключ активного пункта меню. */
$active = $active ?? '';
$siteUser = isset($auth) && $auth instanceof App\Security\Auth ? $auth->user() : null;
$siteCssVersion = @filemtime(__DIR__ . '/../../public/css/product.css') ?: time();
?>
<!DOCTYPE html>
<html lang="ru">
  <head>
    <link rel="shortcut icon" href="/images/hotel5stars.png" type="image/x-icon">
    <title><?= e($title) ?> · Hotel</title>
    <meta name="description" content="Бронирование номеров и дополнительные услуги отеля в Советском.">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&amp;family=Source+Serif+4:opsz,wght@8..60,500;8..60,600&amp;display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/css/open-iconic-bootstrap.min.css">
    <link rel="stylesheet" href="/css/animate.css">

    <link rel="stylesheet" href="/css/owl.carousel.min.css">
    <link rel="stylesheet" href="/css/owl.theme.default.min.css">
    <link rel="stylesheet" href="/css/magnific-popup.css">

    <link rel="stylesheet" href="/css/aos.css">

    <link rel="stylesheet" href="/css/ionicons.min.css">

    <link rel="stylesheet" href="/css/bootstrap-datepicker.css">
    <link rel="stylesheet" href="/css/jquery.timepicker.css">

    <link rel="stylesheet" href="/css/flaticon.css">
    <link rel="stylesheet" href="/css/icomoon.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/product.css?v=<?= e((string) $siteCssVersion) ?>">
  </head>
  <body>

    <nav class="navbar navbar-expand-lg navbar-dark ftco_navbar bg-dark ftco-navbar-light" id="ftco-navbar">
      <div class="container">
        <a class="navbar-brand" href="/"><span class="brand-mark">H</span> Hotel</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#ftco-nav" aria-controls="ftco-nav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="oi oi-menu"></span>
        </button>

        <div class="collapse navbar-collapse" id="ftco-nav">
          <ul class="navbar-nav ml-auto">
            <li class="nav-item <?= $active === 'home' ? 'active' : '' ?>"><a href="/" class="nav-link">Главная</a></li>
            <li class="nav-item <?= $active === 'rooms' ? 'active' : '' ?>"><a href="/rooms.php" class="nav-link">Номера</a></li>
            <li class="nav-item <?= $active === 'services' ? 'active' : '' ?>"><a href="/services.php" class="nav-link">Услуги</a></li>
            <li class="nav-item <?= $active === 'contact' ? 'active' : '' ?>"><a href="/contact.php" class="nav-link">Контакты</a></li>
            <?php if ($siteUser && (string) $siteUser['role'] === 'guest'): ?>
              <li class="nav-item <?= $active === 'account' ? 'active' : '' ?>"><a href="/account/" class="nav-link">Мои поездки</a></li>
              <li class="nav-item"><a href="/logout.php" class="nav-link nav-link-muted">Выйти</a></li>
            <?php elseif ($siteUser): ?>
              <li class="nav-item"><a href="/admin/" class="nav-link">Панель</a></li>
            <?php else: ?>
              <li class="nav-item <?= $active === 'login' ? 'active' : '' ?>"><a href="/login.php" class="nav-link">Войти</a></li>
              <li class="nav-item"><a href="/register.php" class="nav-link nav-link-cta">Регистрация</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </nav>
    <?php if ($message = flash('success')): ?>
      <div class="site-flash site-flash-success" role="status"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($message = flash('error')): ?>
      <div class="site-flash site-flash-error" role="alert"><?= e($message) ?></div>
    <?php endif; ?>
