<?php

return [
    'name' => 'Humano Core',
    'version' => '1.0.0',
    'description' => 'Core functionality for Humano system',

    'modules' => [
        'core' => [
            'name' => 'Core System',
            'description' => 'Essential system functionality',
            'enabled' => true,
        ],
        'users' => [
            'name' => 'User Management',
            'description' => 'User and team management',
            'enabled' => true,
        ],
        'categories' => [
            'name' => 'Categories',
            'description' => 'Category management system',
            'enabled' => true,
        ],
    ],
];
