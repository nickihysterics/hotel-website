<?php

declare(strict_types=1);

$config = [
    'title' => 'Номерной фонд',
    'table' => 'rooms',
    'primaryKey' => 'id',
    'listTitle' => 'Физические номера',
    'formTitle' => 'Номер',
    'permission' => 'catalog.manage',
    'fields' => [
        ['name' => 'number', 'label' => 'Номер комнаты', 'type' => 'text', 'required' => true],
        ['name' => 'room_type_id', 'label' => 'Тип', 'type' => 'select', 'required' => true, 'options' => static function (PDO $pdo): array {
            $result = [];
            foreach ($pdo->query('SELECT id, name FROM room_types ORDER BY name')->fetchAll() as $row) {
                $result[$row['id']] = $row['name'];
            }
            return $result;
        }],
        ['name' => 'floor_id', 'label' => 'Этаж', 'type' => 'select', 'required' => true, 'options' => static function (PDO $pdo): array {
            $result = [];
            foreach ($pdo->query('SELECT id, name FROM floors ORDER BY level')->fetchAll() as $row) {
                $result[$row['id']] = $row['name'];
            }
            return $result;
        }],
        ['name' => 'status', 'label' => 'Статус', 'type' => 'select', 'valueType' => 'string', 'required' => true, 'options' => [
            'available' => 'Готов', 'occupied' => 'Занят', 'dirty' => 'Нужна уборка', 'cleaning' => 'Убирается', 'maintenance' => 'Ремонт',
        ]],
        ['name' => 'notes', 'label' => 'Заметка', 'type' => 'textarea', 'required' => false],
        ['name' => 'active', 'label' => 'В фонде', 'type' => 'select', 'valueType' => 'int', 'required' => true, 'options' => [0 => 'Нет', 1 => 'Да']],
    ],
];

require __DIR__ . '/resource.php';
