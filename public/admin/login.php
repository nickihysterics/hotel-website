<?php

declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';

/** @var PDO $pdo */

use App\Config\Env;
use App\Security\Auth;

$auth = new Auth($pdo);

if ($auth->isStaff()) {
    header('Location: /admin/index.php');
    exit;
}

$userCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'guest'")->fetchColumn();
if ($userCount === 0) {
    $seedLogin = Env::get('ADMIN_USER', 'admin');
    $seedPassword = Env::get('ADMIN_PASS', 'admin123');

    $stmt = $pdo->prepare('INSERT INTO users (login, email, password_hash, role, privacy_accepted_at) VALUES (:login, :email, :password_hash, :role, NOW())');
    $stmt->execute([
        'login' => $seedLogin,
        'email' => $seedLogin . '@hotel.localhost',
        'password_hash' => password_hash((string) $seedPassword, PASSWORD_DEFAULT),
        'role' => 'admin',
    ]);
}

$login = '';

if (is_post()) {
    if (!verify_csrf($_POST['_token'] ?? null)) {
        flash('error', 'Неверный токен безопасности.');
        header('Location: /admin/login.php');
        exit;
    }

    $login = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        flash('error', 'Введите логин и пароль.');
    } elseif ($auth->attemptStaff($login, $password)) {
        header('Location: /admin/index.php');
        exit;
    } else {
        flash('error', 'Неверный логин или пароль.');
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход · Админ</title>
    <link rel="stylesheet" href="/css/admin.css">
  </head>
  <body>
    <main class="admin-login-shell">
      <?php if ($message = flash('error')): ?>
        <div class="flash flash-error"><?= e($message) ?></div>
      <?php endif; ?>

      <section class="admin-card admin-login-card">
        <span class="admin-kicker">Hotel Operations</span>
        <h2>Вход для сотрудников</h2>
        <p class="muted">Единая панель бронирований, заселений, услуг и номерного фонда.</p>
        <form class="admin-form" method="post" action="/admin/login.php">
          <?= csrf_field() ?>
          <div class="form-grid">
            <div class="form-field">
              <label for="login">Логин</label>
              <input id="login" name="login" type="text" value="<?= e($login) ?>" required>
            </div>
            <div class="form-field">
              <label for="password">Пароль</label>
              <input id="password" name="password" type="password" required>
            </div>
          </div>
          <div class="form-actions">
            <button class="button" type="submit">Войти</button>
            <a class="button button-secondary" href="/">На сайт</a>
          </div>
          <div class="demo-box"><strong>Демо-администратор</strong><br>hotel_admin / HotelDemo2024<br><small>manager, reception, housekeeping / StaffDemo2024</small></div>
        </form>
      </section>
    </main>
  </body>
</html>
