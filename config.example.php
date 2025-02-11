<?php
return [
    'debug' => false,
    'secure' => true,
    'db' => [
        'type' => 'mysql',
        'host' => 'localhost',
        'database' => 'database_name',
        'username' => 'user',
        'password' => 'pass',
        'charset' => 'utf8mb4'
    ],
    'mail' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'user@example.com',
        'password' => 'pass',
        'secure' => 'tls'
    ],
    'storage' => [
        'max_size' => '10M',
        'allowed_types' => ['jpg', 'png', 'pdf']
    ]
]; 