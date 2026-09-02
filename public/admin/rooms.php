<?php

declare(strict_types=1);

$lookup = static function (PDO $pdo, string $table): array {
    $rows = $pdo->query(sprintf('SELECT id, name FROM `%s` ORDER BY name', $table))->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $result[$row['id']] = $row['name'];
    }
    return $result;
};

$config = [
    'title' => 'Типы номеров',
    'table' => 'room_types',
    'primaryKey' => 'id',
    'listTitle' => 'Каталог типов номеров',
    'formTitle' => 'Тип номера',
    'permission' => 'catalog.manage',
    'fields' => [
        ['name' => 'name', 'label' => 'Название', 'type' => 'text', 'required' => true],
        ['name' => 'slug', 'label' => 'Slug', 'type' => 'text', 'required' => true],
        ['name' => 'category_id', 'label' => 'Категория', 'type' => 'select', 'required' => true, 'options' => static fn (PDO $pdo): array => $lookup($pdo, 'categories')],
        ['name' => 'view_id', 'label' => 'Вид', 'type' => 'select', 'required' => true, 'options' => static fn (PDO $pdo): array => $lookup($pdo, 'room_views')],
        ['name' => 'bathroom_type_id', 'label' => 'Санузел', 'type' => 'select', 'required' => true, 'options' => static fn (PDO $pdo): array => $lookup($pdo, 'bathroom_types')],
        ['name' => 'base_price', 'label' => 'Цена / ночь', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'required' => true],
        ['name' => 'capacity_adults', 'label' => 'Взрослых', 'type' => 'number', 'valueType' => 'int', 'min' => '1', 'required' => true],
        ['name' => 'capacity_children', 'label' => 'Детей', 'type' => 'number', 'valueType' => 'int', 'min' => '0', 'required' => true],
        ['name' => 'bed_count', 'label' => 'Кроватей', 'type' => 'number', 'valueType' => 'int', 'min' => '1', 'required' => true],
        ['name' => 'size_sqm', 'label' => 'Площадь, м²', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'required' => false],
        ['name' => 'short_description', 'label' => 'Краткое описание', 'type' => 'text', 'required' => true],
        ['name' => 'description', 'label' => 'Полное описание', 'type' => 'textarea', 'required' => true],
        ['name' => 'photo', 'label' => 'Файл фото', 'type' => 'text', 'required' => false],
        ['name' => 'bathroom_separate', 'label' => 'Раздельный санузел', 'type' => 'select', 'valueType' => 'int', 'required' => true, 'options' => [0 => 'Нет', 1 => 'Да']],
        ['name' => 'featured', 'label' => 'Рекомендуемый', 'type' => 'select', 'valueType' => 'int', 'required' => true, 'options' => [0 => 'Нет', 1 => 'Да']],
        ['name' => 'active', 'label' => 'Доступен на сайте', 'type' => 'select', 'valueType' => 'int', 'required' => true, 'options' => [0 => 'Нет', 1 => 'Да']],
    ],
];

require __DIR__ . '/resource.php';
