<?php

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;

$auth = new Auth($pdo);
if ($auth->isGuest()) {
    redirect('/account/');
}

$title = 'Вход';
$active = 'login';
$email = '';

if (is_post()) {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if (!verify_csrf($_POST['_token'] ?? null)) {
        flash('error', 'Сессия формы истекла. Повторите попытку.');
    } elseif ($auth->attemptGuest($email, $password)) {
        $intended = (string) ($_SESSION['_intended_url'] ?? '/account/');
        unset($_SESSION['_intended_url']);
        redirect(str_starts_with($intended, '/') && !str_starts_with($intended, '//') ? $intended : '/account/');
    } else {
        flash('error', 'Неверный email или пароль.');
    }
}

require __DIR__ . '/../templates/site/header.php';
?>
<main class="product-page auth-page">
  <section class="product-container auth-shell auth-shell-small">
    <div class="auth-copy">
      <span class="eyebrow">С возвращением</span>
      <h1>Ваше проживание начинается здесь</h1>
      <p>Откройте бронирования, услуги и оплату в личном кабинете.</p>
      <div class="demo-credentials">
        <strong>Демо-гость</strong>
        <code>guest@hotel.localhost</code>
        <code>GuestDemo2024</code>
      </div>
    </div>
    <form class="product-card product-form auth-form" method="post">
      <?= csrf_field() ?>
      <div class="section-heading compact"><span class="eyebrow">Личный кабинет</span><h2>Войти</h2></div>
      <label>Email<input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required></label>
      <label>Пароль<input type="password" name="password" autocomplete="current-password" required></label>
      <button class="product-button" type="submit">Войти</button>
      <p class="form-footnote"><a href="/forgot-password.php">Забыли пароль?</a></p>
      <p class="form-footnote">Нет аккаунта? <a href="/register.php">Зарегистрироваться</a></p>
    </form>
  </section>
</main>
<?php require __DIR__ . '/../templates/site/footer.php'; ?>
