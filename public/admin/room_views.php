<?php

declare(strict_types=1);

$config = [
    'title' => 'Виды из номера',
    'table' => 'room_views',
    'primaryKey' => 'id',
    'listTitle' => 'Список видов',
    'formTitle' => 'Вид из номера',
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
