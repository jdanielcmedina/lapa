#!/usr/bin/env php
<?php

class LapaSetup {
    private $config = [];
    
    public function run(): void {
        echo "\n🚀 Lapa Framework Setup\n\n";
        
        // Basic configuration
        $this->askHttps();
        
        // Database
        if ($this->confirm('Do you want to configure the database?')) {
            $this->setupDatabase();
        }
        
        // Cache
        if ($this->confirm('Do you want to configure cache?')) {
            $this->setupCache();
        }
        
        // Email
        if ($this->confirm('Do you want to configure email?')) {
            $this->setupEmail();
        }
        
        // Save configuration
        $this->saveConfig();
        
        echo "\n✅ Configuration saved to config.php\n";
    }
    
    private function askHttps(): void {
        $this->config['secure'] = $this->confirm('Force HTTPS?');
    }
    
    private function setupDatabase(): void {
        // Choose database type
        $type = $this->choice('Database type:', [
            'mysql' => 'MySQL/MariaDB',
            'sqlite' => 'SQLite (single file)',
            'pgsql' => 'PostgreSQL',
            'mssql' => 'Microsoft SQL Server',
            'sybase' => 'Sybase'
        ]);

        $this->config['db'] = ['type' => $type];

        // SQLite doesn't need additional configuration
        if ($type === 'sqlite') {
            echo "\nSQLite database will be created at storage/app/private/database.sqlite\n";
            return;
        }

        // Server-based database configuration
        echo "\nConnection settings:\n";
        $this->config['db'] += [
            'host' => $this->ask('Host (localhost):', 'localhost'),
            'database' => $this->ask('Database name:'),
            'username' => $this->ask('Username:'),
            'password' => $this->ask('Password:'),
            'charset' => $this->ask('Charset (utf8mb4):', 'utf8mb4')
        ];

        // Type-specific settings
        switch ($type) {
            case 'pgsql':
                $this->config['db']['port'] = $this->ask('Port (5432):', '5432');
                break;
            case 'mssql':
                $this->config['db']['port'] = $this->ask('Port (1433):', '1433');
                break;
            case 'mysql':
                $this->config['db']['port'] = $this->ask('Port (3306):', '3306');
                break;
        }

        // Test connection
        echo "\nTesting connection...\n";
        try {
            $db = new \Medoo\Medoo($this->config['db']);
            $db->query("SELECT 1")->fetch();
            echo "✅ Connection successful!\n";
        } catch (\PDOException $e) {
            echo "❌ Connection error: " . $e->getMessage() . "\n";
            if ($this->confirm('Do you want to try again?')) {
                $this->setupDatabase();
            }
        }
    }
    
    private function setupCache(): void {
        $driver = $this->choice('Cache driver:', ['local', 'redis', 'memcached']);
        
        $this->config['cache'] = [
            'driver' => $driver
        ];
        
        if ($driver !== 'local') {
            $this->config['cache']['host'] = $this->ask('Host:', '127.0.0.1');
            $this->config['cache']['port'] = (int)$this->ask('Port:', $driver === 'redis' ? '6379' : '11211');
        }
    }
    
    private function setupEmail(): void {
        $this->config['mail'] = [
            'host' => $this->ask('SMTP Host:'),
            'username' => $this->ask('Username:'),
            'password' => $this->ask('Password:'),
            'port' => (int)$this->ask('Port (587):', '587')
        ];
    }
    
    private function setupJWT(): void {
        $this->config['jwt'] = [
            'key' => $this->ask('Secret key (min 32 chars):'),
            'expire' => (int)$this->ask('Expiration in seconds (3600):', '3600')
        ];
    }
    
    private function ask(string $question, ?string $default = null): string {
        echo $question . ' ';
        $answer = trim(fgets(STDIN));
        return $answer ?: $default;
    }
    
    private function confirm(string $question): bool {
        echo $question . ' (s/n) ';
        return strtolower(trim(fgets(STDIN))) === 's';
    }
    
    private function choice(string $question, array $options): string {
        echo $question . "\n";
        foreach ($options as $i => $option) {
            echo ($i + 1) . ") $option\n";
        }
        echo 'Escolha (1-' . count($options) . '): ';
        $answer = (int)trim(fgets(STDIN)) - 1;
        return $options[$answer] ?? $options[0];
    }
    
    private function saveConfig(): void {
        $content = "<?php\n\nreturn " . var_export($this->config, true) . ";\n";
        file_put_contents('config.php', $content);
    }
}

// Criar estrutura de pastas
$paths = [
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/app/private',
    'storage/logs',
    'storage/cache',
    'storage/temp',
    'storage/uploads'
];

foreach ($paths as $path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// Criar .gitignore
file_put_contents('storage/.gitignore', "*\n!.gitignore\n");

echo "Setup completed!\n";

(new LapaSetup())->run(); 