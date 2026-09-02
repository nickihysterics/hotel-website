<?php

declare(strict_types=1);
require __DIR__.'/../../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;

$auth = new Auth($pdo);
$auth->requirePermission('dashboard.view');
$metrics = [
    ['label' => 'Заезды сегодня','value' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE date_in=CURDATE() AND status IN ('pending','confirmed')")->fetchColumn(),'hint' => 'ожидают ресепшен'],
    ['label' => 'Сейчас проживают','value' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='checked_in'")->fetchColumn(),'hint' => 'активных размещений'],
    ['label' => 'Выезды сегодня','value' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE date_out=CURDATE() AND status='checked_in'")->fetchColumn(),'hint' => 'номеров к выезду'],
    ['label' => 'Новые услуги','value' => (int)$pdo->query("SELECT COUNT(*) FROM booking_services WHERE status IN ('new','confirmed')")->fetchColumn(),'hint' => 'в очереди исполнения'],
    ['label' => 'Нужна уборка','value' => (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE status IN ('dirty','cleaning')")->fetchColumn(),'hint' => 'по номерному фонду'],
    ['label' => 'Выручка MTD','value' => money((float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND paid_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn()),'hint' => 'фактически оплачено'],
];
$recent = $pdo->query('SELECT b.id,b.code,b.status,b.date_in,b.date_out,b.total,CONCAT(c.last_name," ",c.first_name) client_name,rt.name room_type_name FROM bookings b INNER JOIN clients c ON c.id=b.client_id INNER JOIN room_types rt ON rt.id=b.room_type_id ORDER BY b.created_at DESC LIMIT 8')->fetchAll();
$rooms = $pdo->query('SELECT status,COUNT(*) total FROM rooms WHERE active=1 GROUP BY status')->fetchAll();
$title = 'Обзор';
require __DIR__.'/../../templates/admin/header.php';
?>
<section class="analytics-grid"><?php foreach ($metrics as $metric):?><article class="stat-card"><p class="stat-label"><?=e((string)$metric['label'])?></p><p class="stat-value"><?=e((string)$metric['value'])?></p><p class="stat-hint"><?=e((string)$metric['hint'])?></p></article><?php endforeach;?></section>
<section class="split-grid"><article class="admin-card"><h2>Последние бронирования</h2><div class="quick-list"><?php foreach ($recent as $row):?><a class="quick-row" href="/admin/booking-edit.php?id=<?=e((string)$row['id'])?>"><span><strong><?=e((string)$row['code'])?></strong><br><small><?=e((string)$row['client_name'])?> · <?=e((string)$row['room_type_name'])?></small></span><span class="status status-<?=e((string)$row['status'])?>"><?=e(booking_status_label((string)$row['status']))?></span></a><?php endforeach;?></div></article><article class="admin-card"><h2>Состояние фонда</h2><div class="quick-list"><?php foreach ($rooms as $row):?><div class="quick-row"><span><?=e(room_status_label((string)$row['status']))?></span><strong><?=e((string)$row['total'])?></strong></div><?php endforeach;?></div><div class="form-actions"><a class="button" href="/admin/operations.php">Операционный день</a><a class="button button-link" href="/admin/housekeeping.php">Уборка</a></div></article></section>
<?php require __DIR__.'/../../templates/admin/footer.php';?>
