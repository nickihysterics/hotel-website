<?php

declare(strict_types=1);

$config = [
    'title' => 'Категории услуг', 'table' => 'service_categories', 'primaryKey' => 'id',
    'listTitle' => 'Категории услуг', 'formTitle' => 'Категория', 'permission' => 'services.manage',
    'fields' => [
        ['name' => 'name', 'label' => 'Название', 'type' => 'text', 'required' => true],
        ['name' => 'sort_order', 'label' => 'Порядок', 'type' => 'number', 'valueType' => 'int', 'min' => '0', 'required' => true],
    ],
];
require __DIR__ . '/resource.php';
