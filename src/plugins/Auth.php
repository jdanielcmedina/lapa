<?php
namespace Lapa\Plugins;

class Auth {
    private $app;
    
    public function __construct($app) {
        $this->app = $app;
    }
    
    // Seus métodos aqui
}

// No seu arquivo de rotas ou bootstrap
$auth = new \Lapa\Plugins\Auth($app);
$app->auth = $auth;
