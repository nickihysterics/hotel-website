<?php

declare(strict_types=1);

$config = [
    'title' => 'Этажи',
    'table' => 'floors',
    'primaryKey' => 'id',
    'listTitle' => 'Список этажей',
    'formTitle' => 'Этаж',
    'fields' => [
        [
            'name' => 'name',
            'label' => 'Название',
            'type' => 'text',
            'required' => true,
        ],
        [
            'name' => 'level',
            'label' => 'Номер этажа',
            'type' => 'number',
            'step' => '1',
            'min' => '0',
            'required' => true,
            'valueType' => 'int',
        ],
    ],
];

require __DIR__ . '/resource.php';
