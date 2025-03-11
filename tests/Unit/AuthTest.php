<?php
namespace Tests\Unit;

use Tests\TestCase;

class AuthTest extends TestCase
{
    public function testRequireAuth()
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token';
        
        $this->app->isAuthenticated = function() {
            return true;
        };
        
        $result = $this->app->requireAuth();
        $this->assertTrue($result);
    }
    
    public function testRequireAdmin()
    {
        $this->app->isAuthenticated = function() {
            return true;
        };
        
        $this->app->isAdmin = function() {
            return true;
        };
        
        $result = $this->app->requireAdmin();
        $this->assertTrue($result);
    }
}
