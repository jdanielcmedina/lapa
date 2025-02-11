# 🚀 Lapa Framework

A minimalist PHP framework for building REST APIs and web applications.

[![Tests](https://github.com/jdanielcmedina/lapa/actions/workflows/tests.yml/badge.svg)](https://github.com/jdanielcmedina/lapa/actions)
[![Latest Version](https://img.shields.io/packagist/v/jdanielcmedina/lapa.svg)](https://packagist.org/packages/jdanielcmedina/lapa)
[![PHP Version](https://img.shields.io/packagist/php-v/jdanielcmedina/lapa.svg)](https://packagist.org/packages/jdanielcmedina/lapa)
[![License](https://img.shields.io/github/license/jdanielcmedina/lapa.svg)](LICENSE)

## Requirements

- PHP 7.4 or higher
- MySQL/MariaDB, SQLite, PostgreSQL, MS SQL or Sybase
- Composer

## Quick Start

1. Create a new project:
```bash
composer create-project jdanielcmedina/lapa my-app
```

2. Copy configuration file:
```bash
cp config.example.php config.php
```

3. Configure your database and other settings in `config.php`

4. Start development server:
```bash
php -S localhost:8000 -t public
```

## Features

### 🛣️ Routing
- Simple and intuitive route definition
- Support for multiple HTTP methods (GET, POST, PUT, PATCH, DELETE, OPTIONS)
- Route parameters (/:id, /:slug)
- Route grouping
- Virtual hosts (subdomains)
- 404 handlers
- Wildcard routes
- Auto-loading routes from /routes directory

### 🔒 Security
- Session protection
- HTTPS enforcement
- Password hashing
- Access control
- CSRF protection
- Secure file permissions
- Custom authentication handlers

### 💾 Storage
- File uploads with validation
- File downloads
- File moving/renaming
- Secure file permissions
- Organized directory structure
- Automatic cleanup
- Disk space monitoring
- Public/private storage areas

### 🗄️ Database (via Medoo)
- MySQL/MariaDB support
- SQLite support
- PostgreSQL support
- MS SQL Server support
- Sybase support
- Safe query building
- Multiple database types

### 🔄 Responses
- JSON responses
- Text responses
- XML responses
- HTML/View responses
- File downloads
- Redirects
- Status codes
- Custom headers
- CORS support

### 🍪 State Management
- Session handling
- Cookie management
- Cache system
- Flash messages
- Headers management

### 📨 Email (via PHPMailer)
- SMTP support
- HTML emails
- Attachments
- Multiple configurations

### 🛠️ Utilities
- Logging system
- Debug mode
- String slugification
- Time ago formatting
- Request validation
- Configuration management
- Random string generation
- String cleaning
- Distance calculation
- Auto-loading configuration

## Installation

```bash
composer require jdanielcmedina/lapa
```

## Basic Usage

```php
<?php
require 'vendor/autoload.php';

// Initialize app (config.php is auto-loaded)
$app = new Lapa();

// Simple route
$app->on('GET /', function() {
    return 'Hello World';
});

// Route with parameters
$app->on('GET /users/:id', function() {
    return $this->db->select('users', '*', [
        'id' => $this->param('id')
    ]);
});

// Protected route
$app->on('GET /admin', function() {
    $this->protect();
    return ['status' => 'admin area'];
});

// Route group
$app->group('/api', function($app) {
    $app->on('GET /status', function() {
        return ['status' => 'online'];
    });
    
    $app->notFound(function() {
        return ['error' => 'API endpoint not found'];
    });
});
```

## Directory Structure

```
/your-app
  /routes
    api.php
    admin.php
    web.php
  /storage
    /app
      /public
      /private
    /logs
    /cache
    /temp
    /uploads
  /public
    index.php
  config.php
  composer.json
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Testing

```bash
composer test
```

## Security

If you discover any security related issues, please email jdanielcmedina@gmail.com instead of using the issue tracker.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Daniel Medina](https://github.com/jdanielcmedina)
- [All Contributors](../../contributors)

## Support

- 📧 Email: jdanielcmedina@gmail.com
- 🐛 Issues: [GitHub Issues](https://github.com/jdanielcmedina/lapa/issues)
- 💬 Discussions: [GitHub Discussions](https://github.com/jdanielcmedina/lapa/discussions)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Author

- Daniel Medina ([@jdanielcmedina](https://github.com/jdanielcmedina))
