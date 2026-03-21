<?php
$db = new SQLite3('database.sqlite');

// Enable foreign key support
$db->exec('PRAGMA foreign_keys = ON');

// Create settings table
$db->exec('CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key TEXT UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Create custom forms table
$db->exec('CREATE TABLE IF NOT EXISTS custom_forms (
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

// Create index for slug
$db->exec('CREATE INDEX IF NOT EXISTS idx_custom_forms_slug ON custom_forms(slug)');

// Create pages table
$db->exec('CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    content TEXT NOT NULL,
    show_in_footer INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Create hero sections table
$db->exec('CREATE TABLE IF NOT EXISTS hero_sections (
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
$db->exec('CREATE TABLE IF NOT EXISTS features (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    icon TEXT NOT NULL,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Create about sections table
$db->exec('CREATE TABLE IF NOT EXISTS about_sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    is_active INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Create FAQs table
$db->exec('CREATE TABLE IF NOT EXISTS faqs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Insert default settings if not exists
$default_settings = [
    ['site_title', 'AI Question Generator'],
    ['site_logo', 'bi-mortarboard-fill'],
    ['meta_description', 'AI-powered question generator for educators'],
    ['primary_color', '#0d6efd'],
    ['api_key', ''],
    ['ai_model', 'microsoft/phi-4'],
    ['ai_prompt', 'Generate {count} {difficulty} {question_types} questions about {topic} suitable for grade {grade} students. {additional_info} {include_answers_text}'],
    ['grade_levels', "1:Grade 1\n2:Grade 2\n3:Grade 3\n4:Grade 4\n5:Grade 5\n6:Grade 6\n7:Grade 7\n8:Grade 8\n9:Grade 9\n10:Grade 10\n11:Grade 11\n12:Grade 12\ncollege:College"],
    ['difficulty_levels', "easy:Easy\nmedium:Medium\nhard:Hard"],
    ['question_types', "mcq:Multiple Choice\nshort:Short Answer\nlong:Long Answer"],
    ['default_count', '5'],
    ['default_grade', '6'],
    ['default_difficulty', 'medium'],
    ['default_types', 'mcq']
];

$stmt = $db->prepare('INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
foreach ($default_settings as $setting) {
    $stmt->reset();
    $stmt->bindValue(1, $setting[0]);
    $stmt->bindValue(2, $setting[1]);
    $stmt->execute();
}

// Create sample FAQ
$db->exec("INSERT OR IGNORE INTO faqs (question, answer, sort_order) VALUES 
    ('What is an AI Question Generator?', 'An AI Question Generator is a tool that uses artificial intelligence to create educational questions based on your input. It can generate various types of questions like multiple choice, short answer, and long answer questions.', 1)");

// Create sample feature
$db->exec("INSERT OR IGNORE INTO features (title, description, icon, sort_order) VALUES 
    ('AI-Powered Questions', 'Generate high-quality educational questions using advanced artificial intelligence technology.', 'robot', 1)");

echo "Database setup completed successfully!\n"; 