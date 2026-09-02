<?php

declare(strict_types=1);
require __DIR__.'/../../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;

$auth = new Auth($pdo);
$auth->requirePermission('reports.view');
if (is_post()) {
    $auth->requirePermission('catalog.manage');
    if (!verify_csrf($_POST['_token'] ?? null)) {
        flash('error', 'Сессия формы истекла.');
        redirect('/admin/reviews.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    if ($id > 0 && in_array($status, ['pending','published','rejected'], true)) {
        $pdo->prepare('UPDATE reviews SET status=:status WHERE id=:id')->execute(['status' => $status,'id' => $id]);
        audit_log($pdo, 'review.moderated', 'review', (string)$id, ['status' => $status]);
        flash('success', 'Статус отзыва обновлён.');
    }redirect('/admin/reviews.php');
}
$reviews = $pdo->query('SELECT reviews.*,b.code,rt.name room_type_name,CONCAT(c.last_name," ",c.first_name) client_name FROM reviews INNER JOIN bookings b ON b.id=reviews.booking_id INNER JOIN room_types rt ON rt.id=b.room_type_id INNER JOIN clients c ON c.id=reviews.client_id ORDER BY reviews.created_at DESC')->fetchAll();
$title = 'Отзывы';
require __DIR__.'/../../templates/admin/header.php';
?>
<section class="admin-card"><h2>Модерация отзывов</h2><div class="table-scroll"><table class="admin-table"><thead><tr><th>Гость</th><th>Проживание</th><th>Оценка</th><th>Отзыв</th><th>Статус</th><th></th></tr></thead><tbody><?php foreach ($reviews as $row):?><tr><td><?=e((string)$row['client_name'])?></td><td><?=e((string)$row['code'])?><br><small><?=e((string)$row['room_type_name'])?></small></td><td><?=str_repeat('★', (int)$row['rating'])?></td><td><?=e((string)$row['comment'])?></td><td><span class="status status-<?=e((string)$row['status'])?>"><?=e((string)$row['status'])?></span></td><td><form class="filter-row" method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=e((string)$row['id'])?>"><select name="status"><option value="pending" <?=$row['status'] === 'pending' ? 'selected' : ''?>>На модерации</option><option value="published" <?=$row['status'] === 'published' ? 'selected' : ''?>>Опубликовать</option><option value="rejected" <?=$row['status'] === 'rejected' ? 'selected' : ''?>>Отклонить</option></select><button class="button">OK</button></form></td></tr><?php endforeach;?></tbody></table></div></section>
<?php require __DIR__.'/../../templates/admin/footer.php';?>
