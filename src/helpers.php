<?php

$app->hello = \Closure::bind(function($name = 'World') {
    return "Hello, $name!";
}, $app, get_class($app));

$app->formatDate = \Closure::bind(function($date = null, $format = 'd/m/Y') {
    return date($format, strtotime($date ?? 'now'));
}, $app, get_class($app));

$app->isAdmin = \Closure::bind(function() {
    return $this->session('user_role') === 'admin';
}, $app, get_class($app));

$app->fnteste = \Closure::bind(function() {
    return "Teste funcionando!";
}, $app, get_class($app));

$app->minhaFuncao = function($param) {
    return "Minha função com: " . $param;
};

$app->outraFuncao = function() {
    return "Outra função";
};

$app->isAuthenticated = function() {
    $token = $this->token();
    return $token && $this->validateJWT($token);
};

$app->validateJWT = function($token) {
    // Simple JWT validation example
    try {
        $key = $this->config('app.key');
        $decoded = \Firebase\JWT\JWT::decode($token, $key, ['HS256']);
        return $decoded && $decoded->user_id;
    } catch (\Exception $e) {
        return false;
    }
};

// Authentication helper
$app->requireAuth = \Closure::bind(function($next = null) {
    if (!$this->isAuthenticated()) {
        return $this->error('Unauthorized', 401);
    }
    return $next ? $next() : true;
}, $app, get_class($app));

// Admin protection helper
$app->requireAdmin = \Closure::bind(function($next = null) {
    if (!$this->requireAuth()) {
        return false;
    }
    if (!$this->isAdmin()) {
        return $this->error('Forbidden', 403);
    }
    return $next ? $next() : true;
}, $app, get_class($app));
