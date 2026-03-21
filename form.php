<?php
require_once 'includes/Settings.php';
require_once 'vendor/autoload.php';

$settings = Settings::getInstance();
$db = new SQLite3('database.sqlite');

// Get base URL without trailing slash
$base_url = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]", '/');

// Get the slug from the URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    http_response_code(404);
    echo '<div class="container mt-5"><div class="alert alert-danger">Form not found.</div></div>';
    require_once 'footer.php';
    exit;
}

// Fetch the form by slug
$stmt = $db->prepare('SELECT * FROM custom_forms WHERE slug = ?');
$stmt->bindValue(1, $slug);
$result = $stmt->execute();
$form = $result->fetchArray(SQLITE3_ASSOC);

if (!$form) {
    http_response_code(404);
    echo '<div class="container mt-5"><div class="alert alert-danger">Form not found.</div></div>';
    require_once 'footer.php';
    exit;
}

// Set dynamic page title and meta description
$settings->set('temp_seo_title', htmlspecialchars($form['name']) . ' - ' . $settings->get('site_title'));
$settings->set('temp_meta_description', $form['description'] ?: $settings->get('meta_description'));

// Include header after setting dynamic SEO values
require_once 'header.php';

$form_fields = json_decode($form['form_fields'], true);
?>

<style>
    .htmx-indicator {
        display: none;
    }
    .htmx-request .htmx-indicator {
        display: inline-block;
    }
    .htmx-request .htmx-indicator-none {
        display: none;
    }
</style>

<div class="question-generator">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <?php if ($ad_code = $settings->get('ad_code')): ?>
                <div class="mb-4 text-center">
                    <?php echo $ad_code; ?>
                </div>
            <?php endif; ?>

            <div class="card border">
                <div class="card-body p-4">
                    <h4 class="card-title mb-4"><?php echo htmlspecialchars($form['name']); ?></h4>
                    <?php if ($form['description']): ?>
                        <p class="text-muted mb-4"><?php echo htmlspecialchars($form['description']); ?></p>
                    <?php endif; ?>

                    <form hx-post="/generate.php" hx-target="#result">
                        <input type="hidden" name="form_id" value="<?php echo $form['id']; ?>">
                        
                        <?php foreach ($form_fields as $field): ?>
                            <div class="mb-3">
                                <label class="form-label"><?php echo htmlspecialchars($field['label']); ?></label>
                                <?php if ($field['type'] === 'textarea'): ?>
                                    <textarea name="<?php echo htmlspecialchars($field['name']); ?>" 
                                            class="form-control form-control-sm" rows="3"
                                            <?php echo $field['required'] ? 'required' : ''; ?>></textarea>
                                <?php elseif ($field['type'] === 'select'): ?>
                                    <select name="<?php echo htmlspecialchars($field['name']); ?>" 
                                            class="form-select form-select-sm"
                                            <?php echo $field['required'] ? 'required' : ''; ?>>
                                        <?php 
                                        foreach (explode("\n", $field['options']) as $option) {
                                            $parts = explode(':', trim($option));
                                            if (count($parts) === 2) {
                                                echo '<option value="' . htmlspecialchars($parts[0]) . '">' . 
                                                     htmlspecialchars($parts[1]) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                <?php else: ?>
                                    <input type="<?php echo htmlspecialchars($field['type']); ?>" 
                                           class="form-control form-control-sm" 
                                           name="<?php echo htmlspecialchars($field['name']); ?>"
                                           <?php echo $field['required'] ? 'required' : ''; ?>>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary" hx-indicator="#loading">
                                <span class="htmx-indicator-none">Generate</span>
                                <span class="htmx-indicator">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Generating...
                                </span>
                            </button>
                        </div>
                    </form>

                    <div id="loading" class="htmx-indicator">
                        <div class="d-flex justify-content-center my-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>

                    <div id="result" class="mt-4"></div>

                    <?php if ($ad_code = $settings->get('ad_code')): ?>
                        <div class="mt-4 text-center">
                            <?php echo $ad_code; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?> 