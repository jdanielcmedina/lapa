<?php

class Auth {
    private $app;
    
    public function __construct($app) {
        $this->app = $app;
    }
    
    public function check() {
        return $this->app->session('user_id') !== null;
    }
    
    public function user() {
        $id = $this->app->session('user_id');
        return $id ? $this->app->db->get('users', '*', ['id' => $id]) : null;
    }
}

// Registrar o helper
return function($app) {
    $app->auth = new Auth($app);
};
