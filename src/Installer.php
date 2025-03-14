<?php
namespace Lapa;

use Composer\Script\Event;

class Installer {
    public static function install(Event $event = null) {
        $projectPath = $event ? $event->getComposer()->getConfig()->get('vendor-dir') . '/..' : getcwd();
        $projectPath = realpath($projectPath);

        $directories = [
            'app',
            'public',
            'routes',
            'views',
            'views/partials',
            'helpers',
            'plugins',
            'storage/app',
            'storage/logs',
            'storage/cache',
            'storage/temp',
            'storage/uploads'
        ];

        // Criar diretórios
        foreach ($directories as $dir) {
            $path = $projectPath . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
        }

        // Copiar arquivos base
        $files = [
            'public/index.php' => <<<'PHP'
<?php
require '../vendor/autoload.php';

$app = new \Lapa\Lapa();
require __DIR__ . '/../routes/web.php';
PHP
            ,
            'routes/api.php' => "<?php\n\n\$app->group('/api', function(\$app) {\n    \$app->on('GET /', function() {\n        return ['version' => '1.0.0'];\n    });\n});",
            'storage/app/config.example.php' => self::getConfigTemplate(),
            'public/.htaccess' => self::getHtaccessTemplate(),
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
        echo "You can now start building your application.\n";
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
