<?php
require_once 'includes/Settings.php';
require_once 'includes/Env.php';
require_once 'vendor/autoload.php';

$settings = Settings::getInstance();
$env = Env::getInstance();

// Check if API key is configured
if (empty($env->get('OPENROUTER_API_KEY'))) {
    http_response_code(400);
    echo '<div class="alert alert-danger">OpenRouter API key is not configured. Please set it in the admin panel.</div>';
    exit;
}

// Get form data
$form_id = $_POST['form_id'] ?? null;

if ($form_id) {
    // Handle custom form submission
    $db = new SQLite3('database.sqlite');
    $stmt = $db->prepare('SELECT * FROM custom_forms WHERE id = ?');
    $stmt->bindValue(1, $form_id);
    $result = $stmt->execute();
    $form = $result->fetchArray(SQLITE3_ASSOC);

    if (!$form) {
        http_response_code(400);
        echo '<div class="alert alert-danger">Form not found.</div>';
        exit;
    }

    // Build prompt from form fields
    $prompt = $form['ai_prompt'];
    $form_fields = json_decode($form['form_fields'], true);
    
    // Validate required fields
    foreach ($form_fields as $field) {
        if ($field['required'] && empty($_POST[$field['name']])) {
            http_response_code(400);
            echo '<div class="alert alert-danger">Please fill in all required fields.</div>';
            exit;
        }
        
        // Replace field placeholders in prompt
        $value = $_POST[$field['name']] ?? '';
        $prompt = str_replace('{' . $field['name'] . '}', $value, $prompt);
    }
} else {
    // Handle default question form
    $topic = $_POST['topic'] ?? '';
    $grade = $_POST['grade'] ?? '';
    $difficulty = $_POST['difficulty'] ?? '';
    $count = intval($_POST['count'] ?? 5);
    $include_answers = isset($_POST['include_answers']);
    $types = $_POST['types'] ?? [];
    $additional_info = $_POST['additional_info'] ?? '';

    // Validate input
    if (empty($topic) || empty($grade) || empty($difficulty) || empty($types)) {
        http_response_code(400);
        echo '<div class="alert alert-danger">Please fill in all required fields.</div>';
        exit;
    }

    // Format question types for prompt
    $question_types_map = [];
    foreach (explode("\n", $settings->get('question_types')) as $line) {
        $parts = explode(':', trim($line));
        if (count($parts) === 2) {
            $question_types_map[$parts[0]] = $parts[1];
        }
    }

    $type_labels = array_map(function($type) use ($question_types_map) {
        return $question_types_map[$type] ?? $type;
    }, $types);

    // Build the prompt
    $prompt = $settings->get('ai_prompt');
    $prompt = str_replace('{count}', $count, $prompt);
    $prompt = str_replace('{difficulty}', $difficulty, $prompt);
    $prompt = str_replace('{question_types}', implode(' and ', $type_labels), $prompt);
    $prompt = str_replace('{topic}', $topic, $prompt);
    $prompt = str_replace('{grade}', $grade, $prompt);
    $prompt = str_replace('{additional_info}', $additional_info, $prompt);
    $prompt = str_replace('{include_answers_text}', $include_answers ? 'Include answers.' : '', $prompt);
}

// Make API request
$api_key = $env->get('OPENROUTER_API_KEY');
$model = $env->get('AI_MODEL', 'microsoft/phi-2');

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $api_key,
    'HTTP-Referer: http://localhost',
    'Content-Type: application/json'
]);

$data = [
    'model' => $model,
    'messages' => [
        ['role' => 'user', 'content' => $prompt]
    ]
];

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Parse the response
$result = json_decode($response, true);
if ($http_code !== 200 || !isset($result['choices'][0]['message']['content'])) {
    http_response_code(500);
    $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown error occurred.';
    echo '<div class="alert alert-danger">API Error: ' . htmlspecialchars($error_message) . '</div>';
    exit;
}

// Display the generated questions
$generated_text = $result['choices'][0]['message']['content'];

// Initialize Parsedown
$parsedown = new Parsedown();
$parsedown->setSafeMode(true);

?>

<div class="card border">
    <div class="card-body">
        <div class="d-flex justify-content-end mb-3">
            <div>
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary me-2 no-print">
                    <i class="bi bi-printer"></i> Print
                </button>
                <button onclick="copyContent()" class="btn btn-sm btn-outline-primary no-print">
                    <i class="bi bi-clipboard"></i> Copy
                </button>
                <span class="copy-success ms-2">Copied!</span>
            </div>
        </div>
        <div class="generated-questions">
            <?php echo $parsedown->text($generated_text); ?>
        </div>
    </div>
</div> 