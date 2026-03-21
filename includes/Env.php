<?php
class Env {
    private static $instance = null;
    private $envFile;
    private $env = [];

    private function __construct() {
        $this->envFile = __DIR__ . '/../.env';
        $this->loadEnv();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Env();
        }
        return self::$instance;
    }

    private function loadEnv() {
        if (file_exists($this->envFile)) {
            $lines = file($this->envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos(trim($line), '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    if (preg_match('/^(["\']).*\1$/', $value)) {
                        $value = substr($value, 1, -1);
                    }
                    
                    $this->env[$key] = $value;
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
        } else {
            // Create default .env file if it doesn't exist
            $this->createDefaultEnv();
        }
    }

    private function createDefaultEnv() {
        $defaultEnv = [
            'OPENROUTER_API_KEY' => '',
            'OPENROUTER_API_URL' => 'https://openrouter.ai/api/v1/chat/completions',
            'AI_MODEL' => 'microsoft/phi-4',
            'ADMIN_USERNAME' => 'admin',
            'ADMIN_PASSWORD' => 'admin123'
        ];

        $content = "";
        foreach ($defaultEnv as $key => $value) {
            $content .= "$key=\"$value\"\n";
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $this->env[$key] = $value;
        }

        file_put_contents($this->envFile, $content);
    }

    public function get($key, $default = '') {
        return $this->env[$key] ?? $default;
    }

    public function set($key, $value) {
        try {
            $this->env[$key] = $value;
            
            // Update environment
            putenv("$key=$value");
            $_ENV[$key] = $value;
            
            // Update .env file
            $content = "";
            foreach ($this->env as $k => $v) {
                // Escape quotes in value
                $v = str_replace('"', '\"', $v);
                $content .= "$k=\"$v\"\n";
            }
            
            file_put_contents($this->envFile, $content);
            return true;
        } catch (Exception $e) {
            error_log("Error setting environment variable: " . $e->getMessage());
            return false;
        }
    }

    public function verifyAdminPassword($password) {
        return $password === $this->get('ADMIN_PASSWORD');
    }

    public function getAll() {
        return $this->env;
    }
} 