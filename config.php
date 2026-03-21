<?php
require_once __DIR__ . '/includes/Env.php';
$env = Env::getInstance();

// Constants are defined from environment variables
define('OPENROUTER_API_KEY', $env->get('OPENROUTER_API_KEY'));
define('OPENROUTER_API_URL', $env->get('OPENROUTER_API_URL'));
define('AI_MODEL', $env->get('AI_MODEL')); 