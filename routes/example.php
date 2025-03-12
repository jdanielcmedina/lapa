<?php

$app->on('GET /hello', function() {
    return $this->hello('User');  // Uses hello() helper
});

$app->on('GET /test', function() {
    return $this->outraFuncao();  // Uses fnteste() helper
});

$app->on('GET /date', function() {
    return [
        'date' => $this->formatDate(),  // Uses formatDate() helper
        'isAdmin' => $this->isAdmin()   // Uses isAdmin() helper
    ];
});

$app->on('GET /data', function() {
    $date = $this->formatDate('2023-12-25');
    return ['date' => $date];
});

$app->on('GET /check', function() {
    return ['status' => $this->isAdmin() ? 'admin' : 'user'];
});

$app->on('POST /example', function() {
    // Using request() with specific method
    $name = $this->request('name', 'post');
    
    // Using direct post() method
    $email = $this->post('email');
    
    // Getting all POST data
    $allPost = $this->request(null, 'post');
    // or
    $allPost2 = $this->post();
    
    // With default value if not exists
    $age = $this->post('age', 18);
    
    return $this->success([
        'name' => $name,
        'email' => $email,
        'age' => $age,
        'all' => $allPost
    ]);
});

$app->on('POST /form', function() {
    // Get ALL POST data
    $allPostData = $this->post();
    
    // Get ALL GET data
    $allGetData = $this->get();
    
    // You can also do:
    $allPost = $this->request(null, 'post');  // Same as $this->post()
    $allGet = $this->request(null, 'get');    // Same as $this->get()
    
    return $this->success([
        'post_data' => $allPostData,
        'get_data' => $allGetData
    ]);
});

$app->on('GET /search', function() {
    // If URL is /search?name=john&age=25
    $allParams = $this->get();  // Returns ['name' => 'john', 'age' => '25']
    
    // Or get specific values
    $name = $this->get('name');     // Returns 'john'
    $age = $this->get('age', 18);   // Returns '25' (18 would be default)
    
    return $allParams;
});

// Import from external API with GET
$app->on('GET /external', function() {
    $data = $this->import('https://api.example.com/data', [
        'headers' => [
            'Authorization' => 'Bearer ' . $this->token(),
            'Accept' => 'application/json'
        ]
    ]);
    
    return $this->success($data);
});

// Import with POST and custom data
$app->on('POST /external', function() {
    $result = $this->import('https://api.example.com/create', [
        'method' => 'POST',
        'headers' => [
            'Content-Type' => 'application/json',
            'X-API-Key' => 'your-api-key'
        ],
        'data' => [
            'name' => $this->post('name'),
            'email' => $this->post('email')
        ]
    ]);
    
    return $this->success($result);
});

// Import raw XML data
$app->on('GET /external/xml', function() {
    $xml = $this->import('https://api.example.com/xml', [
        'json' => false,
        'headers' => ['Accept' => 'application/xml']
    ]);
    
    return $this->response($xml, 'xml');
});

// Register a named middleware
$app->register('auth', function($next) {
    if (!$this->token()) {
        return $this->error('Unauthorized', 401);
    }
    return $next();
});

// Use middleware in routes
$app->group('/api', function($app) {
    // Apply auth middleware to all routes in this group
    $app->use($app->middleware('auth'));
    
    $app->on('GET /users', function() {
        return ['users' => []];
    });
});

// Multiple middleware example
$app->group('/admin', function($app) {
    $app->use($app->middleware('auth')); // Check authentication
    $app->use(function($next) {         // Check admin role
        if (!$this->isAdmin()) {
            return $this->error('Forbidden', 403);
        }
        return $next();
    });
    
    $app->on('GET /dashboard', function() {
        return ['admin' => 'data'];
    });
});
