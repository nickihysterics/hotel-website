<?php

declare(strict_types=1);
require __DIR__.'/../../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;

$auth = new Auth($pdo);
$auth->requireStaff();
// Управление ролями оставлено только администратору, даже если URL открыт напрямую.
if ($auth->role() !== 'admin') {
    http_response_code(403);
    exit('Недостаточно прав.');
}
$id = max(0, (int)($_GET['edit'] ?? $_POST['id'] ?? 0));
$values = ['login' => '','email' => '','role' => 'reception','active' => 1,'associate_id' => '','client_id' => ''];
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT id,login,email,role,active,associate_id,client_id FROM users WHERE id=:id');
    $stmt->execute(['id' => $id]);
    $values = array_merge($values, $stmt->fetch() ?: []);
}
$errors = [];
if (is_post()) {
    if (!verify_csrf($_POST['_token'] ?? null)) {
        flash('error', 'Сессия формы истекла.');
        redirect('/admin/users.php');
    }
    $action = (string)($_POST['action'] ?? 'save');
    if ($action === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);
        // Текущую сессию нельзя лишить собственной учётной записи.
        if ($deleteId === $auth->id()) {
            flash('error', 'Нельзя удалить текущую учётную запись.');
        } else {
            try {
                $pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $deleteId]);
                audit_log($pdo, 'user.deleted', 'user', (string)$deleteId);
                flash('success', 'Учётная запись удалена.');
            } catch (PDOException) {
                flash('error', 'Не удалось удалить пользователя.');
            }
        }redirect('/admin/users.php');
    }
    $values = array_merge($values, $_POST);
    $login = trim((string)$values['login']);
    $email = strtolower(trim((string)$values['email']));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)$values['role'];
    if ($login === '') {
        $errors['login'] = 'Укажите логин.';
    }if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Укажите корректный e-mail.';
    }if ($id === 0 && strlen($password) < 8) {
        $errors['password'] = 'Минимум 8 символов.';
    }if (!in_array($role, ['guest','admin','manager','reception','housekeeping','analyst'], true)) {
        $errors['role'] = 'Некорректная роль.';
    }
    if ($errors === []) {
        try {
            $params = ['login' => $login,'email' => $email,'role' => $role,'active' => (int)$values['active'],'associate_id' => (int)$values['associate_id'] ?: null,'client_id' => (int)$values['client_id'] ?: null];
            if ($id > 0) {
                $params['id'] = $id;
                $sql = 'UPDATE users SET login=:login,email=:email,role=:role,active=:active,associate_id=:associate_id,client_id=:client_id';
                // Пустое поле при редактировании сохраняет действующий хеш пароля.
                if ($password !== '') {
                    $sql .= ',password_hash=:password_hash';
                    $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }$sql .= ' WHERE id=:id';
            } else {
                $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                $sql = 'INSERT INTO users(login,email,password_hash,role,active,associate_id,client_id,privacy_accepted_at) VALUES(:login,:email,:password_hash,:role,:active,:associate_id,:client_id,NOW())';
            }$pdo->prepare($sql)->execute($params);
            $savedId = $id ?: (int)$pdo->lastInsertId();
            audit_log($pdo, 'user.saved', 'user', (string)$savedId, ['role' => $role,'active' => $params['active']]);
            flash('success', 'Учётная запись сохранена.');
            redirect('/admin/users.php');
        } catch (PDOException) {
            $errors['_form'] = 'Логин, e-mail или связанный профиль уже используется.';
        }
    }
}
$users = $pdo->query('SELECT u.*,CONCAT(a.last_name," ",a.first_name) associate_name,CONCAT(c.last_name," ",c.first_name) client_name FROM users u LEFT JOIN associates a ON a.id=u.associate_id LEFT JOIN clients c ON c.id=u.client_id ORDER BY u.role,u.login')->fetchAll();
$associates = $pdo->query('SELECT id,CONCAT(last_name," ",first_name) label FROM associates WHERE active=1 ORDER BY last_name')->fetchAll();
$clients = $pdo->query('SELECT id,CONCAT(last_name," ",first_name) label FROM clients ORDER BY last_name')->fetchAll();
$roles = ['guest' => 'Гость','admin' => 'Администратор','manager' => 'Менеджер','reception' => 'Ресепшен','housekeeping' => 'Хаускипинг','analyst' => 'Аналитик'];
$title = 'Пользователи и роли';
require __DIR__.'/../../templates/admin/header.php';
?>
<section class="admin-card"><h2>Учётные записи</h2><div class="table-scroll"><table class="admin-table"><thead><tr><th>Логин</th><th>E-mail</th><th>Роль</th><th>Профиль</th><th>Активность</th><th></th></tr></thead><tbody><?php foreach ($users as $row):?><tr><td><strong><?=e((string)$row['login'])?></strong><br><small>вход: <?=e((string)($row['last_login_at'] ?: 'ещё не входил'))?></small></td><td><?=e((string)$row['email'])?></td><td><?=e($roles[$row['role']] ?? (string)$row['role'])?></td><td><?=e((string)($row['associate_name'] ?: $row['client_name'] ?: '—'))?></td><td><span class="status <?=$row['active'] ? 'status-completed' : 'status-cancelled'?>"><?=$row['active'] ? 'Активен' : 'Отключён'?></span></td><td class="table-actions"><a class="button button-link" href="?edit=<?=e((string)$row['id'])?>">Изменить</a><form method="post" data-confirm="Удалить учётную запись?"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=e((string)$row['id'])?>"><button class="button button-secondary">Удалить</button></form></td></tr><?php endforeach;?></tbody></table></div></section>
<section class="admin-card"><h2><?=$id ? 'Редактирование' : 'Новая учётная запись'?></h2><?php if (isset($errors['_form'])):?><div class="flash flash-error"><?=e($errors['_form'])?></div><?php endif;?><form class="admin-form" method="post"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e((string)$id)?>"><div class="form-grid"><label class="form-field">Логин<input name="login" value="<?=e((string)$values['login'])?>" required><?php if (isset($errors['login'])):?><small><?=e($errors['login'])?></small><?php endif;?></label><label class="form-field">E-mail<input type="email" name="email" value="<?=e((string)$values['email'])?>" required><?php if (isset($errors['email'])):?><small><?=e($errors['email'])?></small><?php endif;?></label><label class="form-field"><?=$id ? 'Новый пароль (необязательно)' : 'Пароль'?><input type="password" name="password" <?=$id ? '' : 'required'?>><?php if (isset($errors['password'])):?><small><?=e($errors['password'])?></small><?php endif;?></label><label class="form-field">Роль<select name="role"><?php foreach ($roles as $key => $label):?><option value="<?=e($key)?>" <?=$values['role'] === $key ? 'selected' : ''?>><?=e($label)?></option><?php endforeach;?></select></label><label class="form-field">Сотрудник<select name="associate_id"><option value="">Не привязан</option><?php foreach ($associates as $row):?><option value="<?=e((string)$row['id'])?>" <?=(int)$values['associate_id'] === (int)$row['id'] ? 'selected' : ''?>><?=e((string)$row['label'])?></option><?php endforeach;?></select></label><label class="form-field">Клиент<select name="client_id"><option value="">Не привязан</option><?php foreach ($clients as $row):?><option value="<?=e((string)$row['id'])?>" <?=(int)$values['client_id'] === (int)$row['id'] ? 'selected' : ''?>><?=e((string)$row['label'])?></option><?php endforeach;?></select></label><label class="form-field">Активен<select name="active"><option value="1" <?=(int)$values['active'] === 1 ? 'selected' : ''?>>Да</option><option value="0" <?=(int)$values['active'] === 0 ? 'selected' : ''?>>Нет</option></select></label></div><div class="form-actions"><button class="button">Сохранить</button><?php if ($id):?><a class="button button-secondary" href="/admin/users.php">Отмена</a><?php endif;?></div></form></section>
<?php require __DIR__.'/../../templates/admin/footer.php';?>
