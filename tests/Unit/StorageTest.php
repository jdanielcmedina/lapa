<?php
namespace Tests\Unit;

use Tests\TestCase;

class StorageTest extends TestCase
{
    protected $testFile = 'test.txt';
    
    public function testStoragePath()
    {
        $path = $this->app->storage('public');
        $this->assertDirectoryExists($path);
    }
    
    public function testFileUpload()
    {
        $_FILES['file'] = [
            'name' => $this->testFile,
            'type' => 'text/plain',
            'tmp_name' => __DIR__ . '/../../storage/temp/test.txt',
            'error' => 0,
            'size' => 123
        ];
        
        file_put_contents($_FILES['file']['tmp_name'], 'test content');
        
        $result = $this->app->upload('file');
        $this->assertNotFalse($result);
        
        unlink($_FILES['file']['tmp_name']);
    }
    
    public function testCache()
    {
        $this->app->cache('test-key', 'test-value', 60);
        $value = $this->app->cache('test-key');
        
        $this->assertEquals('test-value', $value);
    }
}
