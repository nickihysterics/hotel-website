<?php

declare(strict_types=1);
require __DIR__.'/../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;

$auth = new Auth($pdo);
$email = '';
$demoResetUrl = null;
if (is_post()) {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!verify_csrf($_POST['_token'] ?? null)) {
        flash('error', 'Сессия формы истекла.');
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email=:email AND role='guest' AND active=1");
        $stmt->execute(['email' => $email]);
        $userId = (int)$stmt->fetchColumn();
        if ($userId > 0) {
            // В БД хранится только хеш одноразового токена; исходное значение нужно лишь для ссылки.
            $token = bin2hex(random_bytes(24));
            $pdo->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(:user_id,:hash,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')->execute(['user_id' => $userId,'hash' => hash('sha256', $token)]);
            $demoResetUrl = '/reset-password.php?token='.$token;
            audit_log($pdo, 'password_reset.requested', 'user', (string)$userId);
        } else {
            // Одинаковый внешний ответ не раскрывает, зарегистрирован ли указанный email.
            flash('success', 'Если аккаунт существует, инструкция подготовлена.');
        }
    } else {
        flash('error', 'Укажите корректный e-mail.');
    }
}
$title = 'Восстановление пароля';
$active = 'login';
require __DIR__.'/../templates/site/header.php';
?>
<main class="product-page auth-page"><section class="product-container auth-shell auth-shell-small"><div class="auth-copy"><span class="eyebrow">Безопасность аккаунта</span><h1>Вернём доступ за пару минут</h1><p>Ссылка одноразовая и действует 30 минут.</p></div><form class="product-card product-form auth-form" method="post"><?=csrf_field()?><div class="section-heading compact"><span class="eyebrow">Восстановление</span><h2>Забыли пароль?</h2></div><label>E-mail аккаунта<input type="email" name="email" value="<?=e($email)?>" required></label><button class="product-button">Получить ссылку</button><?php if ($demoResetUrl):?><div class="demo-credentials"><strong>Демонстрационная доставка письма</strong><p>В production эта ссылка отправляется по e-mail. Здесь она доступна сразу.</p><a class="product-button small" href="<?=e($demoResetUrl)?>">Открыть письмо</a></div><?php endif;?><p class="form-footnote"><a href="/login.php">Вернуться ко входу</a></p></form></section></main>
<?php require __DIR__.'/../templates/site/footer.php';?>
