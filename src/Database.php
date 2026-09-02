<?php

declare(strict_types=1);

namespace App;

use PDO;

/** Создаёт единственное настроенное PDO-подключение приложения к MySQL. */
final class Database
{
    private PDO $pdo;

    public function __construct(
        string $host,
        int $port,
        string $name,
        string $user,
        string $pass
    ) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Настоящие prepared statements не передают пользовательские значения как часть SQL-строки.
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
