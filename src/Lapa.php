<?php
namespace Lapa;

/**
 * Lapa Framework Core Class
 * 
 * Core framework class that handles routing, configuration, storage,
 * database connections, mail, plugins and error handling.
 *
 * @package     Lapa
 * @author      Daniel Medina <jdanielcmedina@gmail.com>
 * @copyright   2025 Daniel Medina
 * @license     MIT License
 * @version     1.0.0
 */
class Lapa {
    /**
     * Framework configuration
     * @var array
     */
    private $config;

    /**
     * System paths configuration
     * @var array
     */
    private $paths = [];

    /**
     * Registered application routes
     * @var array
     */
    private $routes = [];

    /**
     * Current route group prefix for nesting routes
     * @var string
     */
    private $currentGroup = '';

    /**
     * Current virtual host for domain routing
     * @var string
     */
    private $currentVhost = '';

    /**
     * Database connection instance
     * @var \Medoo\Medoo|null
     */
    public $db = null;

    /**
     * Email handling instance
     * @var \PHPMailer\PHPMailer\PHPMailer|null
     */
    private $mailer = null;

    /**
     * File storage configuration
     * @var array
     */
    private $storagePaths;

    /**
     * File permission settings
     * @var array
     */
    private $perm = [
        'folder'  => 0755,  // Default folder permissions
        'private' => 0600,  // Restricted file permissions
        'public'  => 0644   // Public file permissions
    ];

    /**
     * Cache handler instance
     * @var mixed
     */
    private $cache = null;

    /**
     * Route not found handlers by group
     * @var array<string,callable>
     */
    private $notFoundHandlers = [];

    /**
     * Fallback route handler
     * @var callable|null
     */
    private $anyHandler = null;

    /**
     * Current route parameters
     * @var array<string,mixed>
     */
    private $currentParams = [];

    /**
     * Current HTTP response code
     * @var int
     */
    private $statusCode = 200;

    private const ERROR_STYLES = '
        body { 
            font-family: -apple-system, system-ui, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #dc3545;
            color: white;
            padding: 2rem;
        }
        .content { padding: 2rem; }
        .error-title {
            font-size: 24px;
            font-weight: 500;
            margin: 0;
        }
        .error-message {
            font-size: 16px;
            margin: 1rem 0;
            color: #666;
        }
        .error-details {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        .stack-trace {
            font-family: monospace;
            font-size: 13px;
            white-space: pre-wrap;
            background: #f1f3f5;
            padding: 1rem;
            border-radius: 4px;
            color: #666;
        }
    ';

    public function __construct($config = [], $database = null) {
        try {
            // Get the root path of the project
            $root = dirname(getcwd()) . DIRECTORY_SEPARATOR;
        
            $this->paths = [
                'root'    => $root,
                'routes'  => $root . 'routes',
                'views'   => $root . 'views',
                'helpers' => $root . 'helpers',  // Added path for helpers
                'storage' => [
                    'cache'   => $root . 'storage/cache',
                    'logs'    => $root . 'storage/logs',
                    'uploads' => $root . 'storage/uploads',
                    'temp'    => $root . 'storage/temp'
                ]
            ];

            // Create storage directories if they don't exist
            foreach ($this->paths['storage'] as $type => $path) {
                if (!is_dir($path)) {
                    @mkdir($path, $this->perm['folder'], true);
                }
                if ($type === 'uploads') {
                    @chmod($path, $this->perm['public']);
                }
            }

            // Add log for debugging the path
            $this->log("Routes path: " . $this->paths['routes'], "debug");

            // Default configurations
            $defaults = [
                'debug' => false,
                'secure' => false,
                'errors' => true,
                'timezone' => 'UTC',
                'upload' => [
                    'max_size' => 5242880, // 5MB
                    'allowed_types' => ['image/jpeg', 'image/png', 'application/pdf']
                ],
                'cache' => ['ttl' => 3600],
                'cors' => [
                    'enabled' => false,
                    'origins' => '*',
                    'methods' => 'GET, POST, PUT, DELETE, OPTIONS, PATCH',
                    'headers' => 'Content-Type, Authorization, X-Requested-With',
                    'credentials' => false
                ]
            ];

            // Merge user config with defaults
            $this->config = array_replace_recursive($defaults, $config);

            // Set database config if provided
            if ($database) {
                $this->config['db'] = $database;
            }

            // Force SSL if secure is true
            $this->secure();

            // Set timezone
            date_default_timezone_set($this->config['timezone'] ?? 'UTC');

            // Initialize components and load resources
            $this->init();
            $this->load('helpers');
            
        } catch (\Throwable $e) {
            $this->debug($e->getMessage(), 500, $e->getTraceAsString());
        }
    }

    private function secure() {
        // If secure is not enabled, return
        if (!($this->config['secure'] ?? false)) {
            return;
        }

        // Check if already in HTTPS
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
            || isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === 443
            || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';

        // Redirect to HTTPS if necessary
        if (!$isSecure && !headers_sent()) {
            $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $url);
            exit();
        }
    }

    public function path($key = null) {
        if ($key === null) {
            return $this->paths;
        }
        
        if (strpos($key, '.') !== false) {
            list($section, $subkey) = explode('.', $key);
            return $this->paths[$section][$subkey] ?? null;
        }
        
        return $this->paths[$key] ?? null;
    }

    private function init() {
        try {
            // Initialize database if configured
            if (isset($this->config['db'])) {
                try {
                    $this->db = new \Medoo\Medoo($this->config['db']);
                } catch (\PDOException $e) {
                    $this->log("DB connection error: " . $e->getMessage(), "error");
                    if ($this->config['debug']) {
                        throw $e;
                    }
                    throw new \Exception("Error connecting to database");
                }
            }
            
            // Initialize mailer if configured
            if (isset($this->config['mail']) && $this->config['mail']['enabled']) {
                try {
                    $this->mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $mailConfig = $this->config['mail'];
                    
                    // Server settings
                    $this->mailer->SMTPDebug = $mailConfig['debug'];
                    $this->mailer->isSMTP();
                    $this->mailer->Host = $mailConfig['host'];
                    $this->mailer->Port = $mailConfig['port'];
                    $this->mailer->SMTPSecure = $mailConfig['secure'];
                    $this->mailer->SMTPAuth = $mailConfig['auth'];
                    $this->mailer->Username = $mailConfig['username'];
                    $this->mailer->Password = $mailConfig['password'];
                    
                    // Default sender
                    $this->mailer->setFrom(
                        $mailConfig['fromEmail'],
                        $mailConfig['fromName']
                    );
                    
                    $this->log("Mailer initialized", "debug");
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    $this->log("Mailer initialization failed: " . $e->getMessage(), "error");
                    throw $e;
                }
            }
            
            // Load routes if not in test mode
            if (!isset($this->config['test'])) {
                $this->load('routes');
                
                // Register request handler
                register_shutdown_function([$this, 'handleRequest']);
            }

            // Load plugins
            $this->loadPlugins();
        } catch (\Throwable $e) {
            $this->debug($e->getMessage(), 500, $e->getTraceAsString());
        }
    }

    /**
     * Load plugins from plugins directory
     * @return void
     */
    private function loadPlugins() {
        $pluginsPath = $this->paths['root'] . 'plugins' . DIRECTORY_SEPARATOR;
        
        if (!is_dir($pluginsPath)) {
            return;
        }

        foreach (new \DirectoryIterator($pluginsPath) as $file) {
            if ($file->isDot() || $file->isDir()) continue;
            if ($file->getExtension() !== 'php') continue;

            $className = 'Lapa\\Plugins\\' . $file->getBasename('.php');
            if (class_exists($className)) {
                $plugin = new $className($this);
                $name = strtolower($file->getBasename('.php'));
                $this->{$name} = $plugin;
                $this->log("Plugin loaded: {$name}", "debug");
            }
        }
    }

    /**
     * Register a route with callback
     *
     * @param string $route Route pattern with HTTP method (e.g., 'GET /users')
     * @param callable $callback Route handler function
     * @return self
     */
    public function on($route, $callback) {
        // Split route into method and path
        $parts = explode(' ', $route, 2);
        $methods = explode('|', $parts[0]);
        $path = $parts[1] ?? '';

        // construct full path
        $fullPath = $path;
        if ($this->currentGroup) {
            $fullPath = rtrim($this->currentGroup, '/') . '/' . ltrim($path, '/');
        }

        // Normalize path (always remove trailing slash except for root '/')
        $fullPath = $fullPath === '/' ? '/' : rtrim($fullPath, '/');

        // Debug only if not in test and debug is enabled
        if (!isset($this->config['test']) && ($this->config['debug'] ?? false)) {
            error_log("Route:");
            error_log("  Method: " . implode('|', $methods));
            error_log("  Path: " . $fullPath);
            error_log("  Group: " . $this->currentGroup);
        }

        // Fazer bind do callback
        $boundCallback = \Closure::bind($callback, $this, get_class($this));

        foreach ($methods as $method) {
            $this->routes[$method][$fullPath] = [
                'callback' => $boundCallback,
                'original' => $fullPath,
                'vhost' => $this->currentVhost
            ];
        }

        return $this;
    }

    /**
     * Register a wildcard route handler
     *
     * @param callable $callback Handler function
     * @return self
     */
    public function any($callback) {
        $this->anyHandler = $callback;
        return $this;
    }

    /**
     * Group routes under a common prefix
     *
     * @param string $prefix URL prefix for the group
     * @param callable $callback Group definition function
     * @return self
     */
    public function group($prefix, $callback) {
        // save current group
        $previousGroup = $this->currentGroup;
        
        // Add new prefix to the current group (always without trailing slash)
        if ($prefix !== '/') {
            $prefix = rtrim($prefix, '/');
        }
        $this->currentGroup = $previousGroup ? rtrim($previousGroup, '/') . '/' . ltrim($prefix, '/') : $prefix;
           
        // Execute callback with the new group
        $callback($this);
        
        // Restore previous group
        $this->currentGroup = $previousGroup;

        return $this;
    }

    /**
     * Group routes under a virtual host
     *
     * @param string $host Virtual host domain
     * @param callable $callback Virtual host routes definition
     * @return self
     */
    public function vhost($host, $callback) {
        $previousVhost = $this->currentVhost;
        $this->currentVhost = $host;
        
        $callback($this);
        
        $this->currentVhost = $previousVhost;
        return $this;
    }

    /**
     * Send unified response
     *
     * @param mixed $data Response data
     * @param string $type Response type (json, text, html, xml)
     * @return null
     */
    public function response($data, $type = 'json', $code = null) {
        if ($code !== null) {
            $this->status($code);
        }

        http_response_code($this->statusCode);

        switch($type) {
            case 'json':
                $this->type('application/json');
                echo is_string($data) ? $data : json_encode($data);
                break;
            case 'text':
                $this->type('text/plain');
                echo $data;
                break;
            case 'html':
                $this->type('text/html');
                echo $data;
                break;
            case 'xml':
                $this->type('application/xml');
                echo $data;
                break;
        }

        $this->statusCode = 200;
        return null;
    }

    /**
     * Send error response
     *
     * @param string|null $message Error message
     * @param int|null $code HTTP status code
     * @return null
     */
    public function error($message = 'Not found', $code = 404) {
        return $this->response($message, 'json', $code);
    }

    /**
     * Send success response
     *
     * @param mixed|null $data Response data
     * @param string $message Success message
     * @return null
     */
    public function success($data = null, $message = 'Success') {
        return $this->response([
            'error' => false,
            'message' => $message,
            'data' => $data
        ], 'json');
    }

    /**
     * Render PHP view template
     *
     * @param string $file View file path
     * @param array $data Data to pass to view
     * @param int|null $code HTTP status code
     * @return null
     * @throws \Exception If view file not found
     */
    public function view($file, $data = [], $code = null) {
        try {
            if ($code !== null) {
                $this->status($code);
            }

            http_response_code($this->statusCode);

            // Add .php extension if not provided
            if (!pathinfo($file, PATHINFO_EXTENSION)) {
                $file .= '.php';
            }

            // Check if absolute path or relative to views directory
            $viewPath = $file;
            if (!file_exists($viewPath)) {
                $viewPath = $this->paths['views'] . '/' . ltrim($file, '/');
            }

            // Check if file exists
            if (!file_exists($viewPath)) {
                throw new \Exception("View not found: {$file}");
            }

            // extract data to variables
            extract($data);

            // Start output buffering
            ob_start();

            try {
                include $viewPath;
                $content = ob_get_clean();
                
                echo $content;
                $this->statusCode = 200;
                            
                return null;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        } catch (\Throwable $e) {
            $this->render($e->getMessage());
        }
    }

    /**
     * Load a partial view
     * 
     * @param String $name Partial name
     * @param mixed $data Data to pass to partial
     * @return void
     */
    public function partial($name, $data = []) {
        // Add .php extension if not provided
        if (!pathinfo($name, PATHINFO_EXTENSION)) {
            $name .= '.php';
        }

        $partialPath = $this->storage('views') . '/partials/' . ltrim($name, '/');
        
        if (!file_exists($partialPath)) {
            throw new \Exception("Partial not found: {$name}");
        }

        extract($data);
        include $partialPath;
    }


    /**
     * Render view with layout
     * 
     * @param string $view View file path
     * @param string $layout Layout file path
     * @param array $data Data to pass to view and layout
     * @param int|null $code HTTP status code
     * @return null
     */
    public function layout($view, $layout = 'default', $data = [], $code = null) {
        if ($code !== null) {
            $this->status($code);
        }

        // Start output buffering
        ob_start();

        // Render view
        $this->view($view, $data);
        
        // Get view content
        $content = ob_get_clean();
        
        // Add content to data
        $data['content'] = $content;
        
        // Render layout with view content
        return $this->view('layouts/' . $layout, $data, $code);
    }

    /**
     * Send raw response
     * 
     * @param mixed $content Raw content to output
     * @param string $type Content type (default: text/html)
     * @return void
     */
    public function raw($content, $type = 'text/html') {
        if (!headers_sent()) {
            $this->type($type);
        }
        echo $content;
        return null;
    }

    /**
     * Redirect to URL
     *
     * @param string $url Target URL
     * @return void
     */
    public function redirect($url) {
        header("Location: $url");
        exit;
    }

    /**
     * Validate input data
     * 
     * @param array $rules Validation rules
     * @param array $data Data to validate (defaults to request data)
     * @return array|false Validated data or false if invalid
     */
    public function validate($rules, $data = null) {
        $data = $data ?? $this->request();
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $rule) {
            // Split rules
            $fieldRules = explode('|', $rule);
            $value = $data[$field] ?? null;
            
            foreach ($fieldRules as $rule) {
                if ($rule === 'required' && empty($value)) {
                    $errors[$field][] = "Field {$field} is required";
                    continue;
                }

                if (strpos($rule, 'min:') === 0) {
                    $min = substr($rule, 4);
                    if (strlen($value) < $min) {
                        $errors[$field][] = "Field {$field} minimum length is {$min}";
                    }
                }
                
                if (strpos($rule, 'max:') === 0) {
                    $max = substr($rule, 4);
                    if (strlen($value) > $max) {
                        $errors[$field][] = "Field {$field} maximum length is {$max}";
                    }
                }

                if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "Field {$field} must be a valid email";
                }

                if ($rule === 'numeric' && !is_numeric($value)) {
                    $errors[$field][] = "Field {$field} must be numeric";
                }
            }

            if (!isset($errors[$field])) {
                $validated[$field] = $value;
            }
        }

        return empty($errors) ? $validated : $this->error(['errors' => $errors], 422);
    }

    /**
     * Get URL parameter
     *
     * @param string $name Parameter name
     * @return mixed Parameter value or null if not found
     */
    public function param($name) {
        if (isset($this->currentParams[$name])) {
            return $this->currentParams[$name];
        }
        return null;
    }

    /**
     * Get request body
     *
     * @param string|null $key Parameter key
     * @return mixed Request body data or specific key value
     */
    public function body($key = null) {
        static $cache = null;
        
        if ($cache === null) {
            $input = file_get_contents('php://input');
            $contentType = $this->header('Content-Type');
            
            // Parse JSON
            if (strpos($contentType, 'application/json') !== false) {
                $cache = json_decode($input, true) ?? [];
            }
            // Parse form data
            else if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                parse_str($input, $cache);
            }
            // Raw data
            else {
                $cache = $input;
            }
        }

        if ($key === null) {
            return $cache;
        }
        
        return is_array($cache) ? ($cache[$key] ?? null) : null;
    }

    /**
     * Get request data (replaces get() and post())
     *
     * @param string|null $key Parameter key
     * @param string|null $method Specific method (get, post, body)
     * @return mixed Parameter value or all parameters if key is null
     */
    public function request($key = null, $method = null) {
        // If method is specified, return data from that method
        if ($method !== null) {
            $data = [];
            switch(strtolower($method)) {
                case 'get': 
                    $data = $_GET;
                    break;
                case 'post': 
                    $data = $_POST;
                    break;
                case 'body': 
                    $data = $this->body();
                    break;
            }
            return $key ? ($data[$key] ?? null) : $data;
        }

        // merge GET, POST and body data
        $data = array_merge(
            $_GET ?? [], 
            $_POST ?? [],
            is_array($this->body()) ? $this->body() : []
        );
        
        return $key ? ($data[$key] ?? null) : $data;
    }

    /**
     * Get request headers
     *
     * @param string|null $key Header key
     * @param string|null $value Header value
     * @return mixed Header value or all headers if key is null
     */
    public function header($key = null, $value = null) {
        // If key is 'destroy', remove all headers
        if ($key === 'destroy') {
            header_remove();
            return $this;
        }
        
        // If key is null, return all headers
        if ($key === null) {
            return getallheaders();
        }

        // If key is an array, set multiple headers
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->header($k, $v);
            }
            return $this;
        }

        // If value is null, return the header value
        if ($value === null) {
            $headers = getallheaders();
            // headers are case-insensitive, so normalize the key
            $key = str_replace(' ', '-', ucwords(str_replace('-', ' ', $key)));
            return $headers[$key] ?? null;
        }

        // If value is false, remove the header
        if ($value === false) {
            header_remove($key);
            return $this;
        }

        // Set the header
        header("$key: $value");
        return $this;
    }

    /**
     * Set response type (replaces contentType())
     * 
     * @param string $type Content type
     * @param string $charset Character encoding
     * @return self
     */
    public function type($type, $charset = 'utf-8') {
        $contentType = $type;
        if ($charset) {
            $contentType .= '; charset=' . $charset;
        }
        return $this->header('Content-Type', $contentType);
    }

    /**
     * Set CORS headers
     *
     * @param string $origins Allowed origins
     * @param string $methods Allowed methods
     * @param string $headers Allowed headers
     * @return self
     */
    public function cors($origins = null, $methods = null, $headers = null) {
        // If CORS is not enabled, return
        if (!($this->config['cors']['enabled'] ?? false)) {
            return $this;
        }

        // Use default values if not provided
        $origins = $origins ?? $this->config['cors']['origins'];
        $methods = $methods ?? $this->config['cors']['methods'];
        $headers = $headers ?? $this->config['cors']['headers'];
        
        $corsHeaders = [
            'Access-Control-Allow-Origin' => $origins,
            'Access-Control-Allow-Methods' => $methods,
            'Access-Control-Allow-Headers' => $headers
        ];

        // Add credentials if enabled
        if ($this->config['cors']['credentials'] ?? false) {
            $corsHeaders['Access-Control-Allow-Credentials'] = 'true';
        }

        return $this->header($corsHeaders);
    }

    /**
     * Get or set HTTP status code
     *
     * @param int|null $code HTTP status code
     * @return self|int
     */
    public function token() {
        $header = $this->header('Authorization');
        if (strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }
        return null;
    }

    /**
     * Get or set cookies
     *
     * @param string|null $key Cookie name
     * @param mixed|null $value Cookie value
     * @param array $options Cookie options
     * @return self
     */
    public function cookie($key = null, $value = null, $options = []) {
        if ($key === 'destroy') {
            foreach ($_COOKIE as $k => $v) {
                setcookie($k, '', time() - 3600, '/');
                unset($_COOKIE[$k]);
            }
            return $this;
        }
        
        if ($key === null) {
            return $_COOKIE;
        }
        
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                if (is_array($v) && isset($v['value'])) {
                    // format ['name' => ['value' => 'value', 'options' => []]]
                    $this->cookie($k, $v['value'], $v);
                } else {
                    // format ['name' => 'value']
                    $this->cookie($k, $v, $options);
                }
            }
            return $this;
        }
        
        if ($value === null) {
            return $_COOKIE[$key] ?? null;
        }
        
        if ($value === false) {
            setcookie($key, '', time() - 3600, '/');
            unset($_COOKIE[$key]);
            return $this;
        }
        
        $defaults = [
            'expire' => 0,           
            'path' => '/',           
            'domain' => '',          
            'secure' => false,       
            'httponly' => true,      
            'samesite' => 'Lax'      
        ];

        $opts = array_merge($defaults, $options);
        
        setcookie(
            $key, 
            $value, 
            [
                'expires' => $opts['expire'],
                'path' => $opts['path'],
                'domain' => $opts['domain'],
                'secure' => $opts['secure'],
                'httponly' => $opts['httponly'],
                'samesite' => $opts['samesite']
            ]
        );
        
        $_COOKIE[$key] = $value;
        
        return $this;
    }

    
    /**
     * Session management
     *
     * @param string|null $key Session key
     * @param mixed|null $value Session value
     * @return self
     */
    public function session($key = null, $value = null) {
        if (!session_id()) session_start();
        
        if ($key === 'destroy') {
            session_destroy();
            $_SESSION = [];
            return $this;
        }
        
        if ($key === null) {
            return $_SESSION;
        }
        
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $_SESSION[$k] = $v;
            }
            return $this;
        }
        
        if ($value === null) {
            return $_SESSION[$key] ?? null;
        }
        
        if ($value === false) {
            unset($_SESSION[$key]);
            return $this;
        }

        $_SESSION[$key] = $value;
        return $this;
    }

    /**
     * Auxiliary method for flash messages
     *
     * @param string $key Message key
     * @param mixed|null $value Message value
     * @return self
     */
    public function flash($key, $value = null) {
        if ($value === null) {
            $value = $this->session($key);
            $this->session($key, false);
            return $value;
        }
        return $this->session($key, $value);
    }

    /**
     * Get database connection instance
     *
     * @return \Medoo\Medoo Database connection
     * @throws \Exception If database configuration is invalid
     */
    public function db() {
        if ($this->db === null && isset($this->config['db'])) {
            try {
                $dbConfig = $this->config['db'];
                switch($dbConfig['type']) {
                    case 'sqlite':
                        $dbPath = $this->storage('private') . '/database.sqlite';
                        $dbConfig = [
                            'type' => 'sqlite',
                            'database' => $dbPath
                        ];
                        break;
                    case 'pgsql':
                    case 'postgresql':
                        $requiredParams = ['host', 'database', 'username', 'password'];
                        $dbConfig['type'] = 'pgsql';
                        break;
                        
                    case 'mssql':
                        $requiredParams = ['host', 'database', 'username', 'password'];
                        $dbConfig['type'] = 'mssql';
                        break;
                        
                    case 'sybase':
                        $requiredParams = ['host', 'database', 'username', 'password'];
                        $dbConfig['type'] = 'sybase';
                        break;
                        
                    case 'mysql':
                    default:
                        $requiredParams = ['host', 'database', 'username', 'password'];
                        $dbConfig['type'] = 'mysql';
                        break;
                }

                if (isset($requiredParams)) {
                    foreach ($requiredParams as $param) {
                        if (!isset($dbConfig[$param])) {
                            throw new \Exception("Missing DB parameter: {$param}");
                        }
                    }
                }

                if (!isset($dbConfig['charset'])) {
                    $dbConfig['charset'] = 'utf8mb4';
                }

                $this->db = new \Medoo\Medoo($dbConfig);
                $this->db->query("SELECT 1")->fetch();
                       
            } catch (\PDOException $e) {
                $this->log("DB connection error: " . $e->getMessage(), "error");
                if ($this->config['debug']) {
                    throw $e;
                }
                throw new \Exception("Error connecting to database");
            }
        }
        return $this->db;
    }

    public function mail() {
        // if mailer is not initialized, return null
        return $this->mailer;
    }

    /**
     * Cache handler
     *
     * @param string $key Cache key
     * @param mixed|null $value Cache value
     * @param int|null $ttl Cache TTL
     * @return self
     */
    public function cache($key, $value = null, $ttl = null) {
        $cacheFile = $this->storage('cache') . '/' . md5($key) . '.cache';
        
        if ($value === null) {
            if (!file_exists($cacheFile)) {
                return null;
            }
            $data = json_decode(file_get_contents($cacheFile), true);
            
            if ($data['expires'] < time()) {
                unlink($cacheFile);
                return null;
            }

            return $data['value'];
        }

        $ttl = $ttl ?? $this->config['cache']['ttl'] ?? 3600;
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];

        if (is_dir(dirname($cacheFile))) {
            file_put_contents($cacheFile, json_encode($data));
        }
        return $this;
    }

    /**
     * Upload a file to storage
     *
     * @param string $field Form field name
     * @param string|null $path Custom storage path
     * @return string|false Filename on success, false on failure
     */
    public function upload($field, $path = null) {
        if (!isset($_FILES[$field])) {
            return false;
        }

        $file = $_FILES[$field];
        $path = $path ?? $this->storage('uploads');
        
        if (!is_dir($path)) {
            return false;
        }
        
        // Validate file size
        $maxSize = $this->config['upload']['max_size'] ?? 5242880;
        if ($file['size'] > $maxSize) {
            $this->log("Upload rejected: file too large", "error");
            return false;
        }

        // Validate mime type
        $allowedTypes = $this->config['upload']['allowed_types'] ?? [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            $this->log("Upload rejected: invalid file type", "error");
            return false;
        }

        // Generate safe filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $fullPath = $path . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            @chmod($fullPath, $this->perm['public']);
            $this->log("File uploaded: $filename", "info");
            return $filename;
        }

        $this->log("Upload error: " . $file['error'], "error");
        return false;
    }

    public function download($file, $name = null) {
        // Check if absolute or relative to storage path
        $path = $file;
        if (!file_exists($path)) {
            $path = $this->storage('public') . '/' . $file;
        }
        
        if (!file_exists($path)) {
            $this->log("File not found: $file", "error");
            return false;
        }

        $name = $name ?? basename($path);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache');
        readfile($path);
        exit;
    }

    /**
     * Log message to file
     * @param string $message Message to log
     * @param string $level Log level
     * @return self
     */
    public function log($message, $level = 'info') {
        if (!isset($this->paths['storage']['logs'])) {
            return $this;
        }

        $logFile = $this->paths['storage']['logs'] . '/app.log';
        $date = date('Y-m-d H:i:s');
        $log = "[$date] [$level] $message" . PHP_EOL;
        
        @file_put_contents($logFile, $log, FILE_APPEND);
        return $this;
    }

    /**
     * Clear storage
     *
     * @param string $type Storage type
     * @return self
     */
    public function clear($type = 'cache') {
        $path = $this->storage($type);
        if (!is_dir($path)) {
            return $this;
        }

        $files = glob($path . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->log("Storage $type cleared", "info");
        return $this;
    }

    /**
     * Get configuration value
     *
     * @param string|null $key Configuration key
     * @return mixed Configuration value or all config if key is null
     */
    public function config($key = null) {
        return $key ? ($this->config[$key] ?? null) : $this->config;
    }

    /**
     * Set HTTP status code
     *
     * @param int $code HTTP status code
     * @return self
     */
    public function status($code) {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Get time ago in human readable format
     *
     * @param string $date Date string
     * @return string Time ago
     */
    public function ago($date) {
        $date = strtotime($date);
        $now = time();
        $difference = $now - $date;

        if ($difference < 60) {
            return 'just now';
        }

        $periods = [
            'year' => 31536000,
            'month' => 2592000,
            'week' => 604800,
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1
        ];

        foreach ($periods as $name => $seconds) {
            $units = floor($difference / $seconds);
            if ($units > 0) {
                return "$units $name" . ($units > 1 ? 's' : '') . ' ago';
            }
        }

        return 'just now';
    }

    /**
     * Generate random string
     * 
     * @param int $length String length
     * @param string $type Type of string (alpha, numeric, alphanumeric)
     * @return string Random string
     */
    public function random($length = 16, $type = 'alphanumeric') {
        $string = '';
        $characters = '';

        switch ($type) {
            case 'alpha':
                $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'numeric':
                $characters = '0123456789';
                break;
            case 'alphanumeric':
                $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                break;
        }

        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $string;
    }

    /**
     * Generate a slug from a string
     *
     * @param string $text String to slugify
     * @return string Slugified string
     */
    public function slug($text) {
        $string = strtolower($text);
        // Remover caracteres especiais
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        // Remover hífens duplicados
        $string = preg_replace('/-+/', '-', $string);
        // Remover hífens no início e fim
        return trim($string, '-');
    }

    /**
     * Calculate distance between two points
     * 
     * @param float $lat1 First point latitude
     * @param float $lon1 First point longitude
     * @param float $lat2 Second point latitude
     * @param float $lon2 Second point longitude
     * @param string $unit Unit of measurement (K = Kilometers, M = Miles, N = Nautical Miles)
     * @return float Distance between points
     */
    public function distance($lat1, $lon1, $lat2, $lon2, $unit = 'K') {
        if (($lat1 == $lat2) && ($lon1 == $lon2)) {
            return 0;
        }

        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + 
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        switch(strtoupper($unit)) {
            case 'K': // Kilometers
                return round($miles * 1.609344, 2);
            case 'N': // Nautical Miles
                return round($miles * 0.8684, 2);
            case 'M': // Miles
            default:
                return round($miles, 2);
        }
    }

    /**
     * Remove specific characters from string
     * 
     * @param string $string Input string
     * @param array|string $chars Characters to remove
     * @return string Cleaned string
     */
    public function clean($string, $chars = []) {
        if (is_string($chars)) {
            $chars = str_split($chars);
        }

        if (empty($chars)) {
            // Default characters to remove if none specified
            $chars = [
                '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', 
                '+', '=', '{', '}', '[', ']', ':', ';', '"', "'",
                '<', '>', '?', '/', '\\', '|', '`', '~'
            ];
        }

        return str_replace($chars, '', $string);
    }

    /**
     * Load resources recursively (routes, helpers, etc)
     * @param string $type Resource type to load (routes|helpers)
     * @return self
     */
    public function load($type) {
        $basePath = $this->paths[$type] ?? null;
        
        if (!$basePath) {
            $this->log("[ERROR] Path not defined for type: $type", "error");
            return $this;
        }

        $basePath = realpath($basePath);
        if (!$basePath || !is_dir($basePath)) {
            $this->log("[ERROR] Invalid directory: $basePath", "error");
            return $this;
        }

        $files = scandir($basePath);
        
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $fullPath = $basePath . DIRECTORY_SEPARATOR . $file;
                try {
                    $this->log("Loading $type file: $fullPath", "debug");
                    $app = $this;
                    require_once $fullPath;
                } catch (\Throwable $e) {
                    $this->log("Failed loading $file: " . $e->getMessage(), "error");
                }
            }
        }

        return $this;
    }

    // Método para obter path do storage
    public function storage($type = 'app') {
        return $this->paths['storage'][$type] ?? $this->paths['root'];
    }

    /**
     * Cleanup storage
     *
     * @param int $maxAge Maximum age in seconds
     * @return self
     */
    public function cleanup($maxAge = 86400) { // 24 hours
        $paths = ['temp', 'cache'];
        
        foreach ($paths as $type) {
            $path = $this->storage($type);
            if (!is_dir($path)) continue;

            foreach (new \DirectoryIterator($path) as $file) {
                if ($file->isDot()) continue;
                if ($file->getMTime() < time() - $maxAge) {
                    unlink($file->getPathname());
                }
            }
        }
        
        $this->log("Automatic cleanup executed", "info");
        return $this;
    }

    /**
     * Rename/move a file or directory in storage
     *
     * @param string $from Source path (relative to storage)
     * @param string $to Target path (relative to storage) 
     * @param string $type Storage type (app, public, private, etc)
     * @return bool Success status
     */
    public function rename($from, $to, $type = 'app') {
        $sourcePath = $this->storage($type) . '/' . $from;
        $targetPath = $this->storage($type) . '/' . $to;
        
        // Check if source exists
        if (!file_exists($sourcePath)) {
            $this->log("Source not found: $from", "error");
            return false;
        }
        
        // Check if target directory exists
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, $this->perm['folder'], true);
        }
        
        // Perform rename/move
        if (rename($sourcePath, $targetPath)) {
            // Apply correct permissions
            if (is_file($targetPath)) {
                $perm = strpos($type, 'private') !== false ? 
                    $this->perm['private'] : 
                    $this->perm['public'];
                chmod($targetPath, $perm);
            } else {
                chmod($targetPath, $this->perm['folder']);
            }
            
            $this->log("Renamed: $from -> $to", "info");
            return true;
        }
        
        return false;
    }

    /**
     * Debug method
     *
     * @return void
     */
    public function debug($message, $code = 500, $details = null) {
        // CLI output
        if (php_sapi_name() === 'cli') {
            echo "\n[ERROR] $message\n";
            if ($details && $this->config['debug']) {
                echo "$details\n";
            }
            exit($code);
        }

        // HTTP headers
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: text/html; charset=utf-8');
        }

        // Em produção, erros 500+ mostram mensagem genérica
        $showMessage = $code < 500 || $this->config['debug'] 
            ? $message 
            : 'An unexpected error occurred. Please try again later.';

        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Error ' . $code . '</title>
            <style>' . self::ERROR_STYLES . '</style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1 class="error-title">Error ' . $code . '</h1>
                </div>
                <div class="content">
                    <div class="error-message">' . $showMessage . '</div>
                    ' . ($this->config['debug'] && $details ? '
                    <div class="error-details">
                        <div class="stack-trace">' . $details . '</div>
                    </div>
                    ' : '') . '
                </div>
            </div>
        </body>
        </html>';
        
        exit($code);
    }

    public function handleRequest() {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $uri = $uri === '/' ? '/' : rtrim($uri, '/');
            $host = $_SERVER['HTTP_HOST'] ?? '';

            if (isset($this->routes[$method])) {
                foreach ($this->routes[$method] as $pattern => $route) {
                    // Verificar virtual host
                    if (!empty($route['vhost']) && $route['vhost'] !== $host) {
                        continue;
                    }

                    // Match route pattern
                    $regex = preg_replace('/:[a-zA-Z]+/', '([^/]+)', $pattern);
                    $regex = str_replace('/', '\/', $regex);
                    $regex = '/^' . $regex . '$/';

                    if (preg_match($regex, $uri, $matches)) {
                        // Extract params
                        preg_match_all('/:([a-zA-Z]+)/', $pattern, $paramNames);
                        array_shift($matches);
                        $this->currentParams = array_combine($paramNames[1] ?? [], $matches);
                        
                        // Execute route callback
                        $response = $route['callback']($this);
                        
                        // Handle response
                        if ($response !== null) {
                            if (is_array($response)) {
                                if (!headers_sent()) {
                                    header('Content-Type: application/json; charset=utf-8');
                                }
                                echo json_encode($response);
                            } else {
                                echo $response;
                            }
                        }
                        return;
                    }
                }
            }

            // Nenhuma rota encontrada
            $this->debug('Not Found', 404);
            
        } catch (\Throwable $e) {
            $this->debug($e->getMessage(), 500, $e->getTraceAsString());
        }
    }

    /**
     * Register not found handler for current group
     * 
     * @param callable $handler
     * @return self
     */
    public function notFound($handler) {
        $group = $this->currentGroup ?: '/';
        $this->notFoundHandlers[$group] = $handler;
        return $this;
    }

    private function handleNotFound($uri) {
        // Find most specific handler
        $handler = null;
        $longestMatch = 0;

        // Procura o handler mais específico baseado no grupo
        foreach ($this->notFoundHandlers as $groupPrefix => $groupHandler) {
            if (strpos($uri, $groupPrefix) === 0) {
                $prefixLength = strlen($groupPrefix);
                if ($prefixLength > $longestMatch) {
                    $longestMatch = $prefixLength;
                    $handler = $groupHandler;
                }
            }
        }

        // Se encontrou um handler, executa
        if ($handler) {
            $response = $handler($this);
            if ($response !== null) {
                return $this->send($response);
            }
        }

        // Handler padrão
        return $this->error('Not Found', 404);
    }

    private function matchVhost($route) {
        if (empty($route['vhost'])) {
            return true;
        }
        
        $host = $_SERVER['HTTP_HOST'] ?? '';
        return fnmatch($route['vhost'], $host);
    }

    /**
     * Get value from $_GET
     * @param string|null $key Key to get
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get($key = null, $default = null) {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    /**
     * Get value from $_POST
     * @param string|null $key Key to get
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function post($key = null, $default = null) {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }

    /**
     * Import data from external API
     * @param string $url API URL
     * @param array $options Request options
     * @return mixed Response data
     */
    public function import($url, $options = []) {
        try {
            $defaults = [
                'method' => 'GET',
                'headers' => [],
                'data' => null,
                'timeout' => 30,
                'verify_ssl' => true,
                'json' => true
            ];

            $opts = array_merge($defaults, $options);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $opts['timeout']);
            
            if (!$opts['verify_ssl']) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            }
            
            // Set method
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($opts['method']));
            
            // Set headers
            if (!empty($opts['headers'])) {
                $headers = [];
                foreach ($opts['headers'] as $key => $value) {
                    $headers[] = "$key: $value";
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            
            // Set POST/PUT data
            if ($opts['data']) {
                $data = is_array($opts['data']) ? http_build_query($opts['data']) : $opts['data'];
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("API request failed: $error");
            }
            
            return $opts['json'] ? json_decode($response, true) : $response;
        } catch (\Throwable $e) {
            $this->debug($e->getMessage(), 500, $e->getTraceAsString());
        }
    }


    /**
     * Generate API documentation from route files
     * @param string|null $routesPath Custom routes path
     * @return string Generated HTML documentation
     */
    public function docs($routesPath = null) {
        $routesPath = $routesPath ?? $this->paths['routes'] ?? dirname(__DIR__) . '/routes';
        $docs = [];
        
        if (!is_dir($routesPath)) {
            return '<h1>No routes directory found</h1>';
        }

        // Scan route files and extract documentation
        foreach (glob($routesPath . '/*.php') as $file) {
            $content = @file_get_contents($file);
            if (!$content) continue;

            preg_match_all('/@api\s+(.*?)\s*\*\//s', $content, $matches);
            if (empty($matches[1])) continue;

            // Parse each documentation block
            foreach ($matches[1] as $block) {
                if (empty($block)) continue;
                
                $endpoint = [];
                foreach (explode("\n", $block) as $line) {
                    if (empty($line)) continue;

                    // Extract API method, path and description
                    if (preg_match('/@api\s+{(\w+)}\s+([^\s]+)\s+(.*)/', $line, $m)) {
                        $endpoint = [
                            'method' => $m[1] ?? 'GET',
                            'path' => $m[2] ?? '/',
                            'description' => $m[3] ?? ''
                        ];
                    }
                    // Extract parameters
                    if (preg_match('/@apiParam\s+{([^}]+)}\s+([^\s]+)\s+(.*)/', $line, $m)) {
                        if (!isset($endpoint['params'])) $endpoint['params'] = [];
                        $endpoint['params'][] = [
                            'type' => $m[1] ?? 'mixed',
                            'name' => $m[2] ?? '',
                            'description' => $m[3] ?? ''
                        ];
                    }
                    // Extract success responses
                    if (preg_match('/@apiSuccess\s+{([^}]+)}\s+([^\s]+)\s+(.*)/', $line, $m)) {
                        if (!isset($endpoint['success'])) $endpoint['success'] = [];
                        $endpoint['success'][] = [
                            'type' => $m[1] ?? 'mixed',
                            'name' => $m[2] ?? '',
                            'description' => $m[3] ?? ''
                        ];
                    }
                }
                if (!empty($endpoint)) $docs[] = $endpoint;
            }
        }

        // Generate HTML documentation
        $html = '<html><head><title>API Documentation</title><style>' 
             . self::ERROR_STYLES 
             . '.endpoint{margin-bottom:2rem}'
             . '.method{display:inline-block;padding:3px 8px;border-radius:4px}'
             . '.method.get{background:#61affe}.method.post{background:#49cc90}'
             . '.method.put{background:#fca130}.method.delete{background:#f93e3e}'
             . '.params table{width:100%;border-collapse:collapse}'
             . '.params td,.params th{padding:8px;border:1px solid #ddd}'
             . '</style></head><body><div class="container">';

        foreach ($docs as $endpoint) {
            $html .= '<div class="endpoint">'
                  . '<h2><span class="method ' . strtolower($endpoint['method']) . '">' 
                  . $endpoint['method'] . '</span> ' . $endpoint['path'] . '</h2>'
                  . '<p>' . $endpoint['description'] . '</p>';

            if (!empty($endpoint['params'])) {
                $html .= '<h3>Parameters</h3><div class="params"><table>'
                      . '<tr><th>Name</th><th>Type</th><th>Description</th></tr>';
                foreach ($endpoint['params'] as $param) {
                    $html .= '<tr><td>' . $param['name'] . '</td><td>' . $param['type'] 
                          . '</td><td>' . $param['description'] . '</td></tr>';
                }
                $html .= '</table></div>';
            }
            $html .= '</div>';
        }

        return $html . '</div></body></html>';
    }

    /**
     * Magic method to handle dynamic function calls
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments) {
        if (!isset($this->{$name})) {
            throw new \Exception("Method {$name}() does not exist");
        }
        
        if (!is_callable($this->{$name})) {
            throw new \Exception("Property {$name} exists but is not callable");
        }

        return call_user_func_array($this->{$name}, $arguments);
    }

    /**
     * Magic method to handle property assignment
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value) {
        if (isset($this->{$name})) {
            throw new \Exception("Helper '{$name}' already exists");
        }
        $this->{$name} = $value;
    }
}

