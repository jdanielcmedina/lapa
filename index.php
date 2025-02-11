<?php
require __DIR__ . '/vendor/autoload.php';

// Initialize app with error reporting in dev
error_reporting(E_ALL);
ini_set('display_errors', 1);

$app = new Lapa\Lapa();

// Routes
$app->on('GET /', function() {
    return 'Lapa Framework';
});

