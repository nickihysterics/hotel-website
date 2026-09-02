<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * Универсальный CRUD для простых справочников с закрытым списком полей.
 *
 * Имена таблицы и столбцов поступают только из конфигурации приложения, а значения
 * всегда передаются подготовленным выражениям PDO.
 */
final class CrudRepository
{
    /** @param string[] $columns Список допустимых столбцов. */
    public function __construct(
        private PDO $pdo,
        private string $table,
        private string $primaryKey,
        private array $columns
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $sql = sprintf('SELECT * FROM `%s` ORDER BY `%s`', $this->table, $this->primaryKey);
        return $this->pdo->query($sql)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `%s` = :id', $this->table, $this->primaryKey);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $filtered = $this->filter($data);
        $columns = array_keys($filtered);
        $placeholders = array_map(fn (string $col): string => ':' . $col, $columns);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            implode(', ', array_map(fn (string $col): string => '`' . $col . '`', $columns)),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($filtered);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $filtered = $this->filter($data);
        if ($filtered === []) {
            return;
        }

        $assignments = array_map(fn (string $col): string => sprintf('`%s` = :%s', $col, $col), array_keys($filtered));
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = :id',
            $this->table,
            implode(', ', $assignments),
            $this->primaryKey
        );

        $filtered['id'] = $id;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($filtered);
    }

    public function delete(int $id): void
    {
        $sql = sprintf('DELETE FROM `%s` WHERE `%s` = :id', $this->table, $this->primaryKey);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filter(array $data): array
    {
        // Поля вне allowlist игнорируются и не могут попасть в динамический SQL.
        return array_intersect_key($data, array_flip($this->columns));
    }
}
