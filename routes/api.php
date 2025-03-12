<?php

$app->on('GET /', function() {
    return ['message' => 'Welcome to the homepage'];
});

$app->on('GET /tt', function() {
    return ['message' => 'Welcome to the homepage'];
});

// Public routes
$app->on('POST /login', function() {
    $credentials = $this->request();
    // Login logic here
    return $this->success(['token' => 'jwt_token_here']);
});

// Protected API routes using helpers
$app->group('/api', function($app) {
    $app->on('GET /users', function() {
        if (!$this->requireAuth()) {
            return;
        }
        return ['users' => []];
    });
    
    $app->on('GET /admin', function() {
        if (!$this->requireAdmin()) {
            return;
        }
        return ['admin' => 'data'];
    });
});

// Protected route using helper
$app->on('GET /protected', function() {
    if (!$this->requireAuth()) {
        return; // Auth helper already sent error response
    }
    return ['data' => 'protected content'];
});

// Admin route using helper
$app->on('GET /admin/dashboard', function() {
    if (!$this->requireAdmin()) {
        return; // Admin helper already sent error response
    }
    return ['admin' => 'data'];
});

// Multiple middleware example
$app->group('/admin', function($app) {
    $app->protect(function($next) {
        if (!$this->isAuthenticated()) {
            return $this->error('Unauthorized', 401);
        }
        if (!$this->isAdmin()) {
            return $this->error('Forbidden', 403);
        }
        return $next();
    });
    
    $app->on('GET /dashboard', function() {
        return ['admin' => 'data'];
    });
});
