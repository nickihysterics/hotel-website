<?php

declare(strict_types=1);
require __DIR__.'/../../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;

$auth = new Auth($pdo);
$auth->requirePermission('reports.view');
// KPI текущего месяца считаются напрямую по согласованным статусам броней и услуг.
$inventory = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE active=1 AND status!='maintenance'")->fetchColumn();
$occupied = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('confirmed','checked_in') AND date_in<=CURDATE() AND date_out>CURDATE()")->fetchColumn();
$occupancy = $inventory > 0 ? round($occupied / $inventory * 100, 1) : 0;
$monthRoomRevenue = (float)$pdo->query("SELECT COALESCE(SUM(accommodation_total-discount_total),0) FROM bookings WHERE status NOT IN ('cancelled','no_show') AND date_in<DATE_ADD(LAST_DAY(CURDATE()),INTERVAL 1 DAY) AND date_out>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn();
$monthServiceRevenue = (float)$pdo->query("SELECT COALESCE(SUM(bs.total),0) FROM booking_services bs INNER JOIN bookings b ON b.id=bs.booking_id WHERE bs.status!='cancelled' AND bs.created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn();
$roomNights = (int)$pdo->query("SELECT COALESCE(SUM(DATEDIFF(LEAST(date_out,DATE_ADD(LAST_DAY(CURDATE()),INTERVAL 1 DAY)),GREATEST(date_in,DATE_FORMAT(CURDATE(),'%Y-%m-01')))),0) FROM bookings WHERE status NOT IN ('cancelled','no_show') AND date_in<DATE_ADD(LAST_DAY(CURDATE()),INTERVAL 1 DAY) AND date_out>DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn();
$adr = $roomNights > 0 ? $monthRoomRevenue / $roomNights : 0;
$days = (int)date('t');
$revpar = $inventory > 0 ? $monthRoomRevenue / ($inventory * $days) : 0;
// Следующие выборки формируют диаграммы без отдельного аналитического хранилища.
$statusRows = $pdo->query('SELECT status,COUNT(*) total FROM bookings GROUP BY status ORDER BY total DESC')->fetchAll();
$bookingTotal = array_sum(array_map(static fn (array $r): int => (int)$r['total'], $statusRows));
$monthly = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') ym,COUNT(*) total,SUM(CASE WHEN status NOT IN ('cancelled','no_show') THEN total ELSE 0 END) revenue FROM bookings WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL 11 MONTH) GROUP BY ym ORDER BY ym")->fetchAll();
$maxRevenue = max(1, ...array_map(static fn (array $r): float => (float)$r['revenue'], $monthly));
$popular = $pdo->query("SELECT rt.name,COUNT(b.id) bookings,COALESCE(SUM(CASE WHEN b.status NOT IN ('cancelled','no_show') THEN b.accommodation_total-b.discount_total ELSE 0 END),0) revenue FROM room_types rt LEFT JOIN bookings b ON b.room_type_id=rt.id GROUP BY rt.id,rt.name ORDER BY bookings DESC,revenue DESC LIMIT 8")->fetchAll();
$sources = $pdo->query('SELECT source,COUNT(*) total FROM bookings GROUP BY source ORDER BY total DESC')->fetchAll();
$title = 'Аналитика';
require __DIR__.'/../../templates/admin/header.php';
?>
<section class="analytics-grid"><article class="stat-card"><p class="stat-label">Загрузка сегодня</p><p class="stat-value"><?=$occupancy?>%</p><p class="stat-hint"><?=$occupied?> из <?=$inventory?> номеров</p></article><article class="stat-card"><p class="stat-label">ADR за месяц</p><p class="stat-value"><?=e(money($adr))?></p><p class="stat-hint">средний тариф проданной ночи</p></article><article class="stat-card"><p class="stat-label">RevPAR за месяц</p><p class="stat-value"><?=e(money($revpar))?></p><p class="stat-hint">выручка на доступный номер</p></article><article class="stat-card"><p class="stat-label">Услуги за месяц</p><p class="stat-value"><?=e(money($monthServiceRevenue))?></p><p class="stat-hint">дополнительная выручка</p></article></section>
<section class="split-grid"><article class="admin-card"><h2>Статусы бронирований</h2><?php foreach ($statusRows as $row):$percent = $bookingTotal ? round((int)$row['total'] / $bookingTotal * 100, 1) : 0;?><div class="hbar"><div class="hbar-label"><span><?=e(booking_status_label((string)$row['status']))?></span><span><?=e((string)$row['total'])?> · <?=$percent?>%</span></div><div class="hbar-track"><span class="hbar-fill" style="width:<?=$percent?>%"></span></div></div><?php endforeach;?></article><article class="admin-card"><h2>Каналы продаж</h2><div class="quick-list"><?php foreach ($sources as $row):?><div class="quick-row"><span><?=e((string)$row['source'])?></span><strong><?=e((string)$row['total'])?></strong></div><?php endforeach;?></div></article></section>
<section class="admin-card"><h2>Динамика бронирований и выручки</h2><div class="bar-chart"><?php foreach ($monthly as $row):$height = max(3, round((float)$row['revenue'] / $maxRevenue * 100));?><div class="bar-column"><span class="bar-value"><?=e(money($row['revenue']))?></span><span class="bar" style="height:<?=$height?>%"></span><span class="bar-label"><?=e((string)$row['ym'])?><br><?=e((string)$row['total'])?> бр.</span></div><?php endforeach;?></div></section>
<section class="admin-card"><h2>Популярность типов номеров</h2><div class="table-scroll"><table class="admin-table"><thead><tr><th>Тип</th><th>Бронирований</th><th>Выручка проживания</th></tr></thead><tbody><?php foreach ($popular as $row):?><tr><td><?=e((string)$row['name'])?></td><td><?=e((string)$row['bookings'])?></td><td><?=e(money($row['revenue']))?></td></tr><?php endforeach;?></tbody></table></div></section>
<?php require __DIR__.'/../../templates/admin/footer.php';?>
