<?php

declare(strict_types=1);

$config = [
    'title' => 'Клиенты',
    'table' => 'clients',
    'primaryKey' => 'id',
    'listTitle' => 'Список клиентов',
    'formTitle' => 'Клиент',
    'fields' => [
        [
            'name' => 'last_name',
            'label' => 'Фамилия',
            'type' => 'text',
            'required' => false,
        ],
        ['name' => 'email', 'label' => 'E-mail', 'type' => 'text', 'required' => true],
        [
            'name' => 'first_name',
            'label' => 'Имя',
            'type' => 'text',
            'required' => true,
        ],
        ['name' => 'preferences', 'label' => 'Предпочтения', 'type' => 'textarea', 'required' => false],
        ['name' => 'marketing_consent', 'label' => 'Маркетинг', 'type' => 'select', 'valueType' => 'int', 'required' => true, 'options' => [0 => 'Нет', 1 => 'Да']],
        [
            'name' => 'middle_name',
            'label' => 'Отчество',
            'type' => 'text',
            'required' => false,
        ],
        [
            'name' => 'birth_date',
            'label' => 'Дата рождения',
            'type' => 'date',
            'required' => true,
        ],
        [
            'name' => 'phone',
            'label' => 'Телефон',
            'type' => 'text',
            'required' => true,
        ],
    ],
];

require __DIR__ . '/resource.php';
