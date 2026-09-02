<?php

declare(strict_types=1);
require __DIR__ . '/../../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;

$auth = new Auth($pdo);
// Менеджер каталога и ресепшен работают с одной очередью, но через разные разрешения.
if (!$auth->can('services.orders') && !$auth->can('services.manage')) {
    $auth->requirePermission('services.orders');
}
if (is_post()) {
    if (!verify_csrf($_POST['_token'] ?? null)) {
        flash('error', 'Сессия формы истекла.');
        redirect('/admin/service_orders.php');
    }
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    // Закрытый набор не позволяет записать произвольный статус из POST.
    if ($id > 0 && in_array($status, ['new','confirmed','in_progress','completed','cancelled'], true)) {
        $pdo->prepare('UPDATE booking_services SET status=:status WHERE id=:id')->execute(['status' => $status,'id' => $id]);
        audit_log($pdo, 'service_order.status_changed', 'booking_service', (string) $id, ['status' => $status]);
        flash('success', 'Статус заказа обновлён.');
    }
    redirect('/admin/service_orders.php');
}
$status = (string)($_GET['status'] ?? '');
$sql = 'SELECT bs.*,s.name service_name,b.code,CONCAT(c.last_name," ",c.first_name) client_name,r.number room_number FROM booking_services bs INNER JOIN services s ON s.id=bs.service_id INNER JOIN bookings b ON b.id=bs.booking_id INNER JOIN clients c ON c.id=b.client_id LEFT JOIN rooms r ON r.id=b.room_id';
$params = [];
if ($status !== '') {
    $sql .= ' WHERE bs.status=:status';
    $params['status'] = $status;
} $sql .= ' ORDER BY FIELD(bs.status,"new","confirmed","in_progress","completed","cancelled"),bs.scheduled_for,bs.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
$title = 'Заказы услуг';
require __DIR__.'/../../templates/admin/header.php';
?>
<section class="admin-card"><h2>Очередь услуг</h2><form class="filter-row" method="get"><select name="status"><option value="">Все статусы</option><?php foreach (['new','confirmed','in_progress','completed','cancelled'] as $item):?><option value="<?=e($item)?>" <?=$status === $item ? 'selected' : ''?>><?=e(service_status_label($item))?></option><?php endforeach;?></select><button class="button">Фильтр</button></form><div class="table-scroll"><table class="admin-table"><thead><tr><th>Заказ</th><th>Гость / номер</th><th>Когда</th><th>Сумма</th><th>Статус</th><th>Обновить</th></tr></thead><tbody><?php foreach ($orders as $row):?><tr><td><strong><?=e((string)$row['service_name'])?></strong><br><small><?=e((string)$row['code'])?> · ×<?=e((string)$row['quantity'])?></small></td><td><?=e((string)$row['client_name'])?><br><small><?=$row['room_number'] ? '№ '.e((string)$row['room_number']) : 'номер не назначен'?></small></td><td><?=e((string)($row['scheduled_for'] ?: 'как можно скорее'))?><br><small><?=e((string)($row['guest_note'] ?: ''))?></small></td><td><?=e(money($row['total']))?></td><td><span class="status status-<?=e((string)$row['status'])?>"><?=e(service_status_label((string)$row['status']))?></span></td><td><form class="filter-row" method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=e((string)$row['id'])?>"><select name="status"><?php foreach (['new','confirmed','in_progress','completed','cancelled'] as $item):?><option value="<?=e($item)?>" <?=$row['status'] === $item ? 'selected' : ''?>><?=e(service_status_label($item))?></option><?php endforeach;?></select><button class="button">OK</button></form></td></tr><?php endforeach;?></tbody></table></div></section>
<?php require __DIR__.'/../../templates/admin/footer.php';?>
