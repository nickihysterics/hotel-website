<?php

declare(strict_types=1);

$config = [
    'title' => 'Типы санузлов',
    'table' => 'bathroom_types',
    'primaryKey' => 'id',
    'listTitle' => 'Список типов санузлов',
    'formTitle' => 'Тип санузла',
    'fields' => [
        [
            'name' => 'name',
            'label' => 'Название',
            'type' => 'text',
            'required' => true,
        ],
    ],
];

require __DIR__ . '/resource.php';
