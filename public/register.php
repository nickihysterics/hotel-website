<?php

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;

$auth = new Auth($pdo);
if ($auth->isGuest()) {
    redirect('/account/');
}

$title = 'Регистрация';
$active = 'login';
$errors = [];
$values = [];

if (is_post()) {
    $values = [
        'last_name' => trim((string) ($_POST['last_name'] ?? '')),
        'first_name' => trim((string) ($_POST['first_name'] ?? '')),
        'middle_name' => trim((string) ($_POST['middle_name'] ?? '')),
        'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'birth_date' => trim((string) ($_POST['birth_date'] ?? '')),
        'marketing_consent' => isset($_POST['marketing_consent']) ? 1 : 0,
    ];
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!verify_csrf($_POST['_token'] ?? null)) {
        $errors['_form'] = 'Сессия формы истекла. Обновите страницу.';
    }
    if ($values['last_name'] === '') {
        $errors['last_name'] = 'Укажите фамилию.';
    }
    if ($values['first_name'] === '') {
        $errors['first_name'] = 'Укажите имя.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Укажите корректный email.';
    }
    if (!preg_match('/^[+0-9()\-\s]{10,30}$/', $values['phone'])) {
        $errors['phone'] = 'Укажите корректный номер телефона.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Пароль должен содержать не менее 8 символов.';
    }
    if ($password !== $passwordConfirmation) {
        $errors['password_confirmation'] = 'Пароли не совпадают.';
    }
    if (!isset($_POST['privacy_consent'])) {
        $errors['privacy_consent'] = 'Для регистрации необходимо согласие на обработку данных.';
    }

    if ($errors === []) {
        // Карточка клиента и учётная запись создаются вместе либо не создаются вовсе.
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO clients ('
                . ' last_name, first_name, middle_name, birth_date, email, phone, marketing_consent'
                . ' ) VALUES ('
                . ' :last_name, :first_name, :middle_name, :birth_date, :email, :phone, :marketing_consent'
                . ' )'
            );
            $stmt->execute([
                'last_name' => $values['last_name'],
                'first_name' => $values['first_name'],
                'middle_name' => $values['middle_name'] ?: null,
                'birth_date' => $values['birth_date'] ?: null,
                'email' => $values['email'],
                'phone' => $values['phone'],
                'marketing_consent' => $values['marketing_consent'],
            ]);
            $clientId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO users ('
                . ' client_id, login, email, password_hash, role, active, privacy_accepted_at'
                . ' ) VALUES (:client_id, :login, :email, :password_hash, :role, 1, NOW())'
            );
            $stmt->execute([
                'client_id' => $clientId,
                'login' => $values['email'],
                'email' => $values['email'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'guest',
            ]);
            audit_log($pdo, 'guest.registered', 'client', (string) $clientId);
            $pdo->commit();

            $auth->attemptGuest($values['email'], $password);
            flash('success', 'Аккаунт создан. Добро пожаловать!');
            redirect('/account/');
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ((string) $exception->getCode() === '23000') {
                $errors['_form'] = 'Аккаунт с таким email или телефоном уже существует.';
            } else {
                throw $exception;
            }
        }
    }
}

require __DIR__ . '/../templates/site/header.php';
?>
<main class="product-page auth-page">
  <section class="product-container auth-shell">
    <div class="auth-copy">
      <span class="eyebrow">Личный кабинет гостя</span>
      <h1>Одна регистрация — вся поездка под контролем</h1>
      <p>Бронируйте номера, добавляйте услуги, отслеживайте оплату и храните историю проживаний.</p>
      <ul class="feature-checks">
        <li>Бронирование без звонка</li>
        <li>Услуги до заезда и во время проживания</li>
        <li>Все счета и статусы в одном месте</li>
      </ul>
    </div>
    <form class="product-card product-form auth-form" method="post" novalidate>
      <?= csrf_field() ?>
      <div class="section-heading compact">
        <span class="eyebrow">Новый аккаунт</span>
        <h2>Создать профиль</h2>
      </div>
      <?php if (isset($errors['_form'])): ?><div class="form-alert"><?= e($errors['_form']) ?></div><?php endif; ?>
      <div class="form-row three">
        <label>Фамилия<input name="last_name" value="<?= e((string) ($values['last_name'] ?? '')) ?>" autocomplete="family-name" required><small><?= e($errors['last_name'] ?? '') ?></small></label>
        <label>Имя<input name="first_name" value="<?= e((string) ($values['first_name'] ?? '')) ?>" autocomplete="given-name" required><small><?= e($errors['first_name'] ?? '') ?></small></label>
        <label>Отчество<input name="middle_name" value="<?= e((string) ($values['middle_name'] ?? '')) ?>" autocomplete="additional-name"></label>
      </div>
      <div class="form-row two">
        <label>Email<input type="email" name="email" value="<?= e((string) ($values['email'] ?? '')) ?>" autocomplete="email" required><small><?= e($errors['email'] ?? '') ?></small></label>
        <label>Телефон<input name="phone" value="<?= e((string) ($values['phone'] ?? '')) ?>" autocomplete="tel" placeholder="+7 900 000-00-00" required><small><?= e($errors['phone'] ?? '') ?></small></label>
      </div>
      <label>Дата рождения<input type="date" name="birth_date" value="<?= e((string) ($values['birth_date'] ?? '')) ?>" autocomplete="bday"></label>
      <div class="form-row two">
        <label>Пароль<input type="password" name="password" autocomplete="new-password" required><small><?= e($errors['password'] ?? '') ?></small></label>
        <label>Повторите пароль<input type="password" name="password_confirmation" autocomplete="new-password" required><small><?= e($errors['password_confirmation'] ?? '') ?></small></label>
      </div>
      <label class="check-field"><input type="checkbox" name="privacy_consent" value="1" required><span>Согласен на обработку персональных данных для бронирования и обслуживания.</span></label>
      <?php if (isset($errors['privacy_consent'])): ?><small class="field-error"><?= e($errors['privacy_consent']) ?></small><?php endif; ?>
      <label class="check-field"><input type="checkbox" name="marketing_consent" value="1"><span>Хочу получать специальные предложения.</span></label>
      <button class="product-button" type="submit">Создать аккаунт</button>
      <p class="form-footnote">Уже зарегистрированы? <a href="/login.php">Войти</a></p>
    </form>
  </section>
</main>
<?php require __DIR__ . '/../templates/site/footer.php'; ?>
