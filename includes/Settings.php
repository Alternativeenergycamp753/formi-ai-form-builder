<?php
require_once __DIR__ . '/Env.php';

class Settings {
    private static $db;
    private static $instance = null;
    private $settings = [];
    private $env;
    const UPLOAD_DIR = __DIR__ . '/../uploads/';
    const MAX_RETRIES = 3;
    const RETRY_DELAY_MS = 100;
    const DB_VERSION = 2; // Add version tracking

    private function __construct() {
        $this->initializeDatabase();
        $this->env = Env::getInstance();
        $this->loadSettings();
        $this->runMigrations();
        
        // Create uploads directory if it doesn't exist
        if (!file_exists(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }
    }

    private function initializeDatabase() {
        try {
            self::$db = new SQLite3(__DIR__ . '/../database.sqlite');
            self::$db->busyTimeout(5000); // Set timeout to 5 seconds
            self::$db->exec('PRAGMA journal_mode = WAL'); // Use Write-Ahead Logging
            self::$db->exec('PRAGMA synchronous = NORMAL'); // Reduce synchronous mode
            
            // Create settings table if not exists
            self::$db->exec('CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');

            // Create custom forms table if not exists
            self::$db->exec('CREATE TABLE IF NOT EXISTS custom_forms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                slug TEXT UNIQUE,
                form_fields TEXT NOT NULL,
                ai_prompt TEXT NOT NULL,
                is_default INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');

            // Create pages table if not exists
            self::$db->exec('CREATE TABLE IF NOT EXISTS pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT UNIQUE NOT NULL,
                title TEXT NOT NULL,
                content TEXT,
                show_in_footer INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');

            // Create hero section table
            self::$db->exec('CREATE TABLE IF NOT EXISTS hero_sections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                subtitle TEXT,
                button_text TEXT,
                button_url TEXT,
                is_active INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');

            // Create features table
            self::$db->exec('CREATE TABLE IF NOT EXISTS features (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                icon TEXT,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');

            // Create FAQ table
            self::$db->exec('CREATE TABLE IF NOT EXISTS faqs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                question TEXT NOT NULL,
                answer TEXT NOT NULL,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');

            // Create about section table
            self::$db->exec('CREATE TABLE IF NOT EXISTS about_sections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                is_active INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');

            // Insert default settings if not exists
            $default_settings = [
                ['site_title', 'AI Question Generator'],
                ['seo_title', 'AI Question Generator - Create Educational Questions'],
                ['show_site_title', '1'],
                ['site_logo', ''],
                ['site_favicon', ''],
                ['meta_description', 'AI-powered question generator for educators'],
                ['primary_color', '#0d6efd']
            ];

            $stmt = self::$db->prepare('INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
            foreach ($default_settings as $setting) {
                $stmt->reset();
                $stmt->bindValue(1, $setting[0]);
                $stmt->bindValue(2, $setting[1]);
                $this->executeWithRetry($stmt);
            }

            // Insert default hero section if not exists
            $result = self::$db->query('SELECT COUNT(*) as count FROM hero_sections');
            $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
            if ($count === 0) {
                $stmt = self::$db->prepare('INSERT INTO hero_sections (title, subtitle, button_text, button_url, is_active) VALUES (?, ?, ?, ?, 1)');
                $stmt->bindValue(1, 'AI-Powered Question Generator');
                $stmt->bindValue(2, 'Create educational questions instantly with artificial intelligence');
                $stmt->bindValue(3, 'Get Started');
                $stmt->bindValue(4, '#generator');
                $this->executeWithRetry($stmt);
            }

            // Insert default about section if not exists
            $result = self::$db->query('SELECT COUNT(*) as count FROM about_sections');
            $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
            if ($count === 0) {
                $stmt = self::$db->prepare('INSERT INTO about_sections (title, content, is_active) VALUES (?, ?, 1)');
                $stmt->bindValue(1, 'About Our Platform');
                $stmt->bindValue(2, '<p>Our AI-powered question generator helps educators create high-quality educational content quickly and efficiently.</p>');
                $this->executeWithRetry($stmt);
            }

            // Insert default features if not exists
            $result = self::$db->query('SELECT COUNT(*) as count FROM features');
            $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
            if ($count === 0) {
                $default_features = [
                    ['AI-Powered Generation', 'Generate questions instantly using advanced AI technology', 'robot', 1],
                    ['Multiple Question Types', 'Support for multiple choice, short answer, and long answer questions', 'list-check', 2],
                    ['Customizable Difficulty', 'Adjust difficulty levels to match your students\' needs', 'sliders', 3]
                ];
                $stmt = self::$db->prepare('INSERT INTO features (title, description, icon, sort_order) VALUES (?, ?, ?, ?)');
                foreach ($default_features as $feature) {
                    $stmt->reset();
                    $stmt->bindValue(1, $feature[0]);
                    $stmt->bindValue(2, $feature[1]);
                    $stmt->bindValue(3, $feature[2]);
                    $stmt->bindValue(4, $feature[3]);
                    $this->executeWithRetry($stmt);
                }
            }

            // Insert default FAQs if not exists
            $result = self::$db->query('SELECT COUNT(*) as count FROM faqs');
            $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
            if ($count === 0) {
                $default_faqs = [
                    ['How does it work?', 'Our platform uses advanced AI to generate educational questions based on your input.', 1],
                    ['What types of questions can I generate?', 'You can generate multiple choice, short answer, and long answer questions.', 2],
                    ['Can I customize the difficulty level?', 'Yes, you can choose from easy, medium, and hard difficulty levels.', 3]
                ];
                $stmt = self::$db->prepare('INSERT INTO faqs (question, answer, sort_order) VALUES (?, ?, ?)');
                foreach ($default_faqs as $faq) {
                    $stmt->reset();
                    $stmt->bindValue(1, $faq[0]);
                    $stmt->bindValue(2, $faq[1]);
                    $stmt->bindValue(3, $faq[2]);
                    $this->executeWithRetry($stmt);
                }
            }
        } catch (Exception $e) {
            error_log("Database initialization error: " . $e->getMessage());
            throw $e;
        }
    }

    private function executeWithRetry($stmt) {
        $retries = 0;
        while ($retries < self::MAX_RETRIES) {
            try {
                $result = $stmt->execute();
                if ($result !== false) {
                    return $result;
                }
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'database is locked') === false) {
                    throw $e;
                }
            }
            $retries++;
            if ($retries < self::MAX_RETRIES) {
                usleep(self::RETRY_DELAY_MS * 1000); // Convert to microseconds
            }
        }
        throw new Exception("Failed to execute statement after " . self::MAX_RETRIES . " retries");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Settings();
        }
        return self::$instance;
    }

    private function loadSettings() {
        try {
            $result = self::$db->query('SELECT setting_key, setting_value FROM settings');
            if ($result) {
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $this->settings[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (Exception $e) {
            error_log("Error loading settings: " . $e->getMessage());
        }
    }

    public function get($key, $default = '') {
        // Check if it's an environment variable
        if (in_array($key, ['api_key', 'ai_model'])) {
            $envKey = 'OPENROUTER_' . strtoupper($key);
            if ($key === 'api_key') $envKey = 'OPENROUTER_API_KEY';
            if ($key === 'ai_model') $envKey = 'AI_MODEL';
            return $this->env->get($envKey, $default);
        }
        return $this->settings[$key] ?? $default;
    }

    public function set($key, $value) {
        try {
            // Handle environment variables
            if (in_array($key, ['api_key', 'ai_model'])) {
                $envKey = 'OPENROUTER_' . strtoupper($key);
                if ($key === 'api_key') $envKey = 'OPENROUTER_API_KEY';
                if ($key === 'ai_model') $envKey = 'AI_MODEL';
                return $this->env->set($envKey, $value);
            }

            // Handle database settings
            $stmt = self::$db->prepare('INSERT OR REPLACE INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)');
            $stmt->bindValue(1, $key);
            $stmt->bindValue(2, $value);
            $this->executeWithRetry($stmt);
            $this->settings[$key] = $value;
            return true;
        } catch (Exception $e) {
            error_log("Error setting value: " . $e->getMessage());
            return false;
        }
    }

    public function getAvailableModels() {
        try {
            $ch = curl_init('https://openrouter.ai/api/v1/models');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->env->get('OPENROUTER_API_KEY'),
                'HTTP-Referer: http://localhost',
                'X-Title: AI Question Generator'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $models = json_decode($response, true);
                if (isset($models['data'])) {
                    // Sort models by pricing (cheapest first)
                    usort($models['data'], function($a, $b) {
                        $a_price = $a['pricing']['prompt'] ?? PHP_FLOAT_MAX;
                        $b_price = $b['pricing']['prompt'] ?? PHP_FLOAT_MAX;
                        return $a_price <=> $b_price;
                    });
                    return $models['data'];
                }
            }
            return [];
        } catch (Exception $e) {
            error_log("Error fetching models: " . $e->getMessage());
            return [];
        }
    }

    public function uploadImage($file, $type) {
        try {
            $allowedTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/x-icon'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Invalid file type. Only PNG, JPEG, GIF, and ICO files are allowed.');
            }

            $maxSize = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxSize) {
                throw new Exception('File is too large. Maximum size is 5MB.');
            }

            $filename = $type . '_' . time() . '_' . basename($file['name']);
            $targetPath = self::UPLOAD_DIR . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Delete old file if exists
                $oldFile = $this->get($type);
                if ($oldFile && file_exists(self::UPLOAD_DIR . $oldFile)) {
                    unlink(self::UPLOAD_DIR . $oldFile);
                }

                $this->set($type, $filename);
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Error uploading image: " . $e->getMessage());
            return false;
        }
    }

    public function getImageUrl($type) {
        $filename = $this->get($type);
        return $filename ? '/uploads/' . $filename : '';
    }

    public function getAll() {
        return array_merge($this->settings, [
            'api_key' => $this->env->get('OPENROUTER_API_KEY'),
            'ai_model' => $this->env->get('AI_MODEL')
        ]);
    }

    private function runMigrations() {
        $currentVersion = (int)$this->get('db_version', 0);
        
        if ($currentVersion < self::DB_VERSION) {
            // Start transaction
            self::$db->exec('BEGIN TRANSACTION');
            
            try {
                // Run migrations based on current version
                if ($currentVersion < 1) {
                    // Migration for adding slug column
                    $this->migrateCustomFormsSlug();
                }
                
                // Update database version
                $this->set('db_version', self::DB_VERSION);
                
                // Commit transaction
                self::$db->exec('COMMIT');
            } catch (Exception $e) {
                // Rollback on error
                self::$db->exec('ROLLBACK');
                throw $e;
            }
        }
    }

    private function migrateCustomFormsSlug() {
        // Create new table with desired schema
        self::$db->exec('CREATE TABLE IF NOT EXISTS custom_forms_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            slug TEXT UNIQUE NOT NULL,
            form_fields TEXT NOT NULL,
            ai_prompt TEXT NOT NULL,
            is_default INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        // Copy data from old table to new table, generating slugs
        $result = self::$db->query('SELECT * FROM custom_forms');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Generate slug from name
            $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $row['name'])));
            $slug = $baseSlug;
            $counter = 1;
            
            // Ensure slug uniqueness
            while (true) {
                $check = self::$db->prepare('SELECT id FROM custom_forms_new WHERE slug = ?');
                $check->bindValue(1, $slug);
                if (!$check->execute()->fetchArray()) {
                    break;
                }
                $slug = $baseSlug . '-' . $counter++;
            }
            
            // Insert data with new slug
            $stmt = self::$db->prepare('INSERT INTO custom_forms_new 
                (id, name, description, slug, form_fields, ai_prompt, is_default, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bindValue(1, $row['id']);
            $stmt->bindValue(2, $row['name']);
            $stmt->bindValue(3, $row['description']);
            $stmt->bindValue(4, $slug);
            $stmt->bindValue(5, $row['form_fields']);
            $stmt->bindValue(6, $row['ai_prompt']);
            $stmt->bindValue(7, $row['is_default']);
            $stmt->bindValue(8, $row['created_at']);
            $stmt->bindValue(9, $row['updated_at']);
            $stmt->execute();
        }

        // Drop old table and rename new table
        self::$db->exec('DROP TABLE IF EXISTS custom_forms');
        self::$db->exec('ALTER TABLE custom_forms_new RENAME TO custom_forms');

        // Create index for slug
        self::$db->exec('CREATE INDEX IF NOT EXISTS idx_custom_forms_slug ON custom_forms(slug)');
    }
} 