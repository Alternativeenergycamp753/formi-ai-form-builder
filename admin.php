<?php
require_once 'includes/auth.php';
require_once 'includes/Settings.php';
require_once 'includes/Env.php';

// Get base URL without trailing slash
$base_url = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']), '/');

$settings = Settings::getInstance();
$env = Env::getInstance();
$db = new SQLite3('database.sqlite');

// Fetch available models
$available_models = $settings->getAvailableModels();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = true;
    $error = '';

    if (isset($_POST['form_type'])) {
        if ($_POST['form_type'] === 'credentials') {
            // Handle credentials update
            $current_password = $_POST['current_password'] ?? '';
            $new_username = $_POST['new_username'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            
            if ($env->verifyAdminPassword($current_password)) {
                if (!empty($new_username)) {
                    $env->set('ADMIN_USERNAME', $new_username);
                }
                if (!empty($new_password)) {
                    $env->set('ADMIN_PASSWORD', $new_password);
                }
                $success = true;
            } else {
                $success = false;
                $error = 'Current password is incorrect';
            }
        } elseif ($_POST['form_type'] === 'form') {
            // Handle form settings
            $settings->set('ai_prompt', $_POST['ai_prompt']);
            $settings->set('grade_levels', $_POST['grade_levels']);
            $settings->set('difficulty_levels', $_POST['difficulty_levels']);
            $settings->set('question_types', $_POST['question_types']);
            $settings->set('default_count', $_POST['default_count']);
            $settings->set('default_grade', $_POST['default_grade']);
            $settings->set('default_difficulty', $_POST['default_difficulty']);
            $settings->set('default_types', $_POST['default_types']);
            $success = true;
        } elseif ($_POST['form_type'] === 'custom_form') {
            // Handle custom form creation/update
            $form_id = $_POST['form_id'] ?? null;
            $form_name = $_POST['form_name'] ?? '';
            $form_description = $_POST['form_description'] ?? '';
            $form_fields = $_POST['form_fields'] ?? '[]';
            $ai_prompt = $_POST['ai_prompt'] ?? '';
            $is_default = isset($_POST['is_default']) ? 1 : 0;

            if (empty($form_name) || empty($form_fields) || empty($ai_prompt)) {
                $success = false;
                $error = 'Please fill in all required fields.';
            } else {
                // Generate slug from name
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $form_name)));
                
                // Ensure slug uniqueness
                $base_slug = $slug;
                $counter = 1;
                while (true) {
                    $check_stmt = $db->prepare('SELECT id FROM custom_forms WHERE slug = ? AND id != ?');
                    $check_stmt->bindValue(1, $slug);
                    $check_stmt->bindValue(2, $form_id ?? 0);
                    $result = $check_stmt->execute();
                    if (!$result->fetchArray()) {
                        break;
                    }
                    $slug = $base_slug . '-' . $counter++;
                }

                if ($is_default) {
                    // Reset all other forms to non-default
                    $db->exec('UPDATE custom_forms SET is_default = 0');
                }

                if ($form_id) {
                    // Update existing form
                    $stmt = $db->prepare('UPDATE custom_forms SET 
                        name = ?, description = ?, slug = ?, form_fields = ?, ai_prompt = ?, is_default = ?,
                        updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?');
                    $stmt->bindValue(1, $form_name);
                    $stmt->bindValue(2, $form_description);
                    $stmt->bindValue(3, $slug);
                    $stmt->bindValue(4, $form_fields);
                    $stmt->bindValue(5, $ai_prompt);
                    $stmt->bindValue(6, $is_default);
                    $stmt->bindValue(7, $form_id);
                } else {
                    // Create new form
                    $stmt = $db->prepare('INSERT INTO custom_forms 
                        (name, description, slug, form_fields, ai_prompt, is_default) 
                        VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->bindValue(1, $form_name);
                    $stmt->bindValue(2, $form_description);
                    $stmt->bindValue(3, $slug);
                    $stmt->bindValue(4, $form_fields);
                    $stmt->bindValue(5, $ai_prompt);
                    $stmt->bindValue(6, $is_default);
                }
                
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to save the form.';
                }
            }
        } elseif ($_POST['form_type'] === 'delete_form') {
            // Handle form deletion
            $form_id = $_POST['form_id'] ?? null;
            if ($form_id) {
                $stmt = $db->prepare('DELETE FROM custom_forms WHERE id = ?');
                $stmt->bindValue(1, $form_id);
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to delete the form.';
                }
            } else {
                $success = false;
                $error = 'Invalid form ID.';
            }
        } elseif ($_POST['form_type'] === 'reset_default_forms') {
            // Reset all custom forms to non-default
            $success = $db->exec('UPDATE custom_forms SET is_default = 0');
            if (!$success) {
                $error = 'Failed to reset default forms.';
            }
        } elseif ($_POST['form_type'] === 'set_default_form') {
            // Handle setting default form
            $form_id = $_POST['form_id'] ?? null;
            if ($form_id) {
                // Reset all forms to non-default
                $db->exec('UPDATE custom_forms SET is_default = 0');
                
                // Set the selected form as default
                $stmt = $db->prepare('UPDATE custom_forms SET is_default = 1 WHERE id = ?');
                $stmt->bindValue(1, $form_id);
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to set the default form.';
                }
            } else {
                $success = false;
                $error = 'Invalid form ID.';
            }
        } elseif ($_POST['form_type'] === 'custom_code') {
            // Handle custom code settings
            $settings->set('header_code', $_POST['header_code']);
            $settings->set('footer_code', $_POST['footer_code']);
            $settings->set('ad_code', $_POST['ad_code']);
            $success = true;
        } elseif ($_POST['form_type'] === 'page') {
            // Handle page creation/update
            $page_id = $_POST['page_id'] ?? null;
            $title = $_POST['title'] ?? '';
            $slug = $_POST['slug'] ?? '';
            $content = $_POST['content'] ?? '';
            $show_in_footer = isset($_POST['show_in_footer']) ? 1 : 0;

            if (empty($title) || empty($slug)) {
                $success = false;
                $error = 'Please fill in all required fields.';
            } else {
                if ($page_id) {
                    // Update existing page
                    $stmt = $db->prepare('UPDATE pages SET 
                        title = ?, slug = ?, content = ?, show_in_footer = ?,
                        updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?');
                    $stmt->bindValue(1, $title);
                    $stmt->bindValue(2, $slug);
                    $stmt->bindValue(3, $content);
                    $stmt->bindValue(4, $show_in_footer);
                    $stmt->bindValue(5, $page_id);
                } else {
                    // Create new page
                    $stmt = $db->prepare('INSERT INTO pages 
                        (title, slug, content, show_in_footer) 
                        VALUES (?, ?, ?, ?)');
                    $stmt->bindValue(1, $title);
                    $stmt->bindValue(2, $slug);
                    $stmt->bindValue(3, $content);
                    $stmt->bindValue(4, $show_in_footer);
                }
                
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to save the page.';
                }
            }
        } elseif ($_POST['form_type'] === 'delete_page') {
            // Handle page deletion
            $page_id = $_POST['page_id'] ?? null;
            if ($page_id) {
                $stmt = $db->prepare('DELETE FROM pages WHERE id = ?');
                $stmt->bindValue(1, $page_id);
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to delete the page.';
                }
            } else {
                $success = false;
                $error = 'Invalid page ID.';
            }
        } elseif ($_POST['form_type'] === 'toggle_footer') {
            // Handle footer visibility toggle
            $page_id = $_POST['page_id'] ?? null;
            $show_in_footer = $_POST['show_in_footer'] ?? '0';
            
            if ($page_id) {
                $stmt = $db->prepare('UPDATE pages SET show_in_footer = ? WHERE id = ?');
                $stmt->bindValue(1, $show_in_footer);
                $stmt->bindValue(2, $page_id);
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to update footer visibility.';
                }
            } else {
                $success = false;
                $error = 'Invalid page ID.';
            }
        } elseif ($_POST['form_type'] === 'hero') {
            // Handle hero section creation/update
            $hero_id = $_POST['hero_id'] ?? null;
            $title = $_POST['title'] ?? '';
            $subtitle = $_POST['subtitle'] ?? '';
            $button_text = $_POST['button_text'] ?? '';
            $button_url = $_POST['button_url'] ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($title)) {
                $success = false;
                $error = 'Title is required.';
            } else {
                if ($is_active) {
                    $db->exec('UPDATE hero_sections SET is_active = 0');
                }

                if ($hero_id) {
                    $stmt = $db->prepare('UPDATE hero_sections SET 
                        title = ?, subtitle = ?, button_text = ?, button_url = ?, is_active = ?,
                        updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?');
                    $stmt->bindValue(1, $title);
                    $stmt->bindValue(2, $subtitle);
                    $stmt->bindValue(3, $button_text);
                    $stmt->bindValue(4, $button_url);
                    $stmt->bindValue(5, $is_active);
                    $stmt->bindValue(6, $hero_id);
                } else {
                    $stmt = $db->prepare('INSERT INTO hero_sections 
                        (title, subtitle, button_text, button_url, is_active) 
                        VALUES (?, ?, ?, ?, ?)');
                    $stmt->bindValue(1, $title);
                    $stmt->bindValue(2, $subtitle);
                    $stmt->bindValue(3, $button_text);
                    $stmt->bindValue(4, $button_url);
                    $stmt->bindValue(5, $is_active);
                }
                
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to save hero section.';
                }
            }
        } elseif ($_POST['form_type'] === 'feature') {
            // Handle feature creation/update
            $feature_id = $_POST['feature_id'] ?? null;
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $icon = $_POST['icon'] ?? '';
            $sort_order = $_POST['sort_order'] ?? 0;

            if (empty($title) || empty($icon)) {
                $success = false;
                $error = 'Title and icon are required.';
            } else {
                if ($feature_id) {
                    $stmt = $db->prepare('UPDATE features SET 
                        title = ?, description = ?, icon = ?, sort_order = ?,
                        updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?');
                    $stmt->bindValue(1, $title);
                    $stmt->bindValue(2, $description);
                    $stmt->bindValue(3, $icon);
                    $stmt->bindValue(4, $sort_order);
                    $stmt->bindValue(5, $feature_id);
                } else {
                    $stmt = $db->prepare('INSERT INTO features 
                        (title, description, icon, sort_order) 
                        VALUES (?, ?, ?, ?)');
                    $stmt->bindValue(1, $title);
                    $stmt->bindValue(2, $description);
                    $stmt->bindValue(3, $icon);
                    $stmt->bindValue(4, $sort_order);
                }
                
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to save feature.';
                }
            }
        } elseif ($_POST['form_type'] === 'about') {
            // Handle about section creation/update
            $about_id = $_POST['about_id'] ?? null;
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($title) || empty($content)) {
                $success = false;
                $error = 'Title and content are required.';
            } else {
                if ($is_active) {
                    $db->exec('UPDATE about_sections SET is_active = 0');
                }

                if ($about_id) {
                    $stmt = $db->prepare('UPDATE about_sections SET 
                        title = ?, content = ?, is_active = ?,
                        updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?');
                    $stmt->bindValue(1, $title);
                    $stmt->bindValue(2, $content);
                    $stmt->bindValue(3, $is_active);
                    $stmt->bindValue(4, $about_id);
                } else {
                    $stmt = $db->prepare('INSERT INTO about_sections 
                        (title, content, is_active) 
                        VALUES (?, ?, ?)');
                    $stmt->bindValue(1, $title);
                    $stmt->bindValue(2, $content);
                    $stmt->bindValue(3, $is_active);
                }
                
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to save about section.';
                }
            }
        } elseif ($_POST['form_type'] === 'faq') {
            // Handle FAQ creation/update
            $faq_id = $_POST['faq_id'] ?? null;
            $question = $_POST['question'] ?? '';
            $answer = $_POST['answer'] ?? '';
            $sort_order = $_POST['sort_order'] ?? 0;

            if (empty($question) || empty($answer)) {
                $success = false;
                $error = 'Question and answer are required.';
            } else {
                if ($faq_id) {
                    $stmt = $db->prepare('UPDATE faqs SET 
                        question = ?, answer = ?, sort_order = ?,
                        updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?');
                    $stmt->bindValue(1, $question);
                    $stmt->bindValue(2, $answer);
                    $stmt->bindValue(3, $sort_order);
                    $stmt->bindValue(4, $faq_id);
                } else {
                    $stmt = $db->prepare('INSERT INTO faqs 
                        (question, answer, sort_order) 
                        VALUES (?, ?, ?)');
                    $stmt->bindValue(1, $question);
                    $stmt->bindValue(2, $answer);
                    $stmt->bindValue(3, $sort_order);
                }
                
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to save FAQ.';
                }
            }
        } elseif ($_POST['form_type'] === 'delete_hero') {
            // Handle hero deletion
            $hero_id = $_POST['hero_id'] ?? null;
            if ($hero_id) {
                $stmt = $db->prepare('DELETE FROM hero_sections WHERE id = ?');
                $stmt->bindValue(1, $hero_id);
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to delete hero section.';
                }
            }
        } elseif ($_POST['form_type'] === 'delete_feature') {
            // Handle feature deletion
            $feature_id = $_POST['feature_id'] ?? null;
            if ($feature_id) {
                $stmt = $db->prepare('DELETE FROM features WHERE id = ?');
                $stmt->bindValue(1, $feature_id);
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to delete feature.';
                }
            }
        } elseif ($_POST['form_type'] === 'delete_about') {
            // Handle about deletion
            $about_id = $_POST['about_id'] ?? null;
            if ($about_id) {
                $stmt = $db->prepare('DELETE FROM about_sections WHERE id = ?');
                $stmt->bindValue(1, $about_id);
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to delete about section.';
                }
            }
        } elseif ($_POST['form_type'] === 'delete_faq') {
            // Handle FAQ deletion
            $faq_id = $_POST['faq_id'] ?? null;
            if ($faq_id) {
                $stmt = $db->prepare('DELETE FROM faqs WHERE id = ?');
                $stmt->bindValue(1, $faq_id);
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to delete FAQ.';
                }
            }
        } elseif ($_POST['form_type'] === 'set_active_hero') {
            // Handle setting active hero
            $hero_id = $_POST['hero_id'] ?? null;
            if ($hero_id) {
                $db->exec('UPDATE hero_sections SET is_active = 0');
                $stmt = $db->prepare('UPDATE hero_sections SET is_active = 1 WHERE id = ?');
                $stmt->bindValue(1, $hero_id);
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to set active hero section.';
                }
            }
        } elseif ($_POST['form_type'] === 'set_active_about') {
            // Handle setting active about
            $about_id = $_POST['about_id'] ?? null;
            if ($about_id) {
                $db->exec('UPDATE about_sections SET is_active = 0');
                $stmt = $db->prepare('UPDATE about_sections SET is_active = 1 WHERE id = ?');
                $stmt->bindValue(1, $about_id);
                $success = $stmt->execute();
                if (!$success) {
                    $error = 'Failed to set active about section.';
                }
            }
        } else {
            // Handle file uploads
            if (!empty($_FILES['site_logo']['name'])) {
                if (!$settings->uploadImage($_FILES['site_logo'], 'site_logo')) {
                    $success = false;
                    $error = 'Error uploading logo. ';
                }
            }
            
            if (!empty($_FILES['site_favicon']['name'])) {
                if (!$settings->uploadImage($_FILES['site_favicon'], 'site_favicon')) {
                    $success = false;
                    $error .= 'Error uploading favicon.';
                }
            }

            // Handle other settings
            $settings->set('site_title', $_POST['site_title']);
            $settings->set('seo_title', $_POST['seo_title']);
            $settings->set('show_site_title', isset($_POST['show_site_title']) ? '1' : '0');
            $settings->set('meta_description', $_POST['meta_description']);
            $settings->set('primary_color', $_POST['primary_color']);
            $settings->set('footer_text', $_POST['footer_text']);
            $env->set('OPENROUTER_API_KEY', $_POST['api_key']);
            $env->set('AI_MODEL', $_POST['ai_model']);
        }
    }
}

$active_tab = $_GET['tab'] ?? 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo $settings->get('site_title'); ?></title>
    <?php if ($faviconUrl = $settings->getImageUrl('site_favicon')): ?>
    <link rel="icon" type="image/png" href="<?php echo $faviconUrl; ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <!-- Add Quill CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <!-- Add Quill JS -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <style>
        :root {
            --primary-color: <?php echo $settings->get('primary_color', '#0d6efd'); ?>;
        }
        .model-info {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .model-price {
            font-weight: 500;
            color: #198754;
        }
        .model-context {
            color: #0d6efd;
        }
        .nav-link {
            color: #6c757d;
        }
        .nav-link.active {
            color: var(--primary-color) !important;
            font-weight: 500;
        }
    </style>
</head>
<body class="admin-body">
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="/">
                <?php if ($logoUrl = $settings->getImageUrl('site_logo')): ?>
                <img src="<?php echo $logoUrl; ?>" alt="<?php echo $settings->get('site_title'); ?>">
                <?php endif; ?>
                <?php if ($settings->get('show_site_title') === '1'): ?>
                <span><?php echo $settings->get('site_title'); ?></span>
                <?php endif; ?>
            </a>
            <div>
                <a href="/" class="btn btn-sm btn-outline-primary me-2">Back to Site</a>
                <a href="logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-md-11">
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" 
                           href="?tab=settings">Site Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'forms' ? 'active' : ''; ?>" 
                           href="?tab=forms">Forms</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'credentials' ? 'active' : ''; ?>" 
                           href="?tab=credentials">Admin Credentials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'code' ? 'active' : ''; ?>" 
                           href="?tab=code">Custom Code</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'pages' ? 'active' : ''; ?>" 
                           href="?tab=pages">Pages</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'sections' ? 'active' : ''; ?>" 
                           href="?tab=sections">Sections</a>
                    </li>
                </ul>

                <div class="card">
                    <div class="card-body p-4">
                        <?php if (isset($success)): ?>
                            <?php if ($success): ?>
                            <div class="alert alert-success">Settings updated successfully!</div>
                            <?php else: ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($active_tab === 'forms'): ?>
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="card-title mb-0">Forms</h4>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#formModal">
                                    Create New Form
                                </button>
                            </div>

                            <?php
                            // Fetch all custom forms
                            $forms = [];
                            $result = $db->query('SELECT * FROM custom_forms ORDER BY created_at DESC');
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                $forms[] = $row;
                            }
                            ?>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Slug</th>
                                            <th>Type</th>
                                            <th>Default</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Default Question Form -->
                                        <tr>
                                            <td>Question Generator</td>
                                            <td>Default question generation form with grade levels, difficulty, and question types</td>
                                            <td>default</td>
                                            <td><span class="badge bg-secondary">Built-in</span></td>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="default_form" 
                                                           value="default"
                                                           <?php echo empty($forms) || !array_filter($forms, fn($f) => $f['is_default'] == 1) ? 'checked' : ''; ?>
                                                           onchange="setDefaultForm('default')">
                                                </div>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editDefaultForm()">
                                                    Configure
                                                </button>
                                            </td>
                                        </tr>
                                        <?php foreach ($forms as $form): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($form['name']); ?></td>
                                            <td><?php echo htmlspecialchars($form['description']); ?></td>
                                            <td>
                                                <div class="d-flex flex-column gap-2">
                                                    <code class="small"><?php echo htmlspecialchars($form['slug']); ?></code>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-secondary btn-sm" 
                                                                onclick="copyToClipboard('<?php echo htmlspecialchars($base_url . '/form/' . $form['slug']); ?>', 'URL')"
                                                                title="Copy Form URL">
                                                            <i class="bi bi-link-45deg"></i>
                                                        </button>
                                                        <button class="btn btn-outline-secondary btn-sm"
                                                                onclick="copyToClipboard('<?php echo htmlspecialchars('<iframe src=\"' . $base_url . '/form/' . $form['slug'] . '\" width=\"100%\" height=\"600\" frameborder=\"0\"></iframe>'); ?>', 'Embed code')"
                                                                title="Copy Embed Code">
                                                            <i class="bi bi-code-slash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-primary">Custom</span></td>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="default_form" 
                                                           value="<?php echo $form['id']; ?>"
                                                           <?php echo $form['is_default'] ? 'checked' : ''; ?>
                                                           onchange="setDefaultForm(<?php echo $form['id']; ?>)">
                                                </div>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary me-2" 
                                                        onclick="editForm(<?php echo htmlspecialchars(json_encode($form)); ?>)">
                                                    Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteForm(<?php echo $form['id']; ?>)">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Add this script for clipboard functionality -->
                            <script>
                            function copyToClipboard(text, type) {
                                navigator.clipboard.writeText(text).then(function() {
                                    alert(type + ' copied to clipboard!');
                                }).catch(function(err) {
                                    console.error('Failed to copy: ', err);
                                });
                            }
                            </script>

                            <!-- Add Bootstrap Icons CSS -->
                            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">

                            <!-- Form Modal -->
                            <div class="modal fade" id="formModal" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="formModalTitle">Create New Form</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form id="customFormEditor" method="post" class="needs-validation" novalidate>
                                                <input type="hidden" name="form_type" value="custom_form">
                                                <input type="hidden" name="form_id" id="formId">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Form Name</label>
                                                    <input type="text" name="form_name" class="form-control form-control-sm" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea name="form_description" class="form-control form-control-sm" rows="2"></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Form Fields</label>
                                                    <div id="formFields">
                                                        <!-- Dynamic form fields will be added here -->
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addFormField()">
                                                        Add Field
                                                    </button>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">AI Prompt Template</label>
                                                    <textarea name="ai_prompt" class="form-control form-control-sm" rows="4" required></textarea>
                                                    <small class="text-muted">Use {field_name} to reference form field values in the prompt</small>
                                                </div>

                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" name="is_default" value="1">
                                                    <label class="form-check-label">Set as default form</label>
                                                </div>

                                                <div class="text-end">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Form</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Default Form Settings Modal -->
                            <div class="modal fade" id="defaultFormModal" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Configure Default Form</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form method="post" class="needs-validation" novalidate>
                                                <input type="hidden" name="form_type" value="form">

                                                <div class="mb-3">
                                                    <label class="form-label">AI Prompt Template</label>
                                                    <textarea name="ai_prompt" class="form-control form-control-sm" rows="4" required><?php echo $settings->get('ai_prompt', 'Generate {count} {difficulty} {question_types} questions about {topic} suitable for grade {grade} students. {additional_info} {include_answers_text}'); ?></textarea>
                                                    <small class="text-muted">Available variables: {count}, {difficulty}, {question_types}, {topic}, {grade}, {additional_info}, {include_answers_text}</small>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Grade Levels</label>
                                                    <textarea name="grade_levels" class="form-control form-control-sm" rows="3" required><?php echo $settings->get('grade_levels', "1:Grade 1\n2:Grade 2\n3:Grade 3\n4:Grade 4\n5:Grade 5\n6:Grade 6\n7:Grade 7\n8:Grade 8\n9:Grade 9\n10:Grade 10\n11:Grade 11\n12:Grade 12\ncollege:College"); ?></textarea>
                                                    <small class="text-muted">Format: value:label (one per line)</small>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Difficulty Levels</label>
                                                    <textarea name="difficulty_levels" class="form-control form-control-sm" rows="2" required><?php echo $settings->get('difficulty_levels', "easy:Easy\nmedium:Medium\nhard:Hard"); ?></textarea>
                                                    <small class="text-muted">Format: value:label (one per line)</small>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Question Types</label>
                                                    <textarea name="question_types" class="form-control form-control-sm" rows="2" required><?php echo $settings->get('question_types', "mcq:Multiple Choice\nshort:Short Answer\nlong:Long Answer"); ?></textarea>
                                                    <small class="text-muted">Format: value:label (one per line)</small>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Default Values</label>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label small">Default Question Count</label>
                                                            <input type="number" name="default_count" class="form-control form-control-sm" 
                                                                   value="<?php echo $settings->get('default_count', '5'); ?>" min="1" max="10" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small">Default Grade Level</label>
                                                            <input type="text" name="default_grade" class="form-control form-control-sm" 
                                                                   value="<?php echo $settings->get('default_grade', '6'); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small">Default Difficulty</label>
                                                            <input type="text" name="default_difficulty" class="form-control form-control-sm" 
                                                                   value="<?php echo $settings->get('default_difficulty', 'medium'); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small">Default Question Types</label>
                                                            <input type="text" name="default_types" class="form-control form-control-sm" 
                                                                   value="<?php echo $settings->get('default_types', 'mcq'); ?>" required>
                                                            <small class="text-muted">Comma-separated values</small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="text-end">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Settings</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php elseif ($active_tab === 'credentials'): ?>
                            <h4 class="card-title mb-4">Admin Credentials</h4>
                            <form method="post" class="needs-validation" novalidate>
                                <input type="hidden" name="form_type" value="credentials">
                                
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control form-control-sm" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">New Username</label>
                                    <input type="text" name="new_username" class="form-control form-control-sm" 
                                           placeholder="Leave blank to keep current username">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control form-control-sm"
                                           placeholder="Leave blank to keep current password">
                                </div>

                                <button type="submit" class="btn btn-primary">Update Credentials</button>
                            </form>
                        <?php elseif ($active_tab === 'code'): ?>
                            <h4 class="card-title mb-4">Custom Code Management</h4>
                            <form method="POST" class="settings-form">
                                <input type="hidden" name="form_type" value="custom_code">
                                
                                <div class="mb-4">
                                    <label class="form-label">Header Code</label>
                                    <textarea name="header_code" class="form-control" rows="5" placeholder="Enter code to be included in the header (before </head>)"><?php echo htmlspecialchars($settings->get('header_code')); ?></textarea>
                                    <div class="form-text">This code will be inserted before the closing &lt;/head&gt; tag</div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Footer Code</label>
                                    <textarea name="footer_code" class="form-control" rows="5" placeholder="Enter code to be included in the footer (before </body>)"><?php echo htmlspecialchars($settings->get('footer_code')); ?></textarea>
                                    <div class="form-text">This code will be inserted before the closing &lt;/body&gt; tag</div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Advertisement Code</label>
                                    <textarea name="ad_code" class="form-control" rows="5" placeholder="Enter your advertisement code"><?php echo htmlspecialchars($settings->get('ad_code')); ?></textarea>
                                    <div class="form-text">
                                        This code will be automatically placed in the following locations:
                                        <ul class="mt-1 mb-0">
                                            <li>At the top of the page, below the header</li>
                                            <li>Between the form and results area</li>
                                        </ul>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        <?php elseif ($active_tab === 'pages'): ?>
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="card-title mb-0">Static Pages</h4>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#pageModal">
                                    Create New Page
                                </button>
                            </div>

                            <?php
                            // Fetch all pages
                            $pages = [];
                            $result = $db->query('SELECT * FROM pages ORDER BY title');
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                $pages[] = $row;
                            }
                            ?>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>URL</th>
                                            <th>Show in Footer</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pages as $page): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($page['title']); ?></td>
                                            <td>
                                                <a href="/page.php?slug=<?php echo htmlspecialchars($page['slug']); ?>" 
                                                   target="_blank" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($page['slug']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           <?php echo $page['show_in_footer'] ? 'checked' : ''; ?>
                                                           onchange="toggleFooterVisibility(<?php echo $page['id']; ?>, this.checked)">
                                                </div>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary me-2" 
                                                        onclick="editPage(<?php echo htmlspecialchars(json_encode($page)); ?>)">
                                                    Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger"
                                                        onclick="deletePage(<?php echo $page['id']; ?>)">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Page Modal -->
                            <div class="modal fade" id="pageModal" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="pageModalTitle">Create New Page</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form id="pageEditor" method="post" class="needs-validation" novalidate>
                                                <input type="hidden" name="form_type" value="page">
                                                <input type="hidden" name="page_id" id="pageId">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Page Title</label>
                                                    <input type="text" name="title" class="form-control form-control-sm" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">URL Slug</label>
                                                    <input type="text" name="slug" class="form-control form-control-sm" required
                                                           pattern="[a-z0-9-]+" placeholder="e.g., about-us, privacy-policy">
                                                    <small class="text-muted">Only lowercase letters, numbers, and hyphens</small>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Content</label>
                                                    <div id="editor" style="height: 300px;"></div>
                                                    <input type="hidden" name="content" id="editorContent">
                                                </div>

                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" name="show_in_footer" value="1">
                                                    <label class="form-check-label">Show in footer</label>
                                                </div>

                                                <div class="text-end">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Page</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Include Quill -->
                            <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
                            <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
                            <script>
                                // Initialize Quill editor
                                var quill = new Quill('#editor', {
                                    theme: 'snow',
                                    modules: {
                                        toolbar: [
                                            [{ 'header': [1, 2, 3, false] }],
                                            ['bold', 'italic', 'underline', 'strike'],
                                            ['link', 'blockquote', 'code-block'],
                                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                            ['clean']
                                        ]
                                    }
                                });

                                // Handle form submission
                                document.getElementById('pageEditor').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    
                                    // Get editor content
                                    document.getElementById('editorContent').value = quill.root.innerHTML;
                                    
                                    const formData = new FormData(this);
                                    
                                    fetch('', {
                                        method: 'POST',
                                        body: formData
                                    }).then(response => response.text())
                                      .then(() => window.location.reload())
                                      .catch(error => console.error('Error:', error));
                                });

                                function editPage(page) {
                                    document.getElementById('pageModalTitle').textContent = 'Edit Page';
                                    document.getElementById('pageId').value = page.id;
                                    document.querySelector('[name="title"]').value = page.title;
                                    document.querySelector('[name="slug"]').value = page.slug;
                                    document.querySelector('[name="show_in_footer"]').checked = page.show_in_footer === 1;
                                    quill.root.innerHTML = page.content;
                                    
                                    new bootstrap.Modal(document.getElementById('pageModal')).show();
                                }

                                function deletePage(pageId) {
                                    if (confirm('Are you sure you want to delete this page?')) {
                                        const form = new FormData();
                                        form.append('form_type', 'delete_page');
                                        form.append('page_id', pageId);
                                        
                                        fetch('', {
                                            method: 'POST',
                                            body: form
                                        }).then(response => response.text())
                                          .then(() => window.location.reload())
                                          .catch(error => console.error('Error:', error));
                                    }
                                }

                                function toggleFooterVisibility(pageId, show) {
                                    const form = new FormData();
                                    form.append('form_type', 'toggle_footer');
                                    form.append('page_id', pageId);
                                    form.append('show_in_footer', show ? '1' : '0');
                                    
                                    fetch('', {
                                        method: 'POST',
                                        body: form
                                    }).catch(error => console.error('Error:', error));
                                }

                                // Auto-generate slug from title
                                document.querySelector('[name="title"]').addEventListener('input', function() {
                                    const slugInput = document.querySelector('[name="slug"]');
                                    if (!slugInput.value) {
                                        slugInput.value = this.value
                                            .toLowerCase()
                                            .replace(/[^a-z0-9]+/g, '-')
                                            .replace(/^-+|-+$/g, '');
                                    }
                                });
                            </script>
                        <?php elseif ($active_tab === 'sections'): ?>
                            <ul class="nav nav-pills mb-4">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo !isset($_GET['section']) || $_GET['section'] === 'hero' ? 'active' : ''; ?>" 
                                       href="?tab=sections&section=hero">Hero Section</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo isset($_GET['section']) && $_GET['section'] === 'features' ? 'active' : ''; ?>" 
                                       href="?tab=sections&section=features">Features</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo isset($_GET['section']) && $_GET['section'] === 'about' ? 'active' : ''; ?>" 
                                       href="?tab=sections&section=about">About</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo isset($_GET['section']) && $_GET['section'] === 'faq' ? 'active' : ''; ?>" 
                                       href="?tab=sections&section=faq">FAQ</a>
                                </li>
                            </ul>

                            <?php
                            $section = $_GET['section'] ?? 'hero';
                            
                            if ($section === 'hero'):
                                // Fetch hero sections
                                $hero_sections = [];
                                $result = $db->query('SELECT * FROM hero_sections ORDER BY created_at DESC');
                                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                    $hero_sections[] = $row;
                                }
                            ?>
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="card-title mb-0">Hero Sections</h4>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="editHero()">
                                        Create New Hero
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Subtitle</th>
                                                <th>Active</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($hero_sections as $hero): ?>
                                            <tr data-hero-id="<?php echo $hero['id']; ?>">
                                                <td><?php echo htmlspecialchars($hero['title']); ?></td>
                                                <td><?php echo htmlspecialchars($hero['subtitle']); ?></td>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" 
                                                               name="active_hero"
                                                               <?php echo $hero['is_active'] ? 'checked' : ''; ?>
                                                               onchange="setActiveHero(<?php echo $hero['id']; ?>)">
                                                    </div>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary me-2" 
                                                            onclick='editHero(<?php echo json_encode($hero); ?>)'>
                                                        Edit
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger"
                                                            onclick="deleteHero(<?php echo $hero['id']; ?>)">
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <?php elseif ($section === 'features'):
                                // Fetch features
                                $features = [];
                                $result = $db->query('SELECT * FROM features ORDER BY sort_order');
                                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                    $features[] = $row;
                                }
                            ?>
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="card-title mb-0">Features</h4>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="editFeature()">
                                        Add New Feature
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order</th>
                                                <th>Title</th>
                                                <th>Description</th>
                                                <th>Icon</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($features as $feature): ?>
                                            <tr data-feature-id="<?php echo $feature['id']; ?>">
                                                <td><?php echo $feature['sort_order']; ?></td>
                                                <td><?php echo htmlspecialchars($feature['title']); ?></td>
                                                <td><?php echo htmlspecialchars($feature['description']); ?></td>
                                                <td><i class="bi bi-<?php echo htmlspecialchars($feature['icon']); ?>"></i></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary me-2" 
                                                            onclick='editFeature(<?php echo json_encode($feature); ?>)'>
                                                        Edit
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger"
                                                            onclick="deleteFeature(<?php echo $feature['id']; ?>)">
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Hero Modal -->
                                <div class="modal fade" id="heroModal" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="heroModalTitle">Create Hero Section</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="heroForm" method="post">
                                                    <input type="hidden" name="form_type" value="hero">
                                                    <input type="hidden" name="hero_id" id="heroId">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Title</label>
                                                        <input type="text" name="title" class="form-control" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Subtitle</label>
                                                        <textarea name="subtitle" class="form-control" rows="2"></textarea>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Button Text</label>
                                                        <input type="text" name="button_text" class="form-control">
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Button URL</label>
                                                        <input type="text" name="button_url" class="form-control">
                                                    </div>

                                                    <div class="form-check mb-3">
                                                        <input class="form-check-input" type="checkbox" name="is_active" value="1">
                                                        <label class="form-check-label">Set as active</label>
                                                    </div>

                                                    <div class="text-end">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Feature Modal -->
                                <div class="modal fade" id="featureModal" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="featureModalTitle">Add Feature</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="featureForm" method="post">
                                                    <input type="hidden" name="form_type" value="feature">
                                                    <input type="hidden" name="feature_id" id="featureId">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Title</label>
                                                        <input type="text" name="title" class="form-control" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Description</label>
                                                        <textarea name="description" class="form-control" rows="3"></textarea>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Icon (Bootstrap Icons class name)</label>
                                                        <input type="text" name="icon" class="form-control" required
                                                               placeholder="e.g., robot, list-check">
                                                        <small class="text-muted">Find icons at <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a></small>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Sort Order</label>
                                                        <input type="number" name="sort_order" class="form-control" required>
                                                    </div>

                                                    <div class="text-end">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($section === 'about'):
                                // Fetch about sections
                                $about_sections = [];
                                $result = $db->query('SELECT * FROM about_sections ORDER BY created_at DESC');
                                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                    $about_sections[] = $row;
                                }
                            ?>
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="card-title mb-0">About Sections</h4>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="editAbout()">
                                        Create New About
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Active</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($about_sections as $about): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($about['title']); ?></td>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" 
                                                               name="active_about" 
                                                               value="<?php echo $about['id']; ?>"
                                                               <?php echo $about['is_active'] ? 'checked' : ''; ?>
                                                               onchange="setActiveAbout(<?php echo $about['id']; ?>)">
                                                    </div>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary me-2" 
                                                            onclick='editAbout(<?php echo json_encode($about); ?>)'>
                                                        Edit
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger"
                                                            onclick="deleteAbout(<?php echo $about['id']; ?>)">
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <?php elseif ($section === 'faq'):
                                // Fetch FAQs
                                $faqs = [];
                                $result = $db->query('SELECT * FROM faqs ORDER BY sort_order');
                                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                    $faqs[] = $row;
                                }
                            ?>
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="card-title mb-0">FAQs</h4>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="editFAQ()">
                                        Add New FAQ
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order</th>
                                                <th>Question</th>
                                                <th>Answer</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($faqs as $faq): ?>
                                            <tr>
                                                <td><?php echo $faq['sort_order']; ?></td>
                                                <td><?php echo htmlspecialchars($faq['question']); ?></td>
                                                <td><?php echo htmlspecialchars($faq['answer']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary me-2" 
                                                            onclick='editFAQ(<?php echo json_encode($faq); ?>)'>
                                                        Edit
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger"
                                                            onclick="deleteFAQ(<?php echo $faq['id']; ?>)">
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <!-- About Modal -->
                            <div class="modal fade" id="aboutModal" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="aboutModalTitle">Create About Section</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form id="aboutForm" method="post">
                                                <input type="hidden" name="form_type" value="about">
                                                <input type="hidden" name="about_id" id="aboutId">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Title</label>
                                                    <input type="text" name="title" class="form-control" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Content</label>
                                                    <div id="aboutEditor" style="height: 300px;"></div>
                                                    <input type="hidden" name="content" id="aboutContent">
                                                </div>

                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" name="is_active" value="1">
                                                    <label class="form-check-label">Set as active</label>
                                                </div>

                                                <div class="text-end">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- FAQ Modal -->
                            <div class="modal fade" id="faqModal" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="faqModalTitle">Add FAQ</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form id="faqForm" method="post">
                                                <input type="hidden" name="form_type" value="faq">
                                                <input type="hidden" name="faq_id" id="faqId">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Question</label>
                                                    <input type="text" name="question" class="form-control" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Answer</label>
                                                    <textarea name="answer" class="form-control" rows="3" required></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Sort Order</label>
                                                    <input type="number" name="sort_order" class="form-control" required>
                                                </div>

                                                <div class="text-end">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <script>
                                let aboutQuill = null;
                                
                                // Initialize Quill when the modal is shown
                                document.getElementById('aboutModal').addEventListener('shown.bs.modal', function () {
                                    if (!aboutQuill) {
                                        try {
                                            aboutQuill = new Quill('#aboutEditor', {
                                                theme: 'snow',
                                                modules: {
                                                    toolbar: [
                                                        [{ 'header': [1, 2, 3, false] }],
                                                        ['bold', 'italic', 'underline', 'strike'],
                                                        ['link', 'blockquote'],
                                                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                                        ['clean']
                                                    ]
                                                }
                                            });
                                        } catch (error) {
                                            console.error('Error initializing Quill:', error);
                                        }
                                    }
                                });

                                // About Section Management
                                function editAbout(about = null) {
                                    const form = document.getElementById('aboutForm');
                                    const title = document.getElementById('aboutModalTitle');
                                    
                                    title.textContent = about ? 'Edit About Section' : 'Create About Section';
                                    form.reset();
                                    
                                    // Show modal first to ensure Quill is initialized
                                    const modal = new bootstrap.Modal(document.getElementById('aboutModal'));
                                    modal.show();
                                    
                                    // Set the values after modal is fully shown
                                    document.getElementById('aboutModal').addEventListener('shown.bs.modal', function handler() {
                                        if (aboutQuill) {
                                            if (about) {
                                                document.getElementById('aboutId').value = about.id;
                                                form.querySelector('[name="title"]').value = about.title;
                                                aboutQuill.root.innerHTML = about.content;
                                                form.querySelector('[name="is_active"]').checked = about.is_active === 1;
                                            } else {
                                                document.getElementById('aboutId').value = '';
                                                aboutQuill.root.innerHTML = '';
                                            }
                                        }
                                        // Remove the event listener after it's executed
                                        this.removeEventListener('shown.bs.modal', handler);
                                    });
                                }

                                // Handle About form submission
                                document.getElementById('aboutForm').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    if (aboutQuill) {
                                        document.getElementById('aboutContent').value = aboutQuill.root.innerHTML;
                                    }
                                    const formData = new FormData(this);
                                    submitForm(formData);
                                });

                                function deleteAbout(id) {
                                    if (confirm('Are you sure you want to delete this about section?')) {
                                        const form = new FormData();
                                        form.append('form_type', 'delete_about');
                                        form.append('about_id', id);
                                        submitForm(form);
                                    }
                                }

                                function setActiveAbout(id) {
                                    const form = new FormData();
                                    form.append('form_type', 'set_active_about');
                                    form.append('about_id', id);
                                    submitForm(form);
                                }

                                // FAQ Management
                                function editFAQ(faq = null) {
                                    const form = document.getElementById('faqForm');
                                    const title = document.getElementById('faqModalTitle');
                                    
                                    title.textContent = faq ? 'Edit FAQ' : 'Add FAQ';
                                    form.reset();
                                    
                                    if (faq) {
                                        document.getElementById('faqId').value = faq.id;
                                        form.querySelector('[name="question"]').value = faq.question;
                                        form.querySelector('[name="answer"]').value = faq.answer;
                                        form.querySelector('[name="sort_order"]').value = faq.sort_order;
                                    }
                                    
                                    new bootstrap.Modal(document.getElementById('faqModal')).show();
                                }

                                function deleteFAQ(id) {
                                    if (confirm('Are you sure you want to delete this FAQ?')) {
                                        const form = new FormData();
                                        form.append('form_type', 'delete_faq');
                                        form.append('faq_id', id);
                                        submitForm(form);
                                    }
                                }

                                // Form submission handler
                                function submitForm(formData) {
                                    fetch('', {
                                        method: 'POST',
                                        body: formData
                                    }).then(response => response.text())
                                      .then(() => window.location.reload())
                                      .catch(error => console.error('Error:', error));
                                }

                                // Handle form submissions
                                document.getElementById('heroForm').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    submitForm(new FormData(this));
                                });

                                document.getElementById('featureForm').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    submitForm(new FormData(this));
                                });

                                document.getElementById('aboutForm').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    document.getElementById('aboutContent').value = aboutQuill.root.innerHTML;
                                    submitForm(new FormData(this));
                                });

                                document.getElementById('faqForm').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    submitForm(new FormData(this));
                                });

                                // Hero Section Management
                                function editHero(hero = null) {
                                    const modal = document.getElementById('heroModal');
                                    if (!modal) {
                                        console.error('Hero modal not found');
                                        return;
                                    }

                                    const form = document.getElementById('heroForm');
                                    const title = document.getElementById('heroModalTitle');
                                    
                                    if (title) {
                                        title.textContent = hero ? 'Edit Hero Section' : 'Create Hero Section';
                                    }
                                    
                                    if (form) {
                                        form.reset();
                                        
                                        if (hero) {
                                            document.getElementById('heroId').value = hero.id;
                                            form.querySelector('[name="title"]').value = hero.title;
                                            form.querySelector('[name="subtitle"]').value = hero.subtitle;
                                            form.querySelector('[name="button_text"]').value = hero.button_text;
                                            form.querySelector('[name="button_url"]').value = hero.button_url;
                                            form.querySelector('[name="is_active"]').checked = hero.is_active === 1;
                                        } else {
                                            document.getElementById('heroId').value = '';
                                        }
                                    }
                                    
                                    const bsModal = new bootstrap.Modal(modal);
                                    bsModal.show();
                                }

                                function deleteHero(id) {
                                    if (confirm('Are you sure you want to delete this hero section?')) {
                                        const form = new FormData();
                                        form.append('form_type', 'delete_hero');
                                        form.append('hero_id', id);
                                        submitForm(form);
                                    }
                                }

                                function setActiveHero(id) {
                                    const form = new FormData();
                                    form.append('form_type', 'set_active_hero');
                                    form.append('hero_id', id);
                                    submitForm(form);
                                }

                                // Feature Management
                                function editFeature(feature = null) {
                                    const form = document.getElementById('featureForm');
                                    const title = document.getElementById('featureModalTitle');
                                    
                                    title.textContent = feature ? 'Edit Feature' : 'Add Feature';
                                    form.reset();
                                    
                                    if (feature) {
                                        document.getElementById('featureId').value = feature.id;
                                        form.querySelector('[name="title"]').value = feature.title;
                                        form.querySelector('[name="description"]').value = feature.description;
                                        form.querySelector('[name="icon"]').value = feature.icon;
                                        form.querySelector('[name="sort_order"]').value = feature.sort_order;
                                    } else {
                                        document.getElementById('featureId').value = '';
                                        // Set default sort order to the next available number
                                        const lastFeature = document.querySelector('table tbody tr:last-child');
                                        const nextOrder = lastFeature ? 
                                            parseInt(lastFeature.querySelector('td:first-child').textContent) + 1 : 1;
                                        form.querySelector('[name="sort_order"]').value = nextOrder;
                                    }
                                    
                                    new bootstrap.Modal(document.getElementById('featureModal')).show();
                                }

                                function deleteFeature(id) {
                                    if (confirm('Are you sure you want to delete this feature?')) {
                                        const form = new FormData();
                                        form.append('form_type', 'delete_feature');
                                        form.append('feature_id', id);
                                        submitForm(form);
                                    }
                                }
                            </script>
                        <?php else: ?>
                            <h4 class="card-title mb-4">Site Settings</h4>
                            <form method="post" class="needs-validation" enctype="multipart/form-data" novalidate>
                                <input type="hidden" name="form_type" value="settings">
                                
                                <div class="mb-3">
                                    <label class="form-label">Site Title (Logo Text)</label>
                                    <input type="text" name="site_title" class="form-control form-control-sm" 
                                           value="<?php echo $settings->get('site_title'); ?>" required>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="show_site_title" value="1" 
                                               <?php echo $settings->get('show_site_title') === '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Show site title next to logo</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">SEO Title (Browser Tab)</label>
                                    <input type="text" name="seo_title" class="form-control form-control-sm" 
                                           value="<?php echo $settings->get('seo_title'); ?>" required>
                                    <small class="text-muted">This title appears in browser tab and search results</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Site Logo</label>
                                    <input type="file" name="site_logo" class="form-control form-control-sm" 
                                           accept="image/png,image/jpeg,image/gif">
                                    <div class="image-preview">
                                        <?php if ($logoUrl = $settings->getImageUrl('site_logo')): ?>
                                            <img src="<?php echo $logoUrl; ?>" alt="Current Logo" class="preview-image">
                                        <?php else: ?>
                                            <span class="text-muted">No logo uploaded</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">Recommended size: 200x60px, PNG format</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Favicon</label>
                                    <input type="file" name="site_favicon" class="form-control form-control-sm" 
                                           accept="image/x-icon,image/png">
                                    <div class="image-preview">
                                        <?php if ($faviconUrl = $settings->getImageUrl('site_favicon')): ?>
                                            <img src="<?php echo $faviconUrl; ?>" alt="Current Favicon" class="preview-favicon">
                                        <?php else: ?>
                                            <span class="text-muted">No favicon uploaded</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">Recommended size: 32x32px, ICO or PNG format</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Meta Description</label>
                                    <textarea name="meta_description" class="form-control form-control-sm" rows="2" 
                                              required><?php echo $settings->get('meta_description'); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Footer Text</label>
                                    <textarea name="footer_text" class="form-control form-control-sm" rows="2"><?php echo $settings->get('footer_text'); ?></textarea>
                                    <small class="text-muted">HTML is allowed. Leave empty to hide footer.</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Primary Color</label>
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="color" name="primary_color" class="form-control form-control-color" 
                                               value="<?php echo $settings->get('primary_color'); ?>" required>
                                        <span class="small text-muted">Select primary theme color</span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">OpenRouter API Key</label>
                                    <input type="password" name="api_key" class="form-control form-control-sm" 
                                           value="<?php echo $settings->get('api_key'); ?>" required>
                                    <small class="text-muted">Get your API key from <a href="https://openrouter.ai/keys" target="_blank">OpenRouter</a></small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">AI Model</label>
                                    <?php if (empty($available_models)): ?>
                                        <div class="alert alert-warning">
                                            No models available. Please check your API key and try again.
                                        </div>
                                    <?php endif; ?>
                                    <select name="ai_model" class="form-select form-select-sm" required>
                                        <?php foreach ($available_models as $model): ?>
                                            <?php
                                                $prompt_price = isset($model['pricing']['prompt']) ? 
                                                    number_format($model['pricing']['prompt'] * 1000, 2) : 'N/A';
                                                $completion_price = isset($model['pricing']['completion']) ? 
                                                    number_format($model['pricing']['completion'] * 1000, 2) : 'N/A';
                                                $context_length = isset($model['context_length']) ? 
                                                    number_format($model['context_length']) : 'N/A';
                                            ?>
                                            <option value="<?php echo htmlspecialchars($model['id']); ?>" 
                                                    <?php echo $settings->get('ai_model') === $model['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($model['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="modelInfo" class="mt-2 model-info">
                                        <?php if (!empty($available_models)): ?>
                                        <script>
                                            const models = <?php echo json_encode($available_models); ?>;
                                            function updateModelInfo() {
                                                const selectedModel = document.querySelector('select[name="ai_model"]').value;
                                                const model = models.find(m => m.id === selectedModel);
                                                if (model) {
                                                    const promptPrice = model.pricing?.prompt ? 
                                                        (model.pricing.prompt * 1000).toFixed(2) : 'N/A';
                                                    const completionPrice = model.pricing?.completion ? 
                                                        (model.pricing.completion * 1000).toFixed(2) : 'N/A';
                                                    const contextLength = model.context_length ? 
                                                        model.context_length.toLocaleString() : 'N/A';
                                                    
                                                    document.getElementById('modelInfo').innerHTML = `
                                                        <div>Pricing: <span class="model-price">$${promptPrice}/1K prompt tokens, 
                                                        $${completionPrice}/1K completion tokens</span></div>
                                                        <div>Context Length: <span class="model-context">${contextLength} tokens</span></div>
                                                    `;
                                                }
                                            }
                                            document.querySelector('select[name="ai_model"]')
                                                .addEventListener('change', updateModelInfo);
                                            updateModelInfo();
                                        </script>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Settings</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Preview uploaded images
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const preview = this.parentElement.querySelector('.image-preview');
                const file = this.files[0];
                
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = preview.querySelector('img') || document.createElement('img');
                        img.src = e.target.result;
                        img.className = input.name === 'site_favicon' ? 'preview-favicon' : 'preview-image';
                        if (!preview.querySelector('img')) {
                            preview.innerHTML = '';
                            preview.appendChild(img);
                        }
                    }
                    reader.readAsDataURL(file);
                }
            });
        });

        // Custom Forms Management
        let fieldCounter = 0;

        function addFormField(field = null) {
            const fieldId = field?.id || fieldCounter++;
            const fieldHtml = `
                <div class="card mb-2 form-field" data-field-id="${fieldId}">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small">Field Name</label>
                                <input type="text" class="form-control form-control-sm field-name" 
                                       value="${field?.name || ''}" required
                                       placeholder="e.g., topic, grade_level">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Field Label</label>
                                <input type="text" class="form-control form-control-sm field-label" 
                                       value="${field?.label || ''}" required
                                       placeholder="e.g., Topic, Grade Level">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Field Type</label>
                                <select class="form-select form-select-sm field-type" required>
                                    <option value="text" ${field?.type === 'text' ? 'selected' : ''}>Text Input</option>
                                    <option value="textarea" ${field?.type === 'textarea' ? 'selected' : ''}>Text Area</option>
                                    <option value="select" ${field?.type === 'select' ? 'selected' : ''}>Select Dropdown</option>
                                    <option value="number" ${field?.type === 'number' ? 'selected' : ''}>Number</option>
                                    <option value="checkbox" ${field?.type === 'checkbox' ? 'selected' : ''}>Checkbox</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Required</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input field-required" type="checkbox" 
                                           ${field?.required ? 'checked' : ''}>
                                    <label class="form-check-label">Make this field required</label>
                                </div>
                            </div>
                            <div class="col-12 field-options ${field?.type === 'select' ? '' : 'd-none'}">
                                <label class="form-label small">Options (one per line)</label>
                                <textarea class="form-control form-control-sm field-options-text" rows="3"
                                          placeholder="value:label">${field?.options || ''}</textarea>
                                <small class="text-muted">Format: value:label (one per line)</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-3" onclick="removeField(${fieldId})">
                            Remove Field
                        </button>
                    </div>
                </div>
            `;
            document.getElementById('formFields').insertAdjacentHTML('beforeend', fieldHtml);
            
            // Add event listener for field type change
            const newField = document.querySelector(`[data-field-id="${fieldId}"]`);
            newField.querySelector('.field-type').addEventListener('change', function() {
                const optionsDiv = newField.querySelector('.field-options');
                optionsDiv.classList.toggle('d-none', this.value !== 'select');
            });
        }

        function removeField(fieldId) {
            document.querySelector(`[data-field-id="${fieldId}"]`).remove();
        }

        function getFormFields() {
            const fields = [];
            document.querySelectorAll('.form-field').forEach(fieldDiv => {
                fields.push({
                    id: fieldDiv.dataset.fieldId,
                    name: fieldDiv.querySelector('.field-name').value,
                    label: fieldDiv.querySelector('.field-label').value,
                    type: fieldDiv.querySelector('.field-type').value,
                    required: fieldDiv.querySelector('.field-required').checked,
                    options: fieldDiv.querySelector('.field-options-text').value
                });
            });
            return fields;
        }

        function editForm(form) {
            document.getElementById('formModalTitle').textContent = 'Edit Form';
            document.getElementById('formId').value = form.id;
            document.querySelector('[name="form_name"]').value = form.name;
            document.querySelector('[name="form_description"]').value = form.description;
            document.querySelector('[name="ai_prompt"]').value = form.ai_prompt;
            document.querySelector('[name="is_default"]').checked = form.is_default === 1;
            
            // Clear existing fields
            document.getElementById('formFields').innerHTML = '';
            
            // Add form fields
            const fields = JSON.parse(form.form_fields);
            fields.forEach(field => addFormField(field));
            
            // Show modal
            new bootstrap.Modal(document.getElementById('formModal')).show();
        }

        function deleteForm(formId) {
            if (confirm('Are you sure you want to delete this form?')) {
                const form = new FormData();
                form.append('form_type', 'delete_form');
                form.append('form_id', formId);
                
                fetch('', {
                    method: 'POST',
                    body: form
                }).then(response => response.text())
                  .then(() => window.location.reload())
                  .catch(error => console.error('Error:', error));
            }
        }

        function setDefaultForm(formId) {
            if (formId === 'default') {
                // Reset all custom forms to non-default
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'form_type=reset_default_forms'
                }).then(response => response.text())
                  .then(() => window.location.reload())
                  .catch(error => console.error('Error:', error));
            } else {
                const form = new FormData();
                form.append('form_type', 'set_default_form');
                form.append('form_id', formId);
                
                fetch('', {
                    method: 'POST',
                    body: form
                }).then(response => response.text())
                  .then(() => window.location.reload())
                  .catch(error => console.error('Error:', error));
            }
        }

        // Handle custom form submission
        document.getElementById('customFormEditor')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('form_fields', JSON.stringify(getFormFields()));
            
            fetch('', {
                method: 'POST',
                body: formData
            }).then(response => response.text())
              .then(() => window.location.reload())
              .catch(error => console.error('Error:', error));
        });

        // Handle default form editing
        function editDefaultForm() {
            new bootstrap.Modal(document.getElementById('defaultFormModal')).show();
        }
    </script>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Hero Modal -->
    <div class="modal fade" id="heroModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="heroModalTitle">Create Hero Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="heroForm" method="post">
                        <input type="hidden" name="form_type" value="hero">
                        <input type="hidden" name="hero_id" id="heroId">
                        
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Subtitle</label>
                            <textarea name="subtitle" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Button Text</label>
                            <input type="text" name="button_text" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Button URL</label>
                            <input type="text" name="button_url" class="form-control">
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1">
                            <label class="form-check-label">Set as active</label>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php require_once 'footer.php'; ?>
</body>
</html> 