<?php
namespace Tests\Unit;

use Tests\TestCase;

class CoreTest extends TestCase
{
    public function testBasicRoute()
    {
        $this->app->on('GET /test', function() {
            return ['status' => 'ok'];
        });
        
        $_SERVER['REQUEST_URI'] = '/test';
        ob_start();
        $this->app->handleRequest();
        $response = json_decode(ob_get_clean(), true);
        
        $this->assertEquals('ok', $response['status']);
    }
    
    public function testHelperFunction()
    {
        $this->app->testHelper = function($name) {
            return "Hello, $name!";
        };
        
        $result = $this->app->testHelper('World');
        $this->assertEquals('Hello, World!', $result);
    }
    
    public function testConfigAccess()
    {
        $this->assertEquals('UTC', $this->app->config('timezone'));
    }
}
