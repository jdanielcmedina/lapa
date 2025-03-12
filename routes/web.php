<?php

// Public routes
$app->on('GET /', function() {
    return ['message' => 'Welcome to the homepage'];
});

$app->on('GET /text', function() {
    return 'Plain text response';
});

$app->on('GET /test', function() {
    return $this->success(['status' => 'working']);
});

