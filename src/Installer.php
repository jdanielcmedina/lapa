<?php
namespace Lapa;

class Installer {
    public static function createStructure($projectPath = null) {
        // Se não passado, usar diretório atual
        $projectPath = $projectPath ?? getcwd();
        
        echo "Creating Lapa structure in: $projectPath\n";
        
        $directories = [
            'public',
            'routes',
            'views/partials',  // Aqui já é criada a pasta partials
            'storage/cache',
            'storage/logs',
            'storage/uploads',
            'storage/temp',
            'helpers',
            'plugins'
        ];

        foreach ($directories as $dir) {
            $path = $projectPath . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
        }

        // Criar arquivos base
        $files = [
            'public/index.php' => "<?php
require '../vendor/autoload.php';

\$app = new \\Lapa\\Lapa([
    'debug' => true,
    'secure' => false,
    'cors' => [
        'enabled' => false,
        'origins' => '*',
        'methods' => 'GET, POST, PUT, DELETE, OPTIONS, PATCH',
        'headers' => 'Content-Type, Authorization, X-Requested-With',
        'credentials' => false
    ],
    'mail' => [
        'enabled' => false,
        'host' => 'smtp.example.com',
        'port' => 587,
        'secure' => 'tls',
        'auth' => true,
        'username' => 'your@email.com',
        'password' => 'your-password',
        'fromName' => 'Your Application',
        'fromEmail' => 'noreply@yourdomain.com',
        'debug' => 0
    ],
    'db' => [
        'type' => 'mysql',
        'database' => 'database_name',
        'host' => 'localhost',
        'username' => 'root',
        'password' => ''
    ]
]);",
            'public/.htaccess' => "RewriteEngine On\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteRule ^ index.php [QSA,L]",
            'routes/web.php' => "<?php\n\$app->on('GET /', function() {\n    return ['message' => 'Welcome to Lapa!'];\n});"
        ];

        foreach ($files as $file => $content) {
            $path = $projectPath . '/' . $file;
            if (!file_exists($path)) {
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0755, true);
                }
                file_put_contents($path, $content);
            }
        }
        
        echo "\n";
        echo "===========================================\n";
        echo "🚀 LAPA FRAMEWORK INSTALLED SUCCESSFULLY! 🚀\n";
        echo "===========================================\n";
        echo "\n";
    }
}
