<?php
require_once 'includes/Settings.php';
$settings = Settings::getInstance();
$db = new SQLite3('database.sqlite');

// Get the page slug from URL
$slug = $_GET['slug'] ?? '';

// Fetch the page content
$stmt = $db->prepare('SELECT * FROM pages WHERE slug = ?');
$stmt->bindValue(1, $slug);
$result = $stmt->execute();
$page = $result->fetchArray(SQLITE3_ASSOC);

// If page not found, show 404
if (!$page) {
    header("HTTP/1.0 404 Not Found");
    include 'header.php';
    echo '<div class="container py-5">
            <div class="text-center">
                <h1>404 - Page Not Found</h1>
                <p>The page you are looking for does not exist.</p>
                <a href="/" class="btn btn-primary">Go Home</a>
            </div>
          </div>';
    include 'footer.php';
    exit;
}

// Set page-specific SEO title and description
$site_title = $settings->get('site_title');
$page_title = htmlspecialchars($page['title']);
$seo_title = "$page_title - $site_title";
$meta_description = strip_tags(substr($page['content'], 0, 160)); // First 160 characters of content as description

// Store these temporarily in settings for the header to use
$settings->set('temp_seo_title', $seo_title);
$settings->set('temp_meta_description', $meta_description);

include 'header.php';

// Clear temporary settings
$settings->set('temp_seo_title', '');
$settings->set('temp_meta_description', '');
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-body p-4">
                    <h1 class="card-title h3 mb-4"><?php echo $page_title; ?></h1>
                    <div class="page-content">
                        <?php echo $page['content']; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?> 