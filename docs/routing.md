# Lapa Framework Routing Guide

## Basic Routing

The Lapa Framework provides a simple and expressive routing system. All routes are defined in the `/routes` directory.

### Basic Route Definition

```php
$app->on('GET /hello', function() {
    return ['message' => 'Hello World'];
});
```

### HTTP Methods

Support for all common HTTP methods:

```php
// GET request
$app->on('GET /users', function() {
    return ['users' => []];
});

// POST request
$app->on('POST /users', function() use ($app) {
    $data = $app->post();
    return ['created' => true, 'data' => $data];
});

// PUT request
$app->on('PUT /users/:id', function() use ($app) {
    $id = $app->currentParams['id'];
    $data = $app->post();
    return ['updated' => true, 'id' => $id];
});

// DELETE request
$app->on('DELETE /users/:id', function() use ($app) {
    $id = $app->currentParams['id'];
    return ['deleted' => true, 'id' => $id];
});

// Multiple methods
$app->on('GET|POST /data', function() use ($app) {
    // Handle both GET and POST
});
```

## Route Parameters

### Basic Parameters

```php
$app->on('GET /users/:id', function() use ($app) {
    $id = $app->currentParams['id'];
    return ['user_id' => $id];
});
```

### Optional Parameters

```php
$app->on('GET /posts/:year?/:month?', function() use ($app) {
    $year = $app->currentParams['year'] ?? date('Y');
    $month = $app->currentParams['month'] ?? date('m');
    return ['year' => $year, 'month' => $month];
});
```

## Route Groups

Groups allow you to share route attributes across multiple routes:

```php
$app->group('/api/v1', function() use ($app) {
    $app->on('GET /users', function() {
        // Matches /api/v1/users
    });

    $app->on('GET /posts', function() {
        // Matches /api/v1/posts
    });
});
```

### Nested Groups

```php
$app->group('/api', function() use ($app) {
    $app->group('/v1', function() use ($app) {
        $app->on('GET /status', function() {
            // Matches /api/v1/status
        });
    });
});
```

## Virtual Hosts

Virtual host routing allows you to define routes for specific domains:

```php
$app->vhost('api.example.com', function() use ($app) {
    $app->on('GET /', function() {
        return ['api' => 'v1'];
    });
});

$app->vhost('admin.example.com', function() use ($app) {
    $app->on('GET /', function() {
        return ['dashboard' => true];
    });
});
```

## Error Handling

### 404 Not Found Handler

```php
$app->notFound(function() {
    return ['error' => 'Route not found'];
});
```

### Group-Specific 404 Handlers

```php
$app->group('/api', function() use ($app) {
    $app->notFound(function() {
        return ['error' => 'API endpoint not found'];
    });
});
```

## Route Patterns

### Basic Patterns
- `:param` - Required parameter
- `:param?` - Optional parameter
- `*` - Wildcard match

```php
// Required parameter
$app->on('GET /users/:id', function() {});

// Optional parameter
$app->on('GET /posts/:year?', function() {});

// Wildcard
$app->on('GET /files/*', function() {});
```

## Response Types

### JSON Response (Default)
```php
$app->on('GET /data', function() {
    return ['key' => 'value'];
});
```

### HTML Response
```php
$app->on('GET /page', function() use ($app) {
    return $app->view('page', ['title' => 'Welcome']);
});
```

### Raw Response
```php
$app->on('GET /raw', function() use ($app) {
    header('Content-Type: text/plain');
    return 'Raw content';
});
```

## Route Middleware

To apply middleware to specific routes:

```php
$app->on('GET /secure', function() use ($app) {
    // Check authentication
    if (!$app->auth->check()) {
        return ['error' => 'Unauthorized'];
    }
    
    return ['data' => 'secure content'];
});
```

## Best Practices

1. **Organization**: Keep routes organized by feature or resource
```php
// routes/users.php
$app->group('/users', function() use ($app) {
    $app->on('GET /', 'list');
    $app->on('POST /', 'create');
    $app->on('GET /:id', 'show');
    $app->on('PUT /:id', 'update');
    $app->on('DELETE /:id', 'delete');
});
```

2. **Documentation**: Use API documentation annotations
```php
/**
 * @api {get} /users List users
 * @apiParam {Number} page Page number
 * @apiSuccess {Array} users List of users
 */
$app->on('GET /users', function() {
    // Route logic
});
```

3. **Security**: Always validate input parameters
```php
$app->on('POST /users', function() use ($app) {
    $data = $app->post();
    if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Invalid email'];
    }
    // Continue processing
});
```

## Examples

### RESTful API Example
```php
$app->group('/api/v1', function() use ($app) {
    // List all users
    $app->on('GET /users', function() use ($app) {
        $page = $app->get('page', 1);
        $limit = $app->get('limit', 10);
        return $app->db->select('users', '*', [
            'LIMIT' => [$page, $limit]
        ]);
    });

    // Get single user
    $app->on('GET /users/:id', function() use ($app) {
        $id = $app->currentParams['id'];
        return $app->db->get('users', '*', ['id' => $id]);
    });

    // Create user
    $app->on('POST /users', function() use ($app) {
        $data = $app->post();
        $id = $app->db->insert('users', $data);
        return ['created' => true, 'id' => $id];
    });
});
```
