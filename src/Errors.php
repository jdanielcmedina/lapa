<?php
namespace Lapa;

class Errors {
    private $app;
    private $debug;
    private $styles = '
        body { 
            font-family: -apple-system, system-ui, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #dc3545;
            color: white;
            padding: 2rem;
        }
        .content { padding: 2rem; }
        .error-title {
            font-size: 24px;
            font-weight: 500;
            margin: 0;
        }
        .error-message {
            font-size: 16px;
            margin: 1rem 0;
            color: #666;
        }
        .error-details {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        .stack-trace {
            font-family: monospace;
            font-size: 13px;
            white-space: pre-wrap;
            overflow-x: auto;
            background: #f1f3f5;
            padding: 1rem;
            border-radius: 4px;
            color: #666;
        }
        .file-line {
            color: #007bff;
        }
    ';

    public function __construct($app) {
        $this->app = $app;
        $this->debug = $app->config('debug');
        
        // Registrar handlers apenas para erros críticos
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleError($level, $message, $file = null, $line = null) {
        // Converter error PHP para exceção
        throw new \ErrorException($message, 500, $level, $file, $line);
    }

    public function handleException($e) {
        // Se for erro de sistema (500+) ou debug ativado, mostra detalhes
        if ($e->getCode() >= 500 || $this->debug) {
            $this->renderErrorPage([
                'code' => $e->getCode() ?: 500,
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        } else {
            // Se for erro de aplicação (400-499), apenas mostra a mensagem
            $this->renderErrorPage([
                'code' => $e->getCode(),
                'type' => 'Error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function handleShutdown() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR])) {
            if ($this->debug) {
                $this->handleError(
                    $error['type'],
                    $error['message'],
                    $error['file'],
                    $error['line']
                );
            } else {
                $this->renderErrorPage([
                    'code' => 500,
                    'type' => 'Error', 
                    'message' => 'Internal Server Error'
                ]);
            }
        }
    }

    private function renderErrorPage($error) {
        // Headers
        if (!headers_sent()) {
            http_response_code($error['code']);
            header('Content-Type: text/html; charset=utf-8');
        }

        // Renderizar template baseado no modo debug
        echo $this->debug ? $this->debugTemplate($error) : $this->productionTemplate($error);
        exit;
    }

    private function debugTemplate($error) {
        return '<!DOCTYPE html>
        <html>
        <head>
            <title>' . $error['title'] . '</title>
            <style>' . $this->styles . '</style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1 class="error-title">' . $error['title'] . '</h1>
                </div>
                <div class="content">
                    <div class="error-message">' . $error['message'] . '</div>
                    <div class="error-details">
                        <div class="file-line">
                            ' . $error['file'] . ':' . $error['line'] . '
                        </div>
                        <div class="stack-trace">' . $error['trace'] . '</div>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }

    private function productionTemplate($error) {
        return '<!DOCTYPE html>
        <html>
        <head>
            <title>Server Error</title>
            <style>' . $this->styles . '</style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1 class="error-title">Server Error</h1>
                </div>
                <div class="content">
                    <div class="error-message">
                        An unexpected error occurred. Please try again later.
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
}
