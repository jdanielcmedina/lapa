<?php
/**
 * Lapa Framework Base Configuration
 * This is the base configuration template.
 * Copy this file to /storage/app/private/config.php and modify as needed.
 */
return [
    // Application settings
    'name' => 'Lapa Application',
    'debug' => true,
    'timezone' => 'UTC',
    'key' => 'your-secret-key',
    
    // Storage structure 
    'storage' => [
        'paths' => [
            'app' => 'storage/app',
            'public' => 'storage/app/public',
            'private' => 'storage/app/private',
            'logs' => 'storage/logs',
            'cache' => 'storage/cache',
            'temp' => 'storage/temp',
            'uploads' => 'storage/uploads',
            'views' => 'resources/views'
        ],
        'permissions' => [
            'public' => 0644,
            'private' => 0600,
            'folder' => 0755
        ]
    ],
    
    // Database configuration
    'db' => [
        'type' => 'mysql',
        'host' => 'localhost',
        'database' => 'lapa',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'prefix' => '',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    ],
    
    // Mail configuration
    'mail' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => '',
        'password' => '',
        'from' => [
            'address' => 'noreply@example.com',
            'name' => 'Lapa Framework'
        ],
        'encryption' => 'tls',
        'debug' => 0
    ],
    
    // Security settings
    'secure' => false,
    
    // Upload settings
    'upload' => [
        'max_size' => 5242880,
        'allowed_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf'
        ],
        'max_files' => 20
    ],
    
    // Cache settings
    'cache' => [
        'driver' => 'file',
        'ttl' => 3600,
        'prefix' => 'lapa_'
    ],
    
    // Session configuration
    'session' => [
        'name' => 'lapa_session',
        'lifetime' => 120,
        'secure' => false,
        'httponly' => true
    ],
    
    // Logging configuration  
    'log' => [
        'level' => 'debug',
        'days' => 30,
        'size' => 10485760
    ]
];