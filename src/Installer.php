<?php
namespace Lapa;

class Installer {
    public static function install($projectPath) {
        $directories = [
            'routes',
            'views',
            'views/partials',
            'storage',
            'storage/app',
            'storage/cache',
            'storage/logs',
            'storage/uploads',
            'storage/temp',
            'helpers'  // Nova pasta para helpers do projeto
        ];

        // Criar diretórios
        foreach ($directories as $dir) {
            if (!is_dir($projectPath . '/' . $dir)) {
                mkdir($projectPath . '/' . $dir, 0755, true);
            }
        }

        // Copiar arquivos base
        $files = [
            'routes/web.php' => "<?php\n\n\$app->on('GET /', function() {\n    return ['message' => 'Welcome to Lapa!'];\n});",
            'routes/api.php' => "<?php\n\n\$app->group('/api', function(\$app) {\n    \$app->on('GET /', function() {\n        return ['version' => '1.0.0'];\n    });\n});",
            'storage/app/config.example.php' => self::getConfigTemplate(),
            'public/.htaccess' => self::getHtaccessTemplate(),
            'public/index.php' => self::getIndexTemplate(),
            'helpers/auth.php' => self::getAuthHelperTemplate(),
            'helpers/app.php' => self::getAppHelperTemplate(),
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

        echo "Lapa Framework installed successfully!\n";
        echo "Please configure your application in storage/app/config.php\n";
    }

    private static function getConfigTemplate() {
        return <<<'PHP'
<?php
return [
    'debug' => true,
    'timezone' => 'UTC',
    
    'db' => [
        'type' => 'mysql',
        'host' => 'localhost',
        'database' => 'database',
        'username' => 'root',
        'password' => ''
    ]
];
PHP;
    }

    private static function getHtaccessTemplate() {
        return <<<'HTACCESS'
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
HTACCESS;
    }

    private static function getIndexTemplate() {
        return <<<'PHP'
<?php
require '../vendor/autoload.php';

$app = new Lapa\Lapa();
PHP;
    }

    private static function getAuthHelperTemplate() {
        return <<<'PHP'
<?php
// Helper functions for authentication
function require_auth() {
    if (!app()->auth->check()) {
        return app()->error('Unauthorized', 401);
    }
    return true;
}

function current_user() {
    return app()->auth->user();
}
PHP;
    }

    private static function getAppHelperTemplate() {
        return <<<'PHP'
<?php
// Your custom application helpers
function app() {
    global $app;
    return $app;
}

function config($key = null) {
    return app()->config($key);
}
PHP;
    }
}
