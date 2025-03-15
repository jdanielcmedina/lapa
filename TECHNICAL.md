# Lapa Framework Technical Documentation

## Core Architecture

### Constants
- `DS`: Directory separator
- `ENV`: Current environment (production/development)
- `ROOT`: Framework root directory
- `APP`: Application directory (src)
- `STORAGE`: Storage base directory
- `CONFIG`: Configuration directory
- `ROUTES`: Routes directory
- `VIEWS`: Views directory
- `CACHE`: Cache directory
- `LOGS`: Logs directory
- `UPLOADS`: File uploads directory
- `TEMP`: Temporary files directory
- `EXT`: PHP file extension (.php)

### Storage Types
```php
$storagePaths = [
    'app' => CONFIG,          // Application storage
    'logs' => LOGS,          // Log files
    'cache' => CACHE,        // Cache files
    'temp' => TEMP,          // Temporary files
    'uploads' => UPLOADS,    // Public uploads
    'views' => VIEWS         // View templates
];
```

### File Permissions
```php
$permissions = [
    'public' => 0644,    // Public readable files
    'private' => 0600,   // Private files
    'folder' => 0755     // Directories
];
```

## Routing System

### Route Pattern Syntax
- Basic: `GET /users`
- Parameters: `GET /users/:id`
- Multiple Methods: `GET|POST /api/data`
- Optional Parameters: `GET /posts/:id?`

### Route Groups
Groups allow prefix organization:
```php
$app->group('/admin', function($app) {
    $app->on('GET /users', function() {
        // Route: /admin/users
    });
    
    $app->group('/settings', function($app) {
        // Nested group: /admin/settings/...
    });
});
```

### Virtual Hosts
Support multiple domains:
```php
$app->vhost('api.example.com', function($app) {
    $app->on('GET /v1/users', function() {
        // Only responds on api.example.com
    });
});
```

## Storage System

### Directory Structure
```
storage/
├── app/           # Application storage (private)
│   └── config.php # Main configuration
├── cache/         # Cache files
├── logs/          # Log files
│   └── YYYY-MM-DD.log
├── temp/          # Temporary files
└── uploads/       # Public uploads
    └── .htaccess  # Allow public access
```

### File Operations
```php
// Upload handling
$config = [
    'max_size' => 5242880,        // 5MB
    'allowed_types' => [
        'image/jpeg',
        'image/png',
        'application/pdf'
    ]
];

// Storage paths
$paths = [
    'public' => 'uploads',        // Public files
    'private' => 'app',          // Private files
    'temp' => 'temp'            // Temporary files
];
```

## Security Features

### Request Validation
```php
// Available Rules
$rules = [
    'required' => true,
    'email' => filter_var($value, FILTER_VALIDATE_EMAIL),
    'numeric' => is_numeric($value),
    'min:N' => strlen($value) >= N,
    'max:N' => strlen($value) <= N,
    'in:a,b,c' => in_array($value, ['a','b','c']),
    'url' => filter_var($value, FILTER_VALIDATE_URL),
    'alpha' => ctype_alpha($value)
];
```

### Headers Security
- Default security headers
- CORS configuration
- Content Security Policy
- XSS Protection
- Frame Options

### File Security
- Extension validation
- MIME type checking
- Size limitations
- Path traversal protection

## Cache System

### Cache Types
1. File Cache (default)
```php
// Structure
[
    'value' => mixed,
    'expires' => timestamp
]
```

2. Memory Cache (optional)
```php
// Runtime cache
static $cache = [];
```

### Cache Operations
- `set`: Store value with TTL
- `get`: Retrieve value
- `has`: Check existence
- `delete`: Remove entry
- `clear`: Clear all cache

## Database Integration

### Medoo Configuration
```php
$config = [
    'type' => 'mysql',
    'host' => 'localhost',
    'database' => 'dbname',
    'username' => 'user',
    'password' => 'pass',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'port' => 3306,
    'prefix' => ''
];
```

### Query Building
```php
// Select with join
$this->db->select('posts', [
    '[>]users' => ['user_id' => 'id']
], [
    'posts.id',
    'posts.title',
    'users.name'
]);

// Insert with validation
$this->db->insert('users', [
    'name' => $data['name'],
    'email' => $data['email'],
    'created_at' => date('Y-m-d H:i:s')
]);
```

## Error Handling

### Log Levels
1. DEBUG: Detailed debug information
2. INFO: Interesting events
3. NOTICE: Normal but significant events
4. WARNING: Exceptional occurrences
5. ERROR: Runtime errors
6. CRITICAL: Critical conditions
7. ALERT: Action must be taken
8. EMERGENCY: System is unusable

### Error Response Format
```json
{
    "error": true,
    "code": 400,
    "message": "Error description",
    "details": {
        "field": ["Error message"]
    }
}
```

## Performance Optimization

### Cache Strategies
1. Route caching
2. Configuration caching
3. View compilation
4. Database query caching

### Request Processing
1. Parse request
2. Load configuration
3. Match route
4. Execute middleware
5. Process controller
6. Format response
7. Send headers
8. Output content

## Testing

### Test Categories
1. Unit Tests
2. Integration Tests
3. Feature Tests
4. Performance Tests

### PHPUnit Configuration
```xml
<phpunit>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

## Deployment

### Requirements Check
```php
[
    'php' => '7.4.0',
    'extensions' => [
        'pdo',
        'json',
        'fileinfo',
        'curl'
    ],
    'writable' => [
        'storage/cache',
        'storage/logs',
        'storage/uploads'
    ]
]
```

### Production Settings
```php
[
    'debug' => false,
    'log_level' => 'error',
    'display_errors' => false,
    'cache_routes' => true,
    'optimize_autoloader' => true
]
```

## Core Functions Reference

### Request Handling
```php
// Body parsing
$this->body($key = null)              // Get request body or specific key
$this->request($key = null)           // Get all request data or specific key
$this->get($key = null)               // Get query parameter
$this->post($key = null)              // Get post data
$this->param($name)                   // Get URL parameter

// Headers
$this->header($key, $value = null)    // Get/set headers
$this->type($type, $charset = 'utf8') // Set content type
$this->cors($origins = '*')           // Set CORS headers
$this->token()                        // Get bearer token
```

### Response Methods
```php
// Core responses
$this->response($data, $type = 'json', $code = null)  // Generic response
$this->success($data = null, $message = 'Success')    // Success response
$this->error($message, $code = 400)                   // Error response
$this->raw($content, $type = 'text/html')            // Raw response
$this->redirect($url)                                 // URL redirect
$this->status($code)                                  // Set HTTP status

// Views
$this->view($file, $data = [], $code = null)         // Render view
$this->partial($name, $data = [])                    // Include partial
```

### Storage Operations
```php
// File handling
$this->upload($field, $path = null)            // Upload file
$this->download($file, $name = null)           // Download file
$this->rename($from, $to, $type = 'app')       // Move/rename file
$this->storage($type = 'app')                  // Get storage path
$this->clear($type = 'cache')                  // Clear storage
$this->cleanup($maxAge = 86400)                // Clean old files

// Cache
$this->cache($key, $value = null, $ttl = null) // Cache operations
```

### Session & Cookies
```php
// Session handling
$this->session($key = null, $value = null)     // Session operations
$this->flash($key, $value = null)              // Flash messages

// Cookie management
$this->cookie($key = null, $value = null, $options = [])  // Cookie operations
```

### Database Operations
```php
// Connection
$this->db()                           // Get database instance

// Common queries
$this->db->select(string $table, array $columns)
$this->db->insert(string $table, array $data)
$this->db->update(string $table, array $data, array $where)
$this->db->delete(string $table, array $where)
$this->db->get(string $table, array $columns, array $where)
$this->db->has(string $table, array $where)
$this->db->count(string $table, array $where)
```

### Utility Functions
```php
// String manipulation
$this->random($length = 16, $type = 'alphanumeric')  // Generate random string
$this->slug($text)                                   // Generate URL slug
$this->clean($string, $chars = [])                   // Clean string
$this->ago($date)                                    // Human readable time

// Data processing
$this->validate($rules, $data = null)                // Validate input
$this->import($url, $options = [])                   // Import external data

// Geolocation
$this->distance($lat1, $lon1, $lat2, $lon2, $unit = 'K')  // Calculate distance
```

### Routing Functions
```php
// Route registration
$this->on($route, $callback)                // Register route
$this->any($callback)                       // Wildcard route
$this->group($prefix, $callback)            // Route group
$this->vhost($host, $callback)              // Virtual host
$this->notFound($handler)                   // 404 handler

// Route utilities
$this->loadRoutes($routesPath = null)       // Load route files
$this->handleRequest()                      // Process request
```

### System Functions
```php
// Configuration
$this->config($key = null)                  // Get config value
$this->isConfigured()                       // Check if configured

// Debug & Logging
$this->log($message, $level = 'info')       // Write to log
$this->debug()                              // Debug information

// Email
$this->mail()                               // Get mailer instance
```

### Magic Methods
```php
$this->__call($name, $arguments)            // Dynamic method handling
$this->__set($name, $value)                 // Dynamic property setting
```
