<?php

declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;
use App\Service\AvailabilityService;

$auth = new Auth($pdo);
$auth->requirePermission('bookings.manage');
$id = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
$booking = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $booking = $stmt->fetch() ?: null;
    if ($booking === null) {
        http_response_code(404);
        exit('Бронирование не найдено.');
    }
}
$defaults = $booking ?: [
    'client_id' => '', 'room_type_id' => '', 'room_id' => '', 'date_in' => date('Y-m-d'),
    'date_out' => date('Y-m-d', strtotime('+1 day')), 'status' => 'confirmed', 'adults' => 1,
    'children' => 0, 'source' => 'admin', 'discount_total' => 0, 'guest_comment' => '', 'admin_note' => '',
];
$values = array_merge($defaults, $_POST);
$error = null;

if (is_post()) {
    if (!verify_csrf($_POST['_token'] ?? null)) {
        $error = 'Сессия формы истекла.';
    } else {
        try {
            $clientId = (int) $values['client_id'];
            $roomTypeId = (int) $values['room_type_id'];
            $dateIn = (string) $values['date_in'];
            $dateOut = (string) $values['date_out'];
            $start = new DateTimeImmutable($dateIn);
            $end = new DateTimeImmutable($dateOut);
            if ($clientId <= 0 || $roomTypeId <= 0 || $end <= $start) {
                throw new DomainException('Заполните гостя, тип номера и корректные даты.');
            }
            $bookingStatus = (string) $values['status'];
            $activeStatuses = ['pending', 'confirmed', 'checked_in'];
            if (!in_array($bookingStatus, ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show'], true)) {
                throw new DomainException('Выбран некорректный статус бронирования.');
            }
            // Для активной брони проверяется общий продаваемый остаток с исключением самой записи.
            $availability = new AvailabilityService($pdo);
            if (in_array($bookingStatus, $activeStatuses, true) && !$availability->isAvailable($roomTypeId, $dateIn, $dateOut, $id > 0 ? $id : null)) {
                throw new DomainException('На выбранные даты нет свободного номера этого типа.');
            }
            $roomStmt = $pdo->prepare('SELECT base_price, capacity_adults, capacity_children FROM room_types WHERE id = :id');
            $roomStmt->execute(['id' => $roomTypeId]);
            $roomType = $roomStmt->fetch();
            if (!$roomType) {
                throw new DomainException('Тип номера не найден.');
            }
            if ((int) $values['adults'] > (int) $roomType['capacity_adults'] || (int) $values['children'] > (int) $roomType['capacity_children']) {
                throw new DomainException('Тип номера не вмещает указанное количество гостей.');
            }
            // Конкретная комната необязательна до заселения, но при выборе обязана соответствовать типу.
            $selectedRoomId = (int) $values['room_id'];
            if ($selectedRoomId > 0) {
                $roomCheck = $pdo->prepare(
                    'SELECT COUNT(*) FROM rooms WHERE id = :id AND room_type_id = :room_type_id AND active = 1'
                );
                $roomCheck->execute(['id' => $selectedRoomId, 'room_type_id' => $roomTypeId]);
                if ((int) $roomCheck->fetchColumn() !== 1) {
                    throw new DomainException('Физический номер не относится к выбранному типу.');
                }
                if (in_array($bookingStatus, $activeStatuses, true)) {
                    $conflict = $pdo->prepare(
                        'SELECT COUNT(*) FROM bookings WHERE room_id = :room_id AND id != :id'
                        . " AND status IN ('pending', 'confirmed', 'checked_in')"
                        . ' AND NOT (date_out <= :date_in OR date_in >= :date_out)'
                    );
                    $conflict->execute([
                        'room_id' => $selectedRoomId, 'id' => $id, 'date_in' => $dateIn, 'date_out' => $dateOut,
                    ]);
                    if ((int) $conflict->fetchColumn() > 0) {
                        throw new DomainException('Физический номер уже занят в выбранный период.');
                    }
                }
            }
            // Сотрудник задаёт только скидку: базовая стоимость всегда пересчитывается по датам и тарифу.
            $accommodation = round((float) $roomType['base_price'] * (int) $start->diff($end)->days, 2);
            $discount = min($accommodation, max(0, (float) $values['discount_total']));
            $params = [
                'client_id' => $clientId, 'associate_id' => $auth->user()['associate_id'] ?: null,
                'room_type_id' => $roomTypeId, 'room_id' => $selectedRoomId ?: null,
                'date_in' => $dateIn, 'date_out' => $dateOut, 'status' => $bookingStatus,
                'adults' => max(1, (int) $values['adults']), 'children' => max(0, (int) $values['children']),
                'source' => (string) $values['source'], 'guest_comment' => trim((string) $values['guest_comment']) ?: null,
                'admin_note' => trim((string) $values['admin_note']) ?: null, 'accommodation_total' => $accommodation,
                'discount_total' => $discount, 'total' => $accommodation - $discount,
            ];
            if ($id > 0) {
                $params['id'] = $id;
                $sql = 'UPDATE bookings SET client_id=:client_id, associate_id=:associate_id, room_type_id=:room_type_id, room_id=:room_id, date_in=:date_in, date_out=:date_out, status=:status, adults=:adults, children=:children, source=:source, guest_comment=:guest_comment, admin_note=:admin_note, accommodation_total=:accommodation_total, discount_total=:discount_total, total=:total + services_total WHERE id=:id';
                $pdo->prepare($sql)->execute($params);
                audit_log($pdo, 'booking.updated', 'booking', (string) $id, ['status' => $params['status']]);
            } else {
                $params['code'] = 'HTL-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $sql = 'INSERT INTO bookings (code,client_id,associate_id,room_type_id,room_id,date_in,date_out,status,adults,children,source,guest_comment,admin_note,accommodation_total,discount_total,total) VALUES (:code,:client_id,:associate_id,:room_type_id,:room_id,:date_in,:date_out,:status,:adults,:children,:source,:guest_comment,:admin_note,:accommodation_total,:discount_total,:total)';
                $pdo->prepare($sql)->execute($params);
                $id = (int) $pdo->lastInsertId();
                audit_log($pdo, 'booking.created_by_staff', 'booking', (string) $id);
            }
            flash('success', 'Бронирование сохранено.');
            redirect('/admin/booking-edit.php?id=' . $id);
        } catch (Throwable $exception) {
            $error = $exception instanceof DomainException ? $exception->getMessage() : 'Не удалось сохранить бронирование.';
        }
    }
}

$clients = $pdo->query('SELECT id, CONCAT(last_name, " ", first_name, " · ", phone) label FROM clients ORDER BY last_name')->fetchAll();
$roomTypes = $pdo->query('SELECT id, CONCAT(name, " · ", base_price, " ₽") label FROM room_types WHERE active=1 ORDER BY name')->fetchAll();
$physicalRooms = $pdo->query('SELECT id, room_type_id, CONCAT("№ ", number) label FROM rooms WHERE active=1 ORDER BY number')->fetchAll();
$title = $id > 0 ? 'Бронь ' . (string) ($booking['code'] ?? '') : 'Новая бронь';
require __DIR__ . '/../../templates/admin/header.php';
?>
<section class="admin-card"><h2><?= $id > 0 ? 'Редактирование бронирования' : 'Создание бронирования' ?></h2><?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?><form class="admin-form" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $id) ?>"><div class="form-grid">
<label class="form-field">Гость<select name="client_id" required><option value="">Выберите</option><?php foreach ($clients as $row): ?><option value="<?= e((string) $row['id']) ?>" <?= (int) $values['client_id'] === (int) $row['id'] ? 'selected' : '' ?>><?= e((string) $row['label']) ?></option><?php endforeach; ?></select></label>
<label class="form-field">Тип номера<select name="room_type_id" required><option value="">Выберите</option><?php foreach ($roomTypes as $row): ?><option value="<?= e((string) $row['id']) ?>" <?= (int) $values['room_type_id'] === (int) $row['id'] ? 'selected' : '' ?>><?= e((string) $row['label']) ?></option><?php endforeach; ?></select></label>
<label class="form-field">Физический номер<select name="room_id"><option value="">Назначить позже</option><?php foreach ($physicalRooms as $row): ?><option value="<?= e((string) $row['id']) ?>" <?= (int) $values['room_id'] === (int) $row['id'] ? 'selected' : '' ?>><?= e((string) $row['label']) ?></option><?php endforeach; ?></select></label>
<label class="form-field">Заезд<input type="date" name="date_in" value="<?= e((string) $values['date_in']) ?>" required></label><label class="form-field">Выезд<input type="date" name="date_out" value="<?= e((string) $values['date_out']) ?>" required></label>
<label class="form-field">Статус<select name="status"><?php foreach (['pending','confirmed','checked_in','checked_out','cancelled','no_show'] as $item): ?><option value="<?= e($item) ?>" <?= $values['status'] === $item ? 'selected' : '' ?>><?= e(booking_status_label($item)) ?></option><?php endforeach; ?></select></label>
<label class="form-field">Взрослых<input type="number" min="1" max="8" name="adults" value="<?= e((string) $values['adults']) ?>"></label><label class="form-field">Детей<input type="number" min="0" max="6" name="children" value="<?= e((string) $values['children']) ?>"></label>
<label class="form-field">Источник<select name="source"><?php foreach (['admin' => 'Админка','phone' => 'Телефон','walk_in' => 'С улицы','website' => 'Сайт'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $values['source'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
<label class="form-field">Скидка<input type="number" min="0" step="0.01" name="discount_total" value="<?= e((string) $values['discount_total']) ?>"></label><label class="form-field">Пожелания гостя<textarea name="guest_comment"><?= e((string) $values['guest_comment']) ?></textarea></label><label class="form-field">Внутренняя заметка<textarea name="admin_note"><?= e((string) $values['admin_note']) ?></textarea></label>
</div><div class="form-actions"><button class="button" type="submit">Сохранить</button><a class="button button-secondary" href="/admin/bookings.php">К списку</a></div></form></section>
<?php require __DIR__ . '/../../templates/admin/footer.php'; ?>
