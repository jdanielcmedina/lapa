<?php
// API Routes
$router->group('/api', function($app) {
    $app->on('GET /test', function() {
        return ['message' => 'test'];
    });
    
    $app->on('GET /status', function() {
        return ['status' => 'online'];
    });
    
    $app->on('POST /users', function() {
        return ['message' => 'User created'];
    });

    $app->notFound(function() {
        return ['error' => 'API endpoint not found'];
    });
}); 