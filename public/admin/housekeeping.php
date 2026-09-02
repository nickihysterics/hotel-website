<?php

declare(strict_types=1);
require __DIR__ . '/../../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;

$auth = new Auth($pdo);
if (!$auth->can('housekeeping.manage') && !$auth->can('operations.manage')) {
    $auth->requirePermission('housekeeping.manage');
}
if (is_post()) {
    if (!verify_csrf($_POST['_token'] ?? null)) {
        flash('error', 'Сессия формы истекла.');
        redirect('/admin/housekeeping.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    if ($id > 0 && in_array($status, ['pending','in_progress','completed','cancelled'], true)) {
        // Задача и эксплуатационный статус комнаты должны оставаться согласованными.
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT room_id FROM housekeeping_tasks WHERE id=:id');
            $stmt->execute(['id' => $id]);
            $roomId = (int)$stmt->fetchColumn();
            $pdo->prepare("UPDATE housekeeping_tasks SET status=:status,assigned_to=COALESCE(assigned_to,:staff),completed_at=IF(:done='completed',NOW(),NULL) WHERE id=:id")->execute(['status' => $status,'staff' => $auth->user()['associate_id'] ?: null,'done' => $status,'id' => $id]);
            if ($roomId > 0) {
                // Только завершённая уборка возвращает номер в доступный фонд.
                $roomStatus = $status === 'completed' ? 'available' : ($status === 'in_progress' ? 'cleaning' : 'dirty');
                $pdo->prepare('UPDATE rooms SET status=:status WHERE id=:id')->execute(['status' => $roomStatus,'id' => $roomId]);
            }
            audit_log($pdo, 'housekeeping.status_changed', 'housekeeping_task', (string)$id, ['status' => $status]);
            $pdo->commit();
            flash('success', 'Задача обновлена.');
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }flash('error', 'Не удалось обновить задачу.');
        }
    } redirect('/admin/housekeeping.php');
}
$tasks = $pdo->query('SELECT ht.*,r.number room_number,rt.name room_type_name,CONCAT(a.last_name," ",a.first_name) staff_name,b.code FROM housekeeping_tasks ht INNER JOIN rooms r ON r.id=ht.room_id INNER JOIN room_types rt ON rt.id=r.room_type_id LEFT JOIN associates a ON a.id=ht.assigned_to LEFT JOIN bookings b ON b.id=ht.booking_id ORDER BY FIELD(ht.status,"in_progress","pending","completed","cancelled"),FIELD(ht.priority,"urgent","high","normal","low"),ht.due_at')->fetchAll();
$title = 'Уборка и готовность';
require __DIR__.'/../../templates/admin/header.php';
?>
<section class="admin-card"><h2>Доска хаускипинга</h2><p class="muted">Завершение уборки автоматически возвращает номер в статус «Готов».</p><div class="table-scroll"><table class="admin-table"><thead><tr><th>Номер</th><th>Задача</th><th>Приоритет</th><th>Исполнитель</th><th>Статус</th><th>Действие</th></tr></thead><tbody><?php foreach ($tasks as $row):?><tr><td><strong>№ <?=e((string)$row['room_number'])?></strong><br><small><?=e((string)$row['room_type_name'])?></small></td><td><?=e((string)$row['task_type'])?><br><small><?=e((string)($row['notes'] ?: ''))?></small></td><td><?=e((string)$row['priority'])?></td><td><?=e((string)($row['staff_name'] ?: 'Не назначен'))?></td><td><span class="status status-<?=e((string)$row['status'])?>"><?=e(service_status_label((string)$row['status']))?></span></td><td><form class="filter-row" method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=e((string)$row['id'])?>"><select name="status"><?php foreach (['pending' => 'Ожидает','in_progress' => 'В работе','completed' => 'Готово','cancelled' => 'Отменено'] as $key => $label):?><option value="<?=e($key)?>" <?=$row['status'] === $key ? 'selected' : ''?>><?=e($label)?></option><?php endforeach;?></select><button class="button">OK</button></form></td></tr><?php endforeach;?></tbody></table></div></section>
<?php require __DIR__.'/../../templates/admin/footer.php';?>
