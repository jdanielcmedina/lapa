<?php
// Admin Routes
$router->group('/admin', function($app) {
    $app->on('GET /dashboard', function() {
        $this->protect();
        return ['stats' => 'dashboard data'];
    });
}); 