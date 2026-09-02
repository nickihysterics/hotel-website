<?php

declare(strict_types=1);

$config = [
    'title' => 'Услуги', 'table' => 'services', 'primaryKey' => 'id',
    'listTitle' => 'Каталог услуг', 'formTitle' => 'Услуга', 'permission' => 'services.manage',
    'fields' => [
        ['name' => 'name', 'label' => 'Название', 'type' => 'text', 'required' => true],
        ['name' => 'slug', 'label' => 'Slug', 'type' => 'text', 'required' => true],
        ['name' => 'category_id', 'label' => 'Категория', 'type' => 'select', 'required' => true, 'options' => static function (PDO $pdo): array {
            $result = [];
            foreach ($pdo->query('SELECT id, name FROM service_categories ORDER BY sort_order')->fetchAll() as $row) {
                $result[$row['id']] = $row['name'];
            }
            return $result;
        }],
        ['name' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'required' => true],
        ['name' => 'price', 'label' => 'Цена', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'required' => true],
        ['name' => 'pricing_type', 'label' => 'Тарификация', 'type' => 'select', 'valueType' => 'string', 'required' => true, 'options' => [
            'per_order' => 'За заказ', 'per_day' => 'За день', 'per_person' => 'За гостя', 'per_unit' => 'За единицу',
        ]],
        ['name' => 'icon', 'label' => 'Иконка', 'type' => 'text', 'required' => false],
        ['name' => 'active', 'label' => 'Продаётся', 'type' => 'select', 'valueType' => 'int', 'required' => true, 'options' => [0 => 'Нет', 1 => 'Да']],
    ],
];
require __DIR__ . '/resource.php';
