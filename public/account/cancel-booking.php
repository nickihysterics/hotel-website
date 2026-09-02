<?php

declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;
use App\Service\AvailabilityService;
use App\Service\BookingService;

$auth = new Auth($pdo);
$auth->requireGuest();
if (!is_post() || !verify_csrf($_POST['_token'] ?? null)) {
    flash('error', 'Не удалось проверить запрос.');
    redirect('/account/');
}

$service = new BookingService($pdo, new AvailabilityService($pdo));
$cancelled = $service->cancelByClient((int) ($_POST['id'] ?? 0), (int) $auth->clientId());
flash($cancelled ? 'success' : 'error', $cancelled ? 'Бронирование отменено. Оплаченные demo-платежи возвращены.' : 'Это бронирование уже нельзя отменить онлайн.');
redirect('/account/');
