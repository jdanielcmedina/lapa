<?php

     /**
     * Protect a route with authentication
     *
     * @param callable|null $callback Custom authentication function
     * @return self
     * @throws \Exception If authentication fails
     */
    public function protect($callback = null) {
        $isAuthorized = false;

        if (!$callback) {
            // Default protection - check session
            $isAuthorized = isset($_SESSION['user']);
        } else {
            // Custom protection
            $isAuthorized = $callback($this);
        }

        if (!$isAuthorized) {
            $this->error('Access denied', 403);
            exit;
        }

        return $this;
    }
