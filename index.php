<?php
require __DIR__ . '/vendor/autoload.php';

// Initialize app
$app = new Lapa();

// Routes
$app->on('GET /', function() {
    return 'Lapa Framework';
});

