<?php
namespace Lapa;

class Installer {
    public static function createStructure($projectPath = null) {
        // Se não passado, usar diretório atual
        $projectPath = $projectPath ?? getcwd();
        
        echo "Creating Lapa structure in: $projectPath\n";
        
        $directories = [
            'app',
            'public',
            'routes',
            'views/partials',
            'storage/app',
            'storage/logs',
            'storage/cache',
            'storage/uploads',
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
            'public/index.php' => "<?php\nrequire '../vendor/autoload.php';\n\$app = new \\Lapa\\Lapa();",
            'public/.htaccess' => "RewriteEngine On\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteRule ^ index.php [QSA,L]",
            'routes/web.php' => "<?php\n\$app->on('GET /', function() {\n    return ['message' => 'Welcome to Lapa!'];\n});",
            'storage/app/config.php' => self::getConfigTemplate(),
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
    }

    private static function getConfigTemplate() {
        return "<?php\nreturn [\n    'debug' => true,\n    'timezone' => 'UTC'\n];";
    }
}
