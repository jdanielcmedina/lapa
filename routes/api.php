<?php
// API Routes
$router->group('/api', function($app) {
    $app->on('GET /test', function() {
        return ['message' => 'test'];
    });
    
    $app->on('GET /status', function() {
        return ['status' => 'online'];
    });
    
    $app->on('POST /users', function() {
        return ['message' => 'User created'];
    });

    $app->on('POST /upload', function() {
        $filename = $this->upload('file'); // 'file' é o nome do campo no formulário
        if ($filename) {
            return ['success' => true, 'filename' => $filename];
        }
        return ['error' => 'Upload failed'];
    });

    $app->on('POST /upload/image', function() {
        // Configurar validações
        $this->config['storage'] = [
            'max_size' => '5M', // 5 megabytes
            'allowed_types' => ['jpg', 'png', 'gif']
        ];
        
        $filename = $this->upload('image');
        if ($filename) {
            return [
                'success' => true,
                'filename' => $filename,
                'url' => '/storage/uploads/' . $filename
            ];
        }
        return ['error' => 'Invalid file'];
    });

    $app->on('POST /upload/documents', function() {
        $filename = $this->upload('document', 'storage/documents');
        if ($filename) {
            return ['success' => true, 'path' => 'documents/' . $filename];
        }
        return ['error' => 'Upload failed'];
    });

    $app->notFound(function() {
        return ['error' => 'API endpoint not found'];
    });
}); 