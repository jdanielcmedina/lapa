<?php
namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Lapa\Lapa;

class TestCase extends BaseTestCase
{
    protected $app;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clean output buffer
        while (ob_get_level()) ob_end_clean();
        
        // Reset environment
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_GET = $_POST = $_FILES = $_COOKIE = [];
        
        // Create test app instance
        $this->app = new Lapa([
            'test' => true,
            'debug' => true,
            'timezone' => 'UTC'
        ]);
    }
}
