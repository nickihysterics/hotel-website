<?php

declare(strict_types=1);
require __DIR__.'/../../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;

$auth = new Auth($pdo);
$auth->requireStaff();
if ($auth->role() !== 'admin') {
    http_response_code(403);
    exit('Недостаточно прав.');
}
$query = trim((string)($_GET['q'] ?? ''));
$sql = 'SELECT audit_logs.*,users.login FROM audit_logs LEFT JOIN users ON users.id=audit_logs.user_id';
$params = [];
if ($query !== '') {
    $sql .= ' WHERE audit_logs.action LIKE :q OR audit_logs.entity_type LIKE :q OR users.login LIKE :q';
    $params['q'] = '%'.$query.'%';
}$sql .= ' ORDER BY audit_logs.id DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
$title = 'Журнал действий';
require __DIR__.'/../../templates/admin/header.php';
?>
<section class="admin-card"><h2>Аудит безопасности и операций</h2><form class="filter-row" method="get"><input name="q" value="<?=e($query)?>" placeholder="Действие, объект или пользователь"><button class="button">Найти</button></form><div class="table-scroll"><table class="admin-table"><thead><tr><th>Время</th><th>Пользователь</th><th>Действие</th><th>Объект</th><th>Данные</th></tr></thead><tbody><?php foreach ($logs as $row):?><tr><td><?=e((string)$row['created_at'])?></td><td><?=e((string)($row['login'] ?: 'система'))?></td><td><strong><?=e((string)$row['action'])?></strong></td><td><?=e((string)$row['entity_type'])?> #<?=e((string)$row['entity_id'])?></td><td><small><?=e((string)($row['payload_json'] ?: '—'))?></small></td></tr><?php endforeach;?></tbody></table></div></section>
<?php require __DIR__.'/../../templates/admin/footer.php';?>
