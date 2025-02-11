<?php
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
    private $storagePaths = [
        'app' => 'storage/app',
        'public' => 'storage/app/public',
        'private' => 'storage/app/private',
        'logs' => 'storage/logs',
        'cache' => 'storage/cache',
        'temp' => 'storage/temp',
        'uploads' => 'storage/uploads'
    ];

    /**
     * File permissions for different storage types
     * @var array
     */
    private const STORAGE_PERMISSIONS = [
        'public' => 0644,  // rw-r--r--
        'private' => 0600, // rw-------
        'folder' => 0755   // rwxr-xr-x
    ];

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

    /**
     * Initialize the framework
     */
    public function __construct() {
        try {
            // Load configuration
            $configFile = dirname($_SERVER['SCRIPT_FILENAME']) . '/config.php';
            if (!file_exists($configFile)) {
                throw new \Exception('Configuration file not found');
            }
            $this->config = require $configFile;
            
            // Initialize database if configured
            if (isset($this->config['db'])) {
                try {
                    $this->db = new \Medoo\Medoo($this->config['db']);
                } catch (\PDOException $e) {
                    throw new \Exception("Database connection failed: " . $e->getMessage());
                }
            }
            
            // Create storage directories
            foreach ($this->storagePaths as $path) {
                if (!is_dir($path) && !@mkdir($path, 0755, true)) {
                    throw new \Exception("Failed to create directory: $path");
                }
            }
            
            // Force HTTPS if configured
            if (isset($this->config['secure']) && $this->config['secure'] === true) {
                if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
                    $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    header('Location: ' . $redirectUrl);
                    exit;
                }
            }
            
            // Initialize mailer if configured
            if (isset($this->config['mail'])) {
                $this->mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            }

            // Auto-load routes
            $routesPath = dirname($_SERVER['SCRIPT_FILENAME']) . '/routes';
            if (is_dir($routesPath)) {
                foreach (glob($routesPath . '/*.php') as $file) {
                    try {
                        $router = $this;
                        require $file;
                    } catch (\Throwable $e) {
                        $this->log("Failed to load route file: $file - " . $e->getMessage(), 'error');
                    }
                }
            }
            
            // Register router
            register_shutdown_function([$this, 'handleRequest']);
        } catch (\Throwable $e) {
            $this->log("Initialization failed: " . $e->getMessage(), 'error');
            if ($this->config['debug'] ?? false) {
                throw $e;
            }
            $this->error('Internal server error', 500);
            exit;
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

        // Normalizar path
        $fullPath = '/' . trim($fullPath, '/');

        // Debug
        error_log("Registrando rota:");
        error_log("  Método: " . implode('|', $methods));
        error_log("  Path: " . $fullPath);
        error_log("  Grupo: " . $this->currentGroup);

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
        // Guardar vhost atual
        $previousVhost = $this->currentVhost;
        
        // Definir novo vhost
        $this->currentVhost = $host;
        
        // Executar callback
        $callback($this);
        
        // Restaurar vhost anterior
        $this->currentVhost = $previousVhost;
        
        return $this;
    }

    /**
     * Get HTTP GET parameters
     *
     * @param string|null $key Parameter key
     * @return mixed Parameter value or all parameters if key is null
     */
    public function get($key = null) {
        return $key ? ($_GET[$key] ?? null) : $_GET;
    }

    /**
     * Get HTTP POST parameters
     *
     * @param string|null $key Parameter key
     * @return mixed Parameter value or all parameters if key is null
     */
    public function post($key = null) {
        return $key ? ($_POST[$key] ?? null) : $_POST;
    }

    /**
     * Send text response
     *
     * @param string $content Text content
     * @param int|null $code HTTP status code
     * @return null
     */
    public function text($content, $code = null) {
        if ($code !== null) {
            $this->status($code);
        }
        
        http_response_code($this->statusCode);
        header('Content-Type: text/plain');
        
        echo $content;
        $this->statusCode = 200;
        
        return null;
    }

    /**
     * Send JSON response
     *
     * @param mixed $data Data to encode as JSON
     * @param int|null $code HTTP status code
     * @return null
     */
    public function json($data, $code = null) {
        // Se passar código direto no método
        if ($code !== null) {
            $this->status($code);
        }
        
        // Definir status code atual
        http_response_code($this->statusCode);
        header('Content-Type: application/json');
        
        // Preparar e enviar resposta
        echo json_encode($data);
        
        // Só resetar após preparar a resposta
        $this->statusCode = 200;
        
        // Retornar null para não duplicar output
        return null;
    }

    /**
     * Send XML response
     *
     * @param string $content XML content
     * @param int|null $code HTTP status code
     * @return null
     */
    public function xml($content, $code = null) {
        if ($code !== null) {
            $this->status($code);
        }
        http_response_code($this->statusCode);
        header('Content-Type: application/xml');
        
        echo $content;
        $this->statusCode = 200;
        
        return null;
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

        // Verificar se o ficheiro existe
        if (!file_exists($file)) {
            throw new \Exception("View não encontrada: {$file}");
        }

        // Extrair variáveis para o escopo local
        extract($data);

        // Iniciar buffer de output
        ob_start();

        try {
            include $file;
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
     * Send HTML response
     *
     * @param string $file HTML file path
     * @param array $data Data to pass to view
     * @param int|null $code HTTP status code
     * @return null
     */
    public function html($file, $data = [], $code = null) {
        if ($code !== null) {
            $this->status($code);
        }
        http_response_code($this->statusCode);
        header('Content-Type: text/html');
        
        $content = $this->view($file, $data);
        if ($content !== null) {
            echo $content;
            $this->statusCode = 200;
        }
        
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
     * Send error response
     *
     * @param string $message Error message
     * @param int $code HTTP status code
     * @return null
     */
    public function error($message, $code = 400) {
        http_response_code($code);
        return $this->json(['error' => true, 'message' => $message]);
    }

    /**
     * Send success response
     *
     * @param mixed|null $data Response data
     * @param string $message Success message
     * @return null
     */
    public function success($data = null, $message = 'Success') {
        return $this->json([
            'error' => false,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Validate input parameters
     *
     * @param array $rules Validation rules
     * @return array Validated parameters
     */
    public function validate($rules) {
        // Implementar validação
        return [];
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
     * @return array Request body
     */
    public function body() {
        return json_decode(file_get_contents('php://input'), true);
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
     * Set response content type
     *
     * @param string $type Content type
     * @return self
     */
    public function type($type) {
        return $this->header('Content-Type', $type);
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
                        // Criar arquivo se não existir
                        if (!file_exists($dbPath)) {
                            touch($dbPath);
                            chmod($dbPath, self::STORAGE_PERMISSIONS['private']);
                        }
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
                            throw new \Exception("Parâmetro de BD em falta: {$param}");
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
                $this->log("Erro de conexão BD: " . $e->getMessage(), "error");
                if ($this->config['debug']) {
                    throw $e;
                }
                throw new \Exception("Erro ao conectar à base de dados");
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

        file_put_contents($cacheFile, json_encode($data));
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
        
        // Verificar tamanho máximo
        $maxSize = $this->config['upload']['max_size'] ?? (5 * 1024 * 1024); // 5MB default
        if ($file['size'] > $maxSize) {
            $this->log("Upload rejeitado: arquivo muito grande", "error");
            return false;
        }

        // Gerar nome único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $fullPath = $path . '/' . $filename;

        // Mover arquivo
        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            $this->log("Arquivo uploaded: $filename", "info");
            return $filename;
        }

        $this->log("Erro no upload: " . $file['error'], "error");
        return false;
    }

    /**
     * Download a file from storage
     *
     * @param string $file File path
     * @param string|null $name Custom download name
     * @return bool Success status
     */
    public function download($file, $name = null) {
        // Verificar se é path absoluto ou relativo ao storage
        $path = $file;
        if (!file_exists($path)) {
            $path = $this->storage('public') . '/' . $file;
        }
        
        if (!file_exists($path)) {
            $this->log("Arquivo não encontrado: $file", "error");
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
        
        error_log($logMessage, 3, $logFile);
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
    public function loadRoutes($routesPath = 'routes') {
        if (!is_dir($routesPath)) {
            $this->log("Routes directory not found: $routesPath", "warning");
            return $this;
        }

        // Get all PHP files from routes directory
        $files = glob($routesPath . '/*.php');
        foreach ($files as $file) {
            $router = $this; // $router é uma referência ao objeto Lapa
            require $file;
        }

        return $this;
    }

    // Método para obter path do storage
    public function storage($type = 'app') {
        return $this->storagePaths[$type] ?? $this->storagePaths['app'];
    }

    /**
     * Cleanup storage
     *
     * @param int $maxAge Maximum age in seconds
     * @return self
     */
    public function cleanup($maxAge = 86400) { // 24 horas
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
        
        $this->log("Limpeza automática executada", "info");
        return $this;
    }

    /**
     * Move a file from temp to public storage
     *
     * @param string $from Source file path
     * @param string $to Target file path
     * @return bool Success status
     */
    public function move($from, $to) {
        $sourcePath = $this->storage('temp') . '/' . $from;
        $targetPath = $this->storage('public') . '/' . $to;
        
        if (!file_exists($sourcePath)) {
            $this->log("Source file not found: $from", "error");
            return false;
        }
        
        if (rename($sourcePath, $targetPath)) {
            chmod($targetPath, self::STORAGE_PERMISSIONS['public']);
            $this->log("File moved: $from -> $to", "info");
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

    /**
     * Handle HTTP request and route to appropriate handler
     *
     * @return void
     */
    private function handleRequest() {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $host = $_SERVER['HTTP_HOST'] ?? '';
            
            $routeFound = false;
            
            if (isset($this->routes[$method])) {
                foreach ($this->routes[$method] as $pattern => $route) {
                    try {
                        // Verificar vhost
                        if ($route['vhost'] && $route['vhost'] !== $host) {
                            continue;
                        }
                        
                        // Match route
                        $regex = preg_replace('/:[a-zA-Z]+/', '([^/]+)', $pattern);
                        $regex = str_replace('/', '\/', $regex);
                        $regex = '/^' . $regex . '$/';
                        
                        if (preg_match($regex, $uri, $matches)) {
                            $routeFound = true;
                            
                            // Extract params
                            preg_match_all('/:([a-zA-Z]+)/', $pattern, $paramNames);
                            array_shift($matches);
                            $this->currentParams = array_combine($paramNames[1], $matches);
                            
                            // Execute route callback
                            try {
                                $response = $route['callback']($this);
                                if ($response !== null) {
                                    if (is_array($response)) {
                                        $this->json($response);
                                    } else {
                                        $this->text($response);
                                    }
                                }
                            } catch (\Throwable $e) {
                                $this->log("Route callback failed: " . $e->getMessage(), 'error');
                                throw $e;
                            }
                            break;
                        }
                    } catch (\Throwable $e) {
                        $this->log("Route processing failed: " . $e->getMessage(), 'error');
                        throw $e;
                    }
                }
            }

            // Handle 404
            if (!$routeFound) {
                $this->handleNotFound($uri);
            }

        } catch (\Throwable $e) {
            $this->log("Request handling failed: " . $e->getMessage(), 'error');
            if ($this->config['debug'] ?? false) {
                $this->error($e->getMessage(), 500);
            } else {
                $this->error('Internal server error', 500);
            }
        }
    }

    private function handleNotFound($uri) {
        try {
            // Find most specific handler
            $handler = null;
            $longestMatch = 0;

            foreach ($this->notFoundHandlers as $groupPrefix => $groupHandler) {
                if (strpos($uri, $groupPrefix) === 0) {
                    $prefixLength = strlen($groupPrefix);
                    if ($prefixLength > $longestMatch) {
                        $longestMatch = $prefixLength;
                        $handler = $groupHandler;
                    }
                }
            }

            if ($handler) {
                $response = $handler($this);
                if ($response !== null) {
                    if (is_array($response)) {
                        $this->json($response);
                    } else {
                        $this->text($response);
                    }
                }
            } else {
                $this->error('Route not found', 404);
            }
        } catch (\Throwable $e) {
            $this->log("Not found handler failed: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Set not found handler for group
     *
     * @param callable $callback Not found handler
     * @return self
     */
    public function notFound($callback) {
        // Se estiver em um grupo, registra handler específico
        if ($this->currentGroup) {
            $this->notFoundHandlers[$this->currentGroup] = $callback;
        } else {
            // Se não estiver em grupo, registra handler global
            $this->notFoundHandlers['/'] = $callback;
        }
        return $this;
    }

    /**
     * Protect a route with authentication
     *
     * @param callable|null $callback Custom authentication function
     * @return self
     * @throws \Exception If authentication fails
     */
    public function protect($callback = null) {
        $isAuthorized = false;

        if (!$callback) {
            // Default protection - check session
            $isAuthorized = isset($_SESSION['user']);
        } else {
            // Custom protection
            $isAuthorized = $callback($this);
        }

        if (!$isAuthorized) {
            $this->error('Access denied', 403);
            exit;
        }

        return $this;
    }
} 