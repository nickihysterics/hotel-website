<?php

declare(strict_types=1);
require __DIR__.'/../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;

$auth = new Auth($pdo);
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$tokenHash = hash('sha256', $token);
$stmt = $pdo->prepare('SELECT id,user_id FROM password_reset_tokens WHERE token_hash=:hash AND used_at IS NULL AND expires_at>NOW()');
$stmt->execute(['hash' => $tokenHash]);
$reset = $stmt->fetch();
$error = null;
if (is_post() && $reset) {
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');
    if (!verify_csrf($_POST['_token'] ?? null)) {
        $error = 'Сессия формы истекла.';
    } elseif (strlen($password) < 8) {
        $error = 'Пароль должен содержать минимум 8 символов.';
    } elseif ($password !== $confirmation) {
        $error = 'Пароли не совпадают.';
    } else {
        // Новый хеш и погашение токена фиксируются одной транзакцией.
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET password_hash=:hash WHERE id=:id')->execute(['hash' => password_hash($password, PASSWORD_DEFAULT),'id' => $reset['user_id']]);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE id=:id')->execute(['id' => $reset['id']]);
            audit_log($pdo, 'password_reset.completed', 'user', (string)$reset['user_id']);
            $pdo->commit();
            flash('success', 'Пароль изменён. Теперь можно войти.');
            redirect('/login.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }throw $exception;
        }
    }
}
$title = 'Новый пароль';
$active = 'login';
require __DIR__.'/../templates/site/header.php';
?>
<main class="product-page auth-page"><section class="product-container auth-shell auth-shell-small"><div class="auth-copy"><span class="eyebrow">Новый пароль</span><h1>Защитите свой аккаунт</h1><p>Используйте уникальную комбинацию длиной не менее 8 символов.</p></div><div class="product-card product-form auth-form"><?php if (!$reset):?><div class="section-heading compact"><h2>Ссылка недействительна</h2></div><p>Она уже использована или срок действия истёк.</p><a class="product-button" href="/forgot-password.php">Запросить новую</a><?php else:?><form class="product-form" method="post"><?=csrf_field()?><input type="hidden" name="token" value="<?=e($token)?>"><div class="section-heading compact"><h2>Задайте пароль</h2></div><?php if ($error):?><div class="form-alert"><?=e($error)?></div><?php endif;?><label>Новый пароль<input type="password" name="password" minlength="8" required></label><label>Повторите пароль<input type="password" name="password_confirmation" minlength="8" required></label><button class="product-button">Сохранить пароль</button></form><?php endif;?></div></section></main>
<?php require __DIR__.'/../templates/site/footer.php';?>
