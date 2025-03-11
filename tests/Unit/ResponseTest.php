<?php
namespace Tests\Unit;

use Tests\TestCase;

class ResponseTest extends TestCase
{
    public function testSuccessResponse()
    {
        ob_start();
        $this->app->success(['data' => 'value']);
        $response = json_decode(ob_get_clean(), true);
        
        $this->assertFalse($response['error']);
        $this->assertEquals('value', $response['data']['data']);
    }
    
    public function testErrorResponse()
    {
        ob_start();
        $this->app->error('Error message', 400);
        $response = json_decode(ob_get_clean(), true);
        
        $this->assertTrue($response['error']);
        $this->assertEquals('Error message', $response['message']);
    }
    
    public function testCustomResponse()
    {
        ob_start();
        $this->app->response('<p>Hello</p>', 'html');
        $response = ob_get_clean();
        
        $this->assertEquals('<p>Hello</p>', $response);
    }
}
