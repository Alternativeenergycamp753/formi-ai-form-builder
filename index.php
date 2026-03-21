<?php 
include 'header.php';
require_once 'vendor/autoload.php';

// Initialize database connection
$db = new SQLite3('database.sqlite');

// Initialize Parsedown
$parsedown = new Parsedown();
$parsedown->setSafeMode(true);

// Get active hero section
$hero = $db->query('SELECT * FROM hero_sections WHERE is_active = 1 LIMIT 1')->fetchArray(SQLITE3_ASSOC);

// Get active about section
$about = $db->query('SELECT * FROM about_sections WHERE is_active = 1 LIMIT 1')->fetchArray(SQLITE3_ASSOC);

// Get features
$features = [];
$result = $db->query('SELECT * FROM features ORDER BY sort_order');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $features[] = $row;
}

// Get FAQs
$faqs = [];
$result = $db->query('SELECT * FROM faqs ORDER BY sort_order');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $faqs[] = $row;
}

// Display hero section
if ($hero): ?>
<div class="hero-section py-5 bg-light mb-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-4 mb-3"><?php echo htmlspecialchars($hero['title']); ?></h1>
                <?php if ($hero['subtitle']): ?>
                    <p class="lead mb-4"><?php echo htmlspecialchars($hero['subtitle']); ?></p>
                <?php endif; ?>
                <?php if ($hero['button_text'] && $hero['button_url']): ?>
                    <a href="<?php echo htmlspecialchars($hero['button_url']); ?>" class="btn btn-primary btn-lg">
                        <?php echo htmlspecialchars($hero['button_text']); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="generator" class="question-generator">
    <!-- Existing form content -->
    <?php
    // Get the default custom form if exists
    $default_form = null;
    $result = $db->query('SELECT * FROM custom_forms WHERE is_default = 1 LIMIT 1');
    if ($result) {
        $default_form = $result->fetchArray(SQLITE3_ASSOC);
    }

    if ($default_form) {
        // Parse form fields
        $form_fields = json_decode($default_form['form_fields'], true);
    ?>
    <div class="question-generator">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card border">
                    <div class="card-body p-4">
                        <h4 class="card-title mb-4"><?php echo htmlspecialchars($default_form['name']); ?></h4>
                        <?php if ($default_form['description']): ?>
                            <p class="text-muted mb-4"><?php echo htmlspecialchars($default_form['description']); ?></p>
                        <?php endif; ?>

                        <form hx-post="generate.php" hx-target="#result">
                            <input type="hidden" name="form_id" value="<?php echo $default_form['id']; ?>">
                            
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
                                    <?php elseif ($field['type'] === 'checkbox'): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="<?php echo htmlspecialchars($field['name']); ?>" value="1">
                                            <label class="form-check-label"><?php echo htmlspecialchars($field['label']); ?></label>
                                        </div>
                                    <?php else: ?>
                                        <input type="<?php echo $field['type']; ?>" 
                                               name="<?php echo htmlspecialchars($field['name']); ?>" 
                                               class="form-control form-control-sm"
                                               <?php echo $field['required'] ? 'required' : ''; ?>>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <button type="submit" class="btn btn-primary btn-sm">
                                <span class="htmx-indicator-hide">Generate</span>
                                <span class="htmx-indicator">
                                    Generating...
                                    <span class="spinner-border spinner-border-sm ms-1"></span>
                                </span>
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($ad_code = $settings->get('ad_code')): ?>
                <div class="ad-container my-4"><?php echo $ad_code; ?></div>
                <?php endif; ?>

                <div id="result" class="mt-4"></div>
            </div>
        </div>
    </div>
<?php } else {
    // Parse the settings into arrays
    $grade_levels = [];
    foreach (explode("\n", $settings->get('grade_levels')) as $line) {
        $parts = explode(':', trim($line));
        if (count($parts) === 2) {
            $grade_levels[$parts[0]] = $parts[1];
        }
    }

    $difficulty_levels = [];
    foreach (explode("\n", $settings->get('difficulty_levels')) as $line) {
        $parts = explode(':', trim($line));
        if (count($parts) === 2) {
            $difficulty_levels[$parts[0]] = $parts[1];
        }
    }

    $question_types = [];
    foreach (explode("\n", $settings->get('question_types')) as $line) {
        $parts = explode(':', trim($line));
        if (count($parts) === 2) {
            $question_types[$parts[0]] = $parts[1];
        }
    }

    $default_count = $settings->get('default_count', 5);
    $default_grade = $settings->get('default_grade', '6');
    $default_difficulty = $settings->get('default_difficulty', 'medium');
    $default_types = explode(',', $settings->get('default_types', 'mcq'));
    ?>

    <div class="question-generator">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card border">
                    <div class="card-body p-4">
                        <h4 class="card-title mb-4">Generate Questions</h4>
                        <form hx-post="generate.php" hx-target="#result">
                            <div class="mb-3">
                                <label class="form-label">Topic</label>
                                <input type="text" name="topic" class="form-control form-control-sm" required 
                                       placeholder="Enter the topic (e.g., World War II, Photosynthesis)">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Grade Level</label>
                                    <select name="grade" class="form-select form-select-sm" required>
                                        <?php foreach ($grade_levels as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" 
                                                <?php echo $value === $default_grade ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Difficulty Level</label>
                                    <select name="difficulty" class="form-select form-select-sm" required>
                                        <?php foreach ($difficulty_levels as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>"
                                                <?php echo $value === $default_difficulty ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Number of Questions</label>
                                    <input type="number" name="count" class="form-control form-control-sm" 
                                           min="1" max="10" value="<?php echo $default_count; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Include Answers</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="include_answers" value="1" checked>
                                        <label class="form-check-label">Yes, include answers</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Question Types</label>
                                <?php foreach ($question_types as $value => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="types[]" 
                                           value="<?php echo htmlspecialchars($value); ?>"
                                           <?php echo in_array($value, $default_types) ? 'checked' : ''; ?>>
                                    <label class="form-check-label"><?php echo htmlspecialchars($label); ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Additional Instructions (Optional)</label>
                                <textarea name="additional_info" class="form-control form-control-sm" rows="2" 
                                        placeholder="Any specific requirements or instructions for the questions"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <span class="htmx-indicator-hide">Generate Questions</span>
                                <span class="htmx-indicator">
                                    Generating...
                                    <span class="spinner-border spinner-border-sm ms-1"></span>
                                </span>
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($ad_code = $settings->get('ad_code')): ?>
                <div class="ad-container my-4"><?php echo $ad_code; ?></div>
                <?php endif; ?>

                <div id="result" class="mt-4"></div>
            </div>
        </div>
    </div>
<?php } ?>
</div>

<!-- Features Section -->
<?php if (!empty($features)): ?>
<div class="features-section py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center text-center mb-5">
            <div class="col-lg-8">
                <h2 class="h1">Features</h2>
                <p class="lead text-muted">Discover what makes our platform unique</p>
            </div>
        </div>
        <div class="row g-4">
            <?php foreach ($features as $feature): ?>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="bi bi-<?php echo htmlspecialchars($feature['icon']); ?> fs-1 text-primary"></i>
                        </div>
                        <h3 class="h4 mb-3"><?php echo htmlspecialchars($feature['title']); ?></h3>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($feature['description']); ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- About Section -->
<?php if ($about): ?>
<div class="about-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h2 class="h1 mb-4"><?php echo htmlspecialchars($about['title']); ?></h2>
                <div class="about-content">
                    <?php echo $parsedown->text($about['content']); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- FAQ Section -->
<?php if (!empty($faqs)): ?>
<div class="faq-section py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center text-center mb-5">
            <div class="col-lg-8">
                <h2 class="h1">Frequently Asked Questions</h2>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="faqAccordion">
                    <?php foreach ($faqs as $index => $faq): ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?>" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#faq<?php echo $index; ?>">
                                <?php echo htmlspecialchars($faq['question']); ?>
                            </button>
                        </h3>
                        <div id="faq<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" 
                             data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <?php echo htmlspecialchars($faq['answer']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'footer.php'; ?> 