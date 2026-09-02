<?php

declare(strict_types=1);

$config = [
    'title' => 'Сотрудники',
    'table' => 'associates',
    'primaryKey' => 'id',
    'listTitle' => 'Список сотрудников',
    'formTitle' => 'Сотрудник',
    'fields' => [
        [
            'name' => 'last_name',
            'label' => 'Фамилия',
            'type' => 'text',
            'required' => true,
        ],
        [
            'name' => 'first_name',
            'label' => 'Имя',
            'type' => 'text',
            'required' => true,
        ],
        [
            'name' => 'middle_name',
            'label' => 'Отчество',
            'type' => 'text',
            'required' => false,
        ],
        [
            'name' => 'position',
            'label' => 'Должность',
            'type' => 'text',
            'required' => true,
        ],
        [
            'name' => 'phone',
            'label' => 'Телефон',
            'type' => 'text',
            'required' => true,
        ],
        ['name' => 'email', 'label' => 'E-mail', 'type' => 'text', 'required' => true],
        [
            'name' => 'birth_date',
            'label' => 'Дата рождения',
            'type' => 'date',
            'required' => false,
        ],
        ['name' => 'active', 'label' => 'Активен', 'type' => 'select', 'valueType' => 'int', 'required' => true, 'options' => [0 => 'Нет', 1 => 'Да']],
    ],
];

require __DIR__ . '/resource.php';
