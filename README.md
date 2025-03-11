# Lapa Framework

A minimalist PHP framework for building REST APIs and web applications with a focus on simplicity and flexibility.

## Features

- 🚀 Lightweight and fast
- 🎯 REST API focused
- 🔌 Dynamic helpers
- 🛣️ Simple routing system
- 🔒 Built-in security
- 📝 Automatic logging
- 💾 Simple caching
- 🗄️ Storage management
- 🔑 Sessions and cookies
- 🌐 Virtual hosts support
- 🔄 External API integration
- 🛡️ Authentication helpers
- ✨ Input validation
- 📦 Database support (via Medoo)
- 📧 Mailer support (via PHPMailer)

## Installation

```bash
composer require jdanielcmedina/lapa
```

## Quick Start

```php
require 'vendor/autoload.php';
$app = new Lapa\Lapa();

// Basic route
$app->on('GET /', function() {
    return ['message' => 'Hello World!'];
});

// Protected route
$app->on('GET /protected', function() {
    if (!$this->requireAuth()) {
        return;
    }
    return ['data' => 'protected content'];
});

// REST API example
$app->on('POST /users', function() {
    $data = $this->validate([
        'name' => 'required|min:3',
        'email' => 'required|email'
    ]);
    
    if (!$data) return; // Validation failed
    
    return $this->success($data, 'User created');
});
```

## Documentation

### Request Handling

```php
// GET parameters
$query = $this->get('search');           // Single parameter
$allGet = $this->get();                  // All GET parameters

// POST data
$name = $this->post('name');             // Single field
$allPost = $this->post();                // All POST data

// Combined request data
$all = $this->request();                 // All request data
$specific = $this->request('field');     // Specific field

// JSON body
$json = $this->body();                   // Full body
$field = $this->body('field');           // Specific field
```

### Response Methods

```php
// Success responses
return $this->success($data);                    // 200 OK
return $this->success($data, 'Created', 201);    // 201 Created

// Error responses
return $this->error('Invalid input', 400);       // 400 Bad Request
return $this->error('Unauthorized', 401);        // 401 Unauthorized

// Custom responses
return $this->response($data, 'json');           // JSON
return $this->response($html, 'html');           // HTML
return $this->response($text, 'text');           // Plain text
return $this->response($xml, 'xml');             // XML
```

### Authentication & Protection

```php
// Protect routes
$app->on('GET /api/data', function() {
    if (!$this->requireAuth()) {
        return;
    }
    return ['data' => 'protected'];
});

// Admin routes
$app->on('GET /admin', function() {
    if (!$this->requireAdmin()) {
        return;
    }
    return ['admin' => 'dashboard'];
});
```

### Data Validation

```php
$validated = $this->validate([
    'name' => 'required|min:3',
    'email' => 'required|email',
    'age' => 'numeric|min:18',
    'role' => 'in:user,admin'
]);
```

### Database Operations

```php
// Select
$users = $this->db->select('users', '*');

// Insert
$id = $this->db->insert('users', [
    'name' => 'John',
    'email' => 'john@example.com'
]);

// Update
$this->db->update('users',
    ['status' => 'active'],
    ['id' => 1]
);

// Delete
$this->db->delete('users', ['id' => 1]);
```

### File Management

```php
// Upload
$filename = $this->upload('photo');

// Download
$this->download('file.pdf', 'custom-name.pdf');

// Storage
$path = $this->storage('public');    // Get storage path
$this->clear('cache');               // Clear storage
```

### External API Integration

```php
// GET request
$data = $this->import('https://api.example.com/data', [
    'headers' => ['Authorization' => 'Bearer ' . $token]
]);

// POST request
$result = $this->import('https://api.example.com/users', [
    'method' => 'POST',
    'data' => ['name' => 'John'],
    'headers' => ['X-API-Key' => 'key']
]);
```

### Session & Cookies

```php
// Sessions
$this->session('user_id', 123);          // Set
$id = $this->session('user_id');         // Get
$this->session('user_id', false);        // Remove

// Cookies
$this->cookie('theme', 'dark', [         // Set with options
    'expire' => time() + 86400,
    'secure' => true
]);
$theme = $this->cookie('theme');         // Get
$this->cookie('theme', false);           // Remove
```

## Configuration

Create `storage/app/private/config.php`:

```php
return [
    'debug' => true,
    'timezone' => 'UTC',
    
    'db' => [
        'type' => 'mysql',
        'host' => 'localhost',
        'database' => 'db_name',
        'username' => 'user',
        'password' => 'pass'
    ],
    
    'mail' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'user@example.com',
        'password' => 'password'
    ]
];
```

## Directory Structure

```
lapa/
├── routes/
│   ├── web.php
│   └── api.php
├── storage/
│   ├── app/
│   │   ├── public/
│   │   └── private/
│   ├── cache/
│   ├── logs/
│   └── uploads/
└── src/
    ├── Lapa.php
    └── helpers.php
```

## Requirements

- PHP 7.4+
- Composer
- PHP Extensions:
  - JSON
  - PDO (optional)
  - Fileinfo

## Changelog

### Version 1.5.0
- Removed middleware complexity in favor of helpers
- Improved authentication system
- Added import() method for external API integration
- Enhanced request/response handling
- Added more test coverage
- Code cleanup and optimization
- Updated documentation

## License

MIT License - see the [LICENSE](LICENSE) file for details.

## Contributing

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## Author

Daniel Medina - [@jdanielcmedina](https://twitter.com/jdanielcmedina)
