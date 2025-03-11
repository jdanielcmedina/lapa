<?php
namespace Tests\Unit;

use Tests\TestCase;

class RequestTest extends TestCase
{
    public function testGetRequest()
    {
        $_GET['name'] = 'john';
        $this->assertEquals('john', $this->app->get('name'));
    }
    
    public function testPostRequest()
    {
        $_POST['email'] = 'test@example.com';
        $this->assertEquals('test@example.com', $this->app->post('email'));
    }
    
    public function testJsonRequest()
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $json = '{"key":"value"}';
        $this->setRequestBody($json);
        
        $this->assertEquals('value', $this->app->body('key'));
    }
    
    private function setRequestBody($content)
    {
        $tmp = tmpfile();
        fwrite($tmp, $content);
        fseek($tmp, 0);
        
        define('TEST_REQUEST_BODY', $tmp);
    }
}
