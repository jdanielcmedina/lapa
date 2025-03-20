# Lapa Framework

A minimalist PHP framework for building REST APIs and web applications.

## Table of Contents
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Core Features](#core-features)
  - [Routing](#routing)
  - [Middleware](#middleware)
  - [Database](#database)
  - [File Storage](#file-storage)
  - [Views & Layouts](#views--layouts)
  - [Error Handling](#error-handling)
  - [Plugins System](#plugins-system)
  - [Helpers](#helpers)
  - [API Documentation](#api-documentation)
- [Configuration](#configuration)
- [Directory Structure](#directory-structure)
- [Advanced Usage](#advanced-usage)
- [Security](#security)
- [Examples](#examples)

## Installation

```bash
composer create-project jdanielcmedina/lapa my-project
cd my-project
```

## Quick Start

### Basic Setup
```php
<?php
require '../vendor/autoload.php';

$app = new \Lapa\Lapa([
    'debug' => true,
    'timezone' => 'UTC'
]);
```

### First Route
```php
$app->on('GET /', function() {
    return ['message' => 'Welcome to Lapa!'];
});
```

## Core Features

### Routing

#### Basic Routes
```php
// Simple GET route
$app->on('GET /users', function() {
    return ['users' => []];
});

// POST route with data
$app->on('POST /users', function() use ($app) {
    $data = $app->post();
    return ['created' => true, 'data' => $data];
});
```

#### Route Parameters
```php
$app->on('GET /users/:id', function() use ($app) {
    $id = $app->currentParams['id'];
    return ['user' => ['id' => $id]];
});
```

#### Route Groups
```php
$app->group('/api/v1', function() use ($app) {
    $app->on('GET /status', function() {
        return ['status' => 'operational'];
    });
    
    $app->on('GET /health', function() {
        return ['health' => 'ok'];
    });
});
```

#### Virtual Hosts
```php
$app->vhost('api.example.com', function() use ($app) {
    $app->on('GET /', function() {
        return ['api' => 'v1'];
    });
});
```

### Database

#### Configuration
```php
$config = [
    'db' => [
        'type' => 'mysql',
        'host' => 'localhost',
        'database' => 'test',
        'username' => 'root',
        'password' => ''
    ]
];
```

#### Usage (with Medoo)
```php
// Select
$users = $app->db->select('users', '*');

// Insert
$id = $app->db->insert('users', [
    'name' => 'John',
    'email' => 'john@example.com'
]);

// Update
$app->db->update('users',
    ['name' => 'Jane'],
    ['id' => 1]
);

// Delete
$app->db->delete('users', ['id' => 1]);
```

### File Storage

#### Upload Files
```php
// Single file upload
$filename = $app->upload('photo');

// With validation
$filename = $app->upload('document', [
    'types' => ['application/pdf'],
    'max_size' => 1024 * 1024 // 1MB
]);
```

#### Download Files
```php
// Force download
$app->download('file.pdf');

// With custom name
$app->download('file.pdf', 'custom-name.pdf');
```

#### Storage Management
```php
// Write to storage
$app->storage('cache')->write('key', $data);

// Read from storage
$data = $app->storage('cache')->read('key');

// Delete from storage
$app->storage('cache')->delete('key');

// Clear storage
$app->storage('cache')->clear();
```

### Views & Layouts

#### Basic View
```php
// Render view
$app->view('home', [
    'title' => 'Welcome',
    'user' => $user
]);
```

#### With Layout
```php
// Render view with layout
$app->layout('home', 'default', [
    'title' => 'Welcome',
    'user' => $user
]);
```

#### Partial Views
```php
// In your view file
<?php $app->partial('header', ['title' => $title]); ?>
```

### Plugin System

#### Creating a Plugin
```php
// plugins/Cache.php
namespace Lapa\Plugins;

class CacheManager {
    private $app;
    
    public function __construct($app) {
        $this->app = $app;
    }

    public function set($key, $value, $ttl = 3600) {
        $file = $this->app->storage('cache') . '/' . md5($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        return file_put_contents($file, serialize($data));
    }

    public function get($key) {
        $file = $this->app->storage('cache') . '/' . md5($key);
        if (!file_exists($file)) return null;
        
        $data = unserialize(file_get_contents($file));
        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }
}
```

#### Using Plugins
```php
// Cache data
$app->cache->set('user.1', $userData);

// Get cached data
$user = $app->cache->get('user.1');
```

### Helpers

#### Creating Helpers
```php
// helpers/string.php
$app->slug = function($text) {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $text));
};

$app->truncate = function($text, $length = 100) {
    return strlen($text) > $length 
        ? substr($text, 0, $length) . '...' 
        : $text;
};
```

#### Using Helpers
```php
$slug = $app->slug('Hello World'); // hello-world
$text = $app->truncate($longText, 50);
```

### API Documentation

#### Documenting Routes
```php
/**
 * @api {get} /users List users
 * @apiParam {Number} page Page number
 * @apiParam {Number} limit Results per page
 * @apiSuccess {Array} users List of users
 * @apiSuccess {Number} total Total number of users
 */
$app->on('GET /users', function() {
    // route logic
});
```

#### Generating Documentation
```php
// Routes: /docs
$app->on('GET /docs', function() use ($app) {
    return $app->docs();
});
```

### Error Handling

#### Debug Mode
```php
// Enable debug mode in config
$config = ['debug' => true];

// Custom error handling
try {
    // your code
} catch (\Exception $e) {
    $app->debug($e->getMessage(), 500, $e->getTraceAsString());
}
```

#### Logging
```php
// Log levels: debug, info, warning, error
$app->log('Database connection failed', 'error');
$app->log('Cache hit for key: user.1', 'debug');
```

## Configuration

### Full Configuration Options
```php
[
    'debug' => false,
    'secure' => false,
    'errors' => true,
    'timezone' => 'UTC',
    'upload' => [
        'max_size' => 5242880, // 5MB
        'allowed_types' => ['image/jpeg', 'image/png', 'application/pdf']
    ],
    'cache' => [
        'ttl' => 3600 // 1 hour
    ],
    'cors' => [
        'enabled' => false,
        'origins' => '*',
        'methods' => 'GET, POST, PUT, DELETE, OPTIONS, PATCH',
        'headers' => 'Content-Type, Authorization, X-Requested-With',
        'credentials' => false
    ],
    'mail' => [
        'enabled' => false,
        'host' => 'smtp.example.com',
        'port' => 587,
        'secure' => 'tls',
        'auth' => true,
        'username' => '',
        'password' => '',
        'from_name' => 'Lapa Framework',
        'from_email' => 'noreply@example.com'
    ]
]
```

## Directory Structure
```
my-project/
├── public/               # Public directory
│   ├── index.php        # Entry point
│   └── .htaccess       # URL rewriting
├── routes/              # Route definitions
│   ├── web.php         # Web routes
│   └── api.php         # API routes
├── views/               # View templates
│   ├── layouts/        # Layout templates
│   └── partials/       # Partial views
├── storage/            # File storage
│   ├── cache/         # Cache files
│   ├── logs/          # Log files
│   ├── uploads/       # Uploaded files
│   └── temp/          # Temporary files
├── helpers/            # Helper functions
├── plugins/            # Custom plugins
└── vendor/            # Composer packages
```

## Security

### CORS Configuration
```php
$config = [
    'cors' => [
        'enabled' => true,
        'origins' => ['https://example.com'],
        'methods' => 'GET, POST',
        'headers' => 'X-Requested-With'
    ]
];
```

### File Upload Security
```php
// Secure file upload configuration
$config = [
    'upload' => [
        'max_size' => 1024 * 1024, // 1MB
        'allowed_types' => [
            'image/jpeg',
            'image/png',
            'application/pdf'
        ],
        'sanitize' => true
    ]
];
```

## License

MIT License. See LICENSE file for details.
