<?php

declare(strict_types=1);

use App\Config\Env;
use App\Database;

require __DIR__ . '/autoload.php';

Env::load(__DIR__ . '/../.env');

if (session_status() !== PHP_SESSION_ACTIVE) {
    // За reverse proxy фактический HTTPS определяется в том числе по служебному заголовку.
    $isHttps = ($_SERVER['HTTPS'] ?? '') === 'on'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    session_start();
}

$port = (int) Env::get('DB_PORT', '3306');

// Контейнерные переменные имеют приоритет, а значения ниже подходят только локальному стенду.
$database = new Database(
    host: Env::get('DB_HOST', '127.0.0.1'),
    port: $port,
    name: Env::get('DB_NAME', 'hotel'),
    user: Env::get('DB_USER', 'hotel'),
    pass: Env::get('DB_PASS', 'hotel')
);

$pdo = $database->pdo();
