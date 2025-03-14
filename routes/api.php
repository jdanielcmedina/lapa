<?php

$app->group('/api', function($app) {
    $app->on('GET /', function() {
        return ['version' => '1.0.0'];
    });
    
    // Protected routes
    $app->on('GET /users', function() {
        if (!$this->auth->require()) {
            return;
        }
        return ['users' => []];
    });
});
