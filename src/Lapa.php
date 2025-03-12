<?php
namespace Lapa;

/**
 * Lapa Framework
 * A minimalist PHP framework for building REST APIs and web applications
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
     * Registered routes
     * @var array
     */
    private $routes = [];

    /**
     * Current route group prefix
     * @var string
     */
    private $currentGroup = '';

    /**
     * Current virtual host
     * @var string
     */
    private $currentVhost = '';

    /**
     * Database connection instance
     * @var \Medoo\Medoo|null
     */
    public $db = null;

    /**
     * Mailer instance
     * @var \PHPMailer\PHPMailer\PHPMailer|null
     */
    private $mailer = null;

    /**
     * Storage paths configuration
     * @var array
     */
    private $storagePaths;

    /**
     * File permissions for different storage types
     * @var array
     */
    private $perm;

    /**
     * Cache handler instance
     * @var mixed
     */
    private $cache = null;

    /**
     * Not found handlers by group
     * @var array
     */
    private $notFoundHandlers = [];

    /**
     * Wildcard route handler
     * @var callable|null
     */
    private $anyHandler = null;

    /**
     * Current route parameters
     * @var array
     */
    private $currentParams = [];

    /**
     * Current HTTP status code
     * @var int
     */
    private $statusCode = 200;

    public function __construct($testConfig = null) {
        try {
            // Define core paths and constants
            if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
            if (!defined('ENV')) define('ENV', getenv('APP_ENV') ?: 'production');
            
            // Define base paths with DS at the end
            define('ROOT', dirname(__DIR__) . DS);
            define('APP', ROOT . 'src' . DS);
            define('STORAGE', ROOT . 'storage' . DS);
            define('CONFIG', STORAGE . 'app' . DS);
            define('ROUTES', ROOT . 'routes' . DS);
            define('VIEWS', ROOT . 'views' . DS);
            define('CACHE', STORAGE . 'cache' . DS);
            define('LOGS', STORAGE . 'logs' . DS);
            define('UPLOADS', STORAGE . 'uploads' . DS);
            define('TEMP', STORAGE . 'temp' . DS);
            define('EXT', '.php');

            // Set storage permissions
            $this->perm = [
                'public' => 0644,
                'private' => 0600,
                'folder' => 0755
            ];

            // Set storage paths using constants
            $this->storagePaths = [
                'app' => CONFIG,          
                'logs' => LOGS,
                'cache' => CACHE,
                'temp' => TEMP,
                'uploads' => UPLOADS,
                'views' => VIEWS
            ];

            // Create required directories with proper permissions
            foreach ($this->storagePaths as $type => $path) {
                if (!is_dir($path)) {
                    @mkdir($path, $this->perm['folder'], true);
                }
                
                // Apply specific permissions
                if ($type === 'app') {
                    @chmod($path, $this->perm['private']);
                    @file_put_contents($path . DS . '.htaccess', 'Deny from all');
                } else if ($type === 'uploads') {
                    @chmod($path, $this->perm['public']);
                }
            }

            // Handle configuration
            if ($testConfig) {
                $this->config = $testConfig;
            } else {
                $configFile = CONFIG . DS . 'config' . EXT;
                if (!file_exists($configFile)) {
                    $examplePath = APP . 'config.example' . EXT;
                    if (!file_exists($examplePath)) {
                        throw new \Exception('Configuration example file not found');
                    }
                    $this->createDefaultConfig($configFile, $examplePath);
                }
                $this->config = require $configFile;
            }

            // Initialize components
            $this->initializeComponents();
            
        } catch (\Throwable $e) {
            if ($this->config['debug'] ?? false) {
                throw $e;
            }
            die("Internal server error");
        }
    }

    private function createDefaultConfig($configPath, $examplePath) {
        // Ensure directory exists
        $configDir = dirname($configPath);
        if (!is_dir($configDir)) {
            mkdir($configDir, $this->perm['folder'], true);
            chmod($configDir, $this->perm['private']);
        }

        // Copy example config
        copy($examplePath, $configPath);
        chmod($configPath, $this->perm['private']);
    }

    private function checkStorage() {
        $base = dirname(__DIR__);
        
        // Create basic structure
        foreach ($this->storagePaths as $type => $path) {
            $fullPath = $base . '/' . $path;
            if (!is_dir($fullPath)) {
                @mkdir($fullPath, $this->perm['folder'], true);
            }

            // Apply specific permissions
            if ($type === 'private') {
                @chmod($fullPath, $this->perm['private']);
            } else if ($type === 'public' || $type === 'uploads') {
                @chmod($fullPath, $this->perm['public']);
            }
        }

        // Protect private folder
        $privatePath = $base . '/' . $this->storagePaths['private'];
        @file_put_contents($privatePath . '/.htaccess', 'Deny from all');
    }

    /**
     * Check if application is configured
     * @return bool
     */
    public function isConfigured() {
        return file_exists(CONFIG . DS . 'config' . EXT);
    }

    private function initializeComponents() {
        // Initialize database if configured
        if (isset($this->config['db'])) {
            try {
                $this->db = new \Medoo\Medoo($this->config['db']);
            } catch (\PDOException $e) {
                throw new \Exception("Database connection failed: " . $e->getMessage());
            }
        }
        
        // Initialize mailer if configured
        if (isset($this->config['mail'])) {
            $this->mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            // Configure mailer from config
            if (isset($this->config['mail']['host'])) {
                $this->mailer->Host = $this->config['mail']['host'];
                $this->mailer->Port = $this->config['mail']['port'] ?? 587;
                $this->mailer->Username = $this->config['mail']['username'] ?? '';
                $this->mailer->Password = $this->config['mail']['password'] ?? '';
                $this->mailer->SMTPAuth = true;
                $this->mailer->SMTPSecure = 'tls';
            }
        }
        
        // Load routes if not in test mode
        if (!isset($this->config['test'])) {
            $this->loadRoutes();
            
            // Register request handler
            register_shutdown_function([$this, 'handleRequest']);
        }

        // Load plugins
        $this->loadPlugins();
    }

    /**
     * Load plugins from plugins directory
     * @return void
     */
    private function loadPlugins() {
        $pluginsPath = APP . 'plugins' . DS;
        
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
        // Separar método e rota
        $parts = explode(' ', $route, 2);
        $methods = explode('|', $parts[0]);
        $path = $parts[1] ?? '';

        // Construir caminho completo com grupo
        $fullPath = $this->currentGroup ? 
                   rtrim($this->currentGroup, '/') . '/' . ltrim($path, '/') :
                   $path;

        // Normalizar path (mantém barra final se existir no original)
        $fullPath = '/' . trim($path, '/');
        if ($path !== '/' && substr($path, -1) === '/') {
            $fullPath .= '/';
        }

        // Debug apenas se não estiver em teste e debug ativado
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
        // Guardar grupo atual
        $previousGroup = $this->currentGroup;
        
        // Adicionar novo prefixo ao grupo atual
        $this->currentGroup = $previousGroup . '/' . trim($prefix, '/');
           
        // Executar callback com o novo grupo
        $callback($this);
        
        // Restaurar grupo anterior
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
            $viewPath = $this->storage('views') . '/' . ltrim($file, '/');
        }

        // Check if file exists
        if (!file_exists($viewPath)) {
            throw new \Exception("View not found: {$file}");
        }

        // Extrair variáveis para o escopo local
        extract($data);

        // Iniciar buffer de output
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
        // Se especificar método, retorna dados específicos
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

        // Combina todos os dados
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
        // Se key for 'destroy', remove todos os headers definidos
        if ($key === 'destroy') {
            header_remove();
            return $this;
        }
        
        // Se não passar key, retorna todos os headers
        if ($key === null) {
            return getallheaders();
        }

        // Se for array, define múltiplos headers
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->header($k, $v);
            }
            return $this;
        }

        // Se value for null, obtém valor do header
        if ($value === null) {
            $headers = getallheaders();
            // Headers são case-insensitive
            $key = str_replace(' ', '-', ucwords(str_replace('-', ' ', $key)));
            return $headers[$key] ?? null;
        }

        // Se value for false, remove o header
        if ($value === false) {
            header_remove($key);
            return $this;
        }

        // Define header
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
    public function cors($origins = '*', $methods = 'GET, POST, OPTIONS', $headers = '') {
        return $this->header([
            'Access-Control-Allow-Origin' => $origins,
            'Access-Control-Allow-Methods' => $methods,
            'Access-Control-Allow-Headers' => $headers
        ]);
    }

    /**
     * Get bearer token from Authorization header
     *
     * @return string|null Bearer token or null if not found
     */
    public function token() {
        $header = $this->header('Authorization');
        if (strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }
        return null;
    }

    // Cookies - gestão completa
    public function cookie($key = null, $value = null, $options = []) {
        // Se key for 'destroy', remove todos os cookies
        if ($key === 'destroy') {
            foreach ($_COOKIE as $k => $v) {
                setcookie($k, '', time() - 3600, '/');
                unset($_COOKIE[$k]);
            }
            return $this;
        }
        
        // Se não passar key, retorna todos os cookies
        if ($key === null) {
            return $_COOKIE;
        }
        
        // Se for array, define múltiplos cookies
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                if (is_array($v) && isset($v['value'])) {
                    // Formato: ['name' => ['value' => 'val', 'expire' => time]]
                    $this->cookie($k, $v['value'], $v);
                } else {
                    // Formato simples: ['name' => 'value']
                    $this->cookie($k, $v, $options);
                }
            }
            return $this;
        }
        
        // Se value for null, obtém valor
        if ($value === null) {
            return $_COOKIE[$key] ?? null;
        }
        
        // Se value for false, apaga o cookie
        if ($value === false) {
            setcookie($key, '', time() - 3600, '/');
            unset($_COOKIE[$key]);
            return $this;
        }
        
        // Opções padrão para o cookie
        $defaults = [
            'expire' => 0,           // 0 = até fechar o browser
            'path' => '/',           // Caminho base
            'domain' => '',          // Domínio atual
            'secure' => false,       // Requer HTTPS
            'httponly' => true,      // Não acessível via JavaScript
            'samesite' => 'Lax'      // Proteção CSRF
        ];

        // Mesclar opções padrão com as fornecidas
        $opts = array_merge($defaults, $options);
        
        // Definir cookie
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
        
        // Atualizar superglobal
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
        // Iniciar sessão se não existir
        if (!session_id()) session_start();
        
        // Se key for 'destroy', destrói a sessão
        if ($key === 'destroy') {
            session_destroy();
            $_SESSION = [];
            return $this;
        }
        
        // Se não passar key, retorna toda a sessão
        if ($key === null) {
            return $_SESSION;
        }
        
        // Se for array, define múltiplos valores
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $_SESSION[$k] = $v;
            }
            return $this;
        }
        
        // Se value for null, obtém valor
        if ($value === null) {
            return $_SESSION[$key] ?? null;
        }
        
        // Se value for false, apaga a key
        if ($value === false) {
            unset($_SESSION[$key]);
            return $this;
        }

        // Define valor
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
        // Obter e apagar
        if ($value === null) {
            $value = $this->session($key);
            $this->session($key, false);
            return $value;
        }
        
        // Definir
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

                // Verificar parâmetros necessários
                if (isset($requiredParams)) {
                    foreach ($requiredParams as $param) {
                        if (!isset($dbConfig[$param])) {
                            throw new \Exception("Missing DB parameter: {$param}");
                        }
                    }
                }

                // Charset padrão
                if (!isset($dbConfig['charset'])) {
                    $dbConfig['charset'] = 'utf8mb4';
                }

                // Conectar
                $this->db = new \Medoo\Medoo($dbConfig);
                
                // Testar conexão
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
        
        // Se value é null, é uma leitura
        if ($value === null) {
            if (!file_exists($cacheFile)) {
                return null;
            }
            $data = json_decode(file_get_contents($cacheFile), true);
            
            // Verificar expiração
            if ($data['expires'] < time()) {
                unlink($cacheFile);
                return null;
            }

            return $data['value'];
        }

        // Se tem value, é uma escrita
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
     * Log utility
     *
     * @param string $message Log message
     * @param string $level Log level
     * @return self
     */
    public function log($message, $level = 'info') {
        $logFile = $this->storage('logs') . '/' . date('Y-m-d') . '.log';
        $logMessage = sprintf(
            "[%s] %s: %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            is_array($message) ? json_encode($message) : $message
        );
        
        // Silenciosamente tenta escrever log se o diretório existir
        if (is_dir(dirname($logFile))) {
            @error_log($logMessage, 3, $logFile);
        }
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
     * Load route files from routes directory
     * 
     * @param string $routesPath Path to routes directory
     * @return self
     */
    public function loadRoutes($routesPath = null) {
        $routesPath = $routesPath ?? ROUTES;

        if (!is_dir($routesPath)) {
            $this->log("Routes directory not found: $routesPath", "warning");
            return $this;
        }

        $this->loadRoutesRecursively($routesPath);
        return $this;
    }

    /**
     * Recursively load routes from directory
     * 
     * @param string $dir Directory path
     * @return void
     */
    private function loadRoutesRecursively($dir) {
        // Add safety check
        if (!is_dir($dir)) {
            return;
        }

        // Get all PHP files and directories with safety check
        $items = glob($dir . '/*');
        if (!is_array($items)) {
            $this->log("Failed to read directory: $dir", "error");
            return;
        }

        foreach ($items as $item) {
            if (is_file($item) && pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                // Load PHP file
                try {
                    $app = $this;
                    $this->log("Loading route file: $item", "debug");
                    require $item;
                } catch (\Throwable $e) {
                    $this->log("Failed to load route file: $item - " . $e->getMessage(), 'error');
                }
            } elseif (is_dir($item)) {
                $this->loadRoutesRecursively($item);
            }
        }
    }

    // Método para obter path do storage
    public function storage($type = 'app') {
        return $this->storagePaths[$type] ?? APP;
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
    public function debug() {
        error_log("=== DEBUG LAPA ===");
        error_log("Grupos: " . $this->currentGroup);
        error_log("Rotas: " . print_r($this->routes, true));
        error_log("=================");
    }

    public function handleRequest() {
        // Verificar se está configurado
        if (!$this->isConfigured()) {
            return $this->raw('
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Lapa Framework - Setup Required</title>
                    <style>
                        body { 
                            font-family: sans-serif; 
                            max-width: 800px; 
                            margin: 40px auto; 
                            padding: 20px;
                            line-height: 1.6;
                        }
                        pre {
                            background: #f5f5f5;
                            padding: 15px;
                            border-radius: 5px;
                        }
                    </style>
                </head>
                <body>
                    <h1>Lapa Framework</h1>
                    <p>The application needs to be configured before it can be used.</p>
                    <p>Look for the file <code>config.example.php</code> in the root folder and copy it to <code>storage/app/private/config.php</code></p>
                    <p>Then modify the configuration values as needed.</p>
                    <p>Example path:</p>
                    <pre>
storage/
  app/
    private/
      config.php    <-- Copy config.example.php here
                    </pre>
                    <p>Once you copy and configure the file, this message will disappear.</p>
                </body>
                </html>
            ');
        }

        // Continuar com o processamento normal da rota
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remover barra final exceto para root '/'
        $uri = $uri === '/' ? '/' : rtrim($uri, '/');
        
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $pattern => $route) {
                // Verificar virtual host primeiro
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
        
        // 404 handler
        $this->error('Not Found', 404);
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
    }

    /**
     * Load helpers recursively from directory
     * 
     * @param string $dir Directory path
     * @return void
     */
    private function loadHelpersRecursively($dir) {
        // Suprimir warnings com @
        $items = @glob($dir . '/*');
        
        // Se não houver items, retorna silenciosamente
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if (!$item) continue;
            
            if (@is_file($item) && pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                // Load PHP file
                try {
                    $this->log("Loading helper file: $item", "debug");
                    @require $item;
                } catch (\Throwable $e) {
                    $this->log("Failed to load helper file: $item - " . $e->getMessage(), 'error');
                }
            } elseif (@is_dir($item)) {
                $this->loadHelpersRecursively($item);
            }
        }
    }

}