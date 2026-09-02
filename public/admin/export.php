<?php

declare(strict_types=1);
require __DIR__.'/../../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;
use App\Service\XlsxWriter;

$auth = new Auth($pdo);
$auth->requirePermission('reports.export');
// Закрытый allowlist не позволяет передать произвольный SQL через параметр type.
$exports = [
 'bookings' => ['headers' => ['Код','Заезд','Выезд','Статус','Гость','E-mail','Телефон','Тип номера','Комната','Источник','Проживание','Услуги','Скидка','Итого','Создано'],'sql' => 'SELECT b.code,b.date_in,b.date_out,b.status,CONCAT(c.last_name," ",c.first_name),c.email,c.phone,rt.name,COALESCE(r.number,""),b.source,b.accommodation_total,b.services_total,b.discount_total,b.total,b.created_at FROM bookings b INNER JOIN clients c ON c.id=b.client_id INNER JOIN room_types rt ON rt.id=b.room_type_id LEFT JOIN rooms r ON r.id=b.room_id ORDER BY b.created_at DESC'],
 'rooms' => ['headers' => ['Комната','Тип','Этаж','Статус','Активна','Заметка'],'sql' => 'SELECT r.number,rt.name,f.name,r.status,r.active,COALESCE(r.notes,"") FROM rooms r INNER JOIN room_types rt ON rt.id=r.room_type_id INNER JOIN floors f ON f.id=r.floor_id ORDER BY f.level,r.number'],
 'clients' => ['headers' => ['ID','Фамилия','Имя','Отчество','Дата рождения','E-mail','Телефон','Предпочтения','Маркетинг','Создан'],'sql' => 'SELECT id,last_name,first_name,COALESCE(middle_name,""),COALESCE(birth_date,""),email,phone,COALESCE(preferences,""),marketing_consent,created_at FROM clients ORDER BY last_name'],
 'services' => ['headers' => ['Заказ','Бронь','Услуга','Количество','Цена','Сумма','Статус','Запланировано','Комментарий'],'sql' => 'SELECT bs.id,b.code,s.name,bs.quantity,bs.unit_price,bs.total,bs.status,COALESCE(bs.scheduled_for,""),COALESCE(bs.guest_note,"") FROM booking_services bs INNER JOIN bookings b ON b.id=bs.booking_id INNER JOIN services s ON s.id=bs.service_id ORDER BY bs.created_at DESC'],
 'payments' => ['headers' => ['ID','Бронь','Сумма','Метод','Статус','Ссылка провайдера','Оплачено','Создано'],'sql' => 'SELECT p.id,b.code,p.amount,p.method,p.status,COALESCE(p.provider_reference,""),COALESCE(p.paid_at,""),p.created_at FROM payments p INNER JOIN bookings b ON b.id=p.booking_id ORDER BY p.created_at DESC'],
 'associates' => ['headers' => ['ID','Фамилия','Имя','Должность','E-mail','Телефон','Активен'],'sql' => 'SELECT id,last_name,first_name,position,email,phone,active FROM associates ORDER BY last_name'],
];
$type = (string)($_GET['type'] ?? '');
if (!isset($exports[$type])) {
    http_response_code(404);
    exit('Неизвестный тип экспорта.');
}$config = $exports[$type];
$rows = [];
// Числовой режим PDO сохраняет точный порядок колонок относительно заголовков XLSX.
foreach ($pdo->query($config['sql'])->fetchAll(PDO::FETCH_NUM) as $row) {
    $rows[] = array_values($row);
}audit_log($pdo, 'report.exported', 'report', $type, ['rows' => count($rows)]);
(new XlsxWriter())->download('hotel-'.$type.'-'.date('Y-m-d').'.xlsx', $config['headers'], $rows);
