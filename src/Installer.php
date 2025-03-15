<?php
namespace Lapa;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Installer implements PluginInterface {
    public function activate(Composer $composer, IOInterface $io) {
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        $projectDir = dirname($vendorDir);
        
        // Criar estrutura
        $this->createStructure($projectDir);
        
        $io->write("\n<info>Lapa Framework installed successfully!</info>\n");
    }

    public function createStructure($projectPath) {
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
    }

    public static function getConfigTemplate() {
        return "<?php\nreturn [\n    'debug' => true,\n    'timezone' => 'UTC'\n];";
    }

    public function deactivate(Composer $composer, IOInterface $io) {}
    public function uninstall(Composer $composer, IOInterface $io) {}
}
