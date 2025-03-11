<?php
namespace Tests\Unit;

use Tests\TestCase;

class ValidationTest extends TestCase
{
    public function testBasicValidation()
    {
        $_POST = [
            'name' => 'John',
            'email' => 'john@example.com',
            'age' => '25'
        ];
        
        $validated = $this->app->validate([
            'name' => 'required|min:3',
            'email' => 'required|email',
            'age' => 'numeric'
        ]);
        
        $this->assertIsArray($validated);
        $this->assertEquals('John', $validated['name']);
    }
    
    public function testValidationFailure()
    {
        $_POST = [
            'name' => 'Jo',
            'email' => 'invalid'
        ];
        
        ob_start();
        $result = $this->app->validate([
            'name' => 'required|min:3',
            'email' => 'required|email'
        ]);
        ob_get_clean();
        
        $this->assertFalse($result);
    }
}
