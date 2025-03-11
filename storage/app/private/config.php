<?php
/**
 * Lapa Framework Configuration
 * This is the base configuration file. Do not modify this file directly.
 * Instead, create a config.php file in your application's storage/app/private directory.
 */
return [
    // Debug mode (show detailed errors)
    'debug' => true,
    
    // Application timezone
    'timezone' => 'UTC',

    // Secret key
    'key' => 'your_secret_key_here',
    
    // Database configuration
    'db' => [
        'type' => 'mysql',      // mysql, pgsql, sqlite
        'host' => 'localhost',
        'database' => 'lapa',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ],
    
    // Mail configuration (using PHPMailer)
    'mail' => [
        'host' => 'smtp.example.com',
        'port' => 587,          // 587 (TLS) or 465 (SSL)
        'username' => '',
        'password' => '',
        'from' => [
            'address' => 'noreply@example.com',
            'name' => 'Lapa Framework'
        ],
        'encryption' => 'tls'   // tls or ssl
    ],
    
    // Security settings
    'secure' => false,          // force HTTPS
    
    // Cache settings
    'cache' => [
        'ttl' => 3600          // default cache lifetime in seconds (1 hour)
    ],
    
    // Upload settings
    'upload' => [
        'max_size' => 5242880  // maximum upload size in bytes (5MB)
    ]
];
