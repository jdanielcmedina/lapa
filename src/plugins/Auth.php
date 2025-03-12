<?php
namespace Lapa\Plugins;

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

    public function login($userId) {
        $this->app->session('user_id', $userId);
        return true;
    }

    public function logout() {
        $this->app->session('user_id', false);
        return true;
    }
}
