# Sessions & Cookies Guide

## Session Management

### Configuration

Sessions can be configured through the main app configuration:

```php
$app = new \Lapa\Lapa([
    'session' => [
        'name' => 'LAPA_SESSION',
        'lifetime' => 3600,        // 1 hour
        'path' => '/',
        'domain' => null,
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]
]);
```

### Basic Session Operations

```php
// Start or resume a session
$app->session->start();

// Set session data
$app->session->set('user_id', 123);

// Get session data with default fallback
$userId = $app->session->get('user_id', 0);

// Check if session key exists
if ($app->session->has('user_id')) {
    // User is logged in
}

// Remove session data
$app->session->remove('temporary');

// Clear all session data but keep session active
$app->session->clear();

// Destroy session completely
$app->session->destroy();
```

### Flash Messages

Flash messages are temporary data stored for exactly one request:

```php
// Set flash message
$app->session->flash('success', 'Your profile was updated');
$app->session->flash('error', 'Invalid input');

// Get and clear flash message
$success = $app->session->getFlash('success');

// Check if flash message exists
if ($app->session->hasFlash('error')) {
    // Handle error message
}
```

### Security Best Practices

```php
// Regenerate session ID (recommended after login)
$app->session->regenerateId();

// Session timeout handling
if ($app->session->get('last_activity', 0) < time() - 1800) {
    // Session expired (30 minutes of inactivity)
    $app->session->destroy();
} else {
    $app->session->set('last_activity', time());
}
```

## Cookie Management

### Configuration

```php
$app = new \Lapa\Lapa([
    'cookies' => [
        'path' => '/',
        'domain' => null,
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
        'expires' => 86400    // 24 hours default
    ]
]);
```

### Basic Cookie Operations

```php
// Set cookie with default options
$app->cookie->set('theme', 'dark');

// Set cookie with custom options
$app->cookie->set('remember_me', 'token123', [
    'expires' => 30 * 86400,  // 30 days
    'secure' => true,
    'httponly' => true
]);

// Get cookie value
$theme = $app->cookie->get('theme', 'light');  // With default fallback

// Check if cookie exists
if ($app->cookie->has('remember_me')) {
    // Handle remembered user
}

// Remove cookie
$app->cookie->remove('temporary');
```

### Secure Cookies

```php
// Encrypt sensitive data in cookies
$app->cookie->encrypt('auth_token', $token);

// Decrypt cookie data
$token = $app->cookie->decrypt('auth_token');

// Set signed cookie (tamper-proof)
$app->cookie->sign('user_id', 123);

// Verify signed cookie and get value
if ($userId = $app->cookie->verify('user_id')) {
    // Cookie is valid and $userId contains the value
}
```

## Authentication Example

Here's a complete example implementing a simple authentication system:

```php
// Login route
$app->on('POST /login', function() use ($app) {
    $email = $app->post('email');
    $password = $app->post('password');
    $remember = $app->post('remember') ? true : false;
    
    $user = $app->db->get('users', '*', ['email' => $email]);
    
    if ($user && password_verify($password, $user['password'])) {
        // Set session data
        $app->session->start();
        $app->session->set('user_id', $user['id']);
        $app->session->set('user_role', $user['role']);
        $app->session->set('last_activity', time());
        $app->session->regenerateId();
        
        // Set remember cookie if requested
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $app->db->update('users', 
                ['remember_token' => $token], 
                ['id' => $user['id']]
            );
            $app->cookie->encrypt('remember', $token, [
                'expires' => 30 * 86400  // 30 days
            ]);
        }
        
        return ['success' => true, 'redirect' => '/dashboard'];
    }
    
    return ['error' => 'Invalid email or password'];
});

// Auto-login with remember cookie
$app->on('GET /*', function() use ($app) {
    // Already logged in
    if ($app->session->has('user_id')) {
        return;
    }
    
    // Check for remember token
    $token = $app->cookie->decrypt('remember');
    if ($token) {
        $user = $app->db->get('users', '*', ['remember_token' => $token]);
        if ($user) {
            $app->session->start();
            $app->session->set('user_id', $user['id']);
            $app->session->set('user_role', $user['role']);
            $app->session->set('last_activity', time());
            $app->session->regenerateId();
        }
    }
});

// Logout route
$app->on('GET /logout', function() use ($app) {
    $userId = $app->session->get('user_id');
    if ($userId) {
        $app->db->update('users', 
            ['remember_token' => null], 
            ['id' => $userId]
        );
    }
    
    $app->session->destroy();
    $app->cookie->remove('remember');
    
    return ['success' => true, 'redirect' => '/login'];
});
```

## CSRF Protection

Adding CSRF protection to your forms:

```php
// Generate token and store in session
$app->on('GET /form', function() use ($app) {
    $app->session->start();
    $csrfToken = bin2hex(random_bytes(32));
    $app->session->set('csrf_token', $csrfToken);
    
    return $app->view('form', ['csrf_token' => $csrfToken]);
});

// Validate token on form submission
$app->on('POST /form', function() use ($app) {
    $app->session->start();
    $sessionToken = $app->session->get('csrf_token');
    $formToken = $app->post('csrf_token');
    
    if (!$sessionToken || !$formToken || $sessionToken !== $formToken) {
        return ['error' => 'Invalid CSRF token', 'code' => 403];
    }
    
    // Process form...
    return ['success' => true];
});
```
