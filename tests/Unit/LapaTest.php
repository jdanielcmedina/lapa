<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lapa\Lapa;

class LapaTest extends TestCase
{
    protected $app;

    protected function setUp(): void
    {
        // Mock $_SERVER
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../../index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'localhost';

        // Mock config file
        if (!file_exists(__DIR__ . '/../../config.php')) {
            file_put_contents(__DIR__ . '/../../config.php', '<?php return ["debug" => true];');
        }

        // Create instance
        $this->app = new Lapa();
    }

    protected function tearDown(): void
    {
        // Cleanup
        @unlink(__DIR__ . '/../../config.php');
    }

    public function test_lapa_can_be_instantiated()
    {
        $this->assertInstanceOf(Lapa::class, $this->app);
    }

    public function test_can_register_route()
    {
        $this->app->on('GET /test', function() {
            return 'test';
        });

        $routes = $this->app->getRoutes();
        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/test', $routes['GET']);
    }
} 