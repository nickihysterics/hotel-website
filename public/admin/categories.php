<?php

declare(strict_types=1);

$config = [
    'title' => 'Категории',
    'table' => 'categories',
    'primaryKey' => 'id',
    'listTitle' => 'Список категорий',
    'formTitle' => 'Категория',
    'fields' => [
        [
            'name' => 'name',
            'label' => 'Название',
            'type' => 'text',
            'required' => true,
        ],
        ['name' => 'slug', 'label' => 'Slug', 'type' => 'text', 'required' => true],
        ['name' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'required' => false],
    ],
];

require __DIR__ . '/resource.php';
