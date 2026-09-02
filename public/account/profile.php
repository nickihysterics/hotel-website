<?php

declare(strict_types=1);
require __DIR__.'/../../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;

$auth = new Auth($pdo);
$auth->requireGuest();
$clientId = (int)$auth->clientId();
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id=:id');
$stmt->execute(['id' => $clientId]);
$profile = $stmt->fetch();
$error = null;
if (is_post()) {
    if (!verify_csrf($_POST['_token'] ?? null)) {
        $error = 'Сессия формы истекла.';
    } else {
        $values = ['last_name' => trim((string)($_POST['last_name'] ?? '')),'first_name' => trim((string)($_POST['first_name'] ?? '')),'middle_name' => trim((string)($_POST['middle_name'] ?? '')) ?: null,'birth_date' => (string)($_POST['birth_date'] ?? '') ?: null,'phone' => trim((string)($_POST['phone'] ?? '')),'preferences' => trim((string)($_POST['preferences'] ?? '')) ?: null,'marketing_consent' => isset($_POST['marketing_consent']) ? 1 : 0,'id' => $clientId];
        if ($values['last_name'] === '' || $values['first_name'] === '' || !preg_match('/^[+0-9()\-\s]{10,30}$/', $values['phone'])) {
            $error = 'Проверьте имя и телефон.';
        } else {
            try {
                $pdo->prepare('UPDATE clients SET last_name=:last_name,first_name=:first_name,middle_name=:middle_name,birth_date=:birth_date,phone=:phone,preferences=:preferences,marketing_consent=:marketing_consent WHERE id=:id')->execute($values);
                audit_log($pdo, 'guest.profile_updated', 'client', (string)$clientId);
                flash('success', 'Профиль обновлён.');
                redirect('/account/profile.php');
            } catch (PDOException) {
                $error = 'Этот телефон уже используется.';
            }
        }
    }
}
$stmt->execute(['id' => $clientId]);
$profile = $stmt->fetch();
$title = 'Мой профиль';
$active = 'account';
require __DIR__.'/../../templates/site/header.php';
?>
<main class="product-page"><section class="product-container checkout-heading"><a class="back-link" href="/account/">← Мои поездки</a><span class="eyebrow">Личный кабинет</span><h1>Профиль гостя</h1></section><section class="product-container"><form class="product-card product-form" method="post"><?=csrf_field()?><?php if ($error):?><div class="form-alert"><?=e($error)?></div><?php endif;?><div class="form-row three"><label>Фамилия<input name="last_name" value="<?=e((string)$profile['last_name'])?>" required></label><label>Имя<input name="first_name" value="<?=e((string)$profile['first_name'])?>" required></label><label>Отчество<input name="middle_name" value="<?=e((string)$profile['middle_name'])?>"></label></div><div class="form-row two"><label>E-mail<input value="<?=e((string)$profile['email'])?>" disabled><small>E-mail используется для входа и меняется через поддержку.</small></label><label>Телефон<input name="phone" value="<?=e((string)$profile['phone'])?>" required></label></div><label>Дата рождения<input type="date" name="birth_date" value="<?=e((string)$profile['birth_date'])?>"></label><label>Предпочтения<textarea name="preferences" placeholder="Подушка, этаж, особенности питания..."><?=e((string)$profile['preferences'])?></textarea></label><label class="check-field"><input type="checkbox" name="marketing_consent" value="1" <?=(int)$profile['marketing_consent'] === 1 ? 'checked' : ''?>><span>Получать специальные предложения</span></label><button class="product-button">Сохранить</button></form></section></main>
<?php require __DIR__.'/../../templates/site/footer.php';?>
