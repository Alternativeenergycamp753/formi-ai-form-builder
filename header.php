<?php
require_once __DIR__ . '/includes/Settings.php';
session_start();
$settings = Settings::getInstance();
$isIndexPage = basename($_SERVER['PHP_SELF']) === 'index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $isIndexPage ? $settings->get('meta_description') : ($settings->get('temp_meta_description') ?: $settings->get('meta_description')); ?>">
    <title><?php echo $isIndexPage ? $settings->get('seo_title') : ($settings->get('temp_seo_title') ?: $settings->get('seo_title')); ?></title>
    <?php if ($faviconUrl = $settings->getImageUrl('site_favicon')): ?>
    <link rel="icon" type="image/png" href="<?php echo $faviconUrl; ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Urbanist:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <style>
        :root {
            --primary-color: <?php echo $settings->get('primary_color', '#0d6efd'); ?>;
        }
    </style>
    <script>
        function copyContent() {
            const content = document.querySelector('.generated-questions').innerText;
            navigator.clipboard.writeText(content).then(() => {
                const successMsg = document.querySelector('.copy-success');
                successMsg.classList.add('show');
                setTimeout(() => {
                    successMsg.classList.remove('show');
                }, 2000);
            });
        }
    </script>
    <?php if ($header_code = $settings->get('header_code')): ?>
    <?php echo $header_code; ?>
    <?php endif; ?>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
        <div class="container">
            <a class="navbar-brand" href="/">
                <?php if ($logoUrl = $settings->getImageUrl('site_logo')): ?>
                <img src="<?php echo $logoUrl; ?>" alt="<?php echo $settings->get('site_title'); ?>">
                <?php endif; ?>
                <?php if ($settings->get('show_site_title') === '1'): ?>
                <span><?php echo $settings->get('site_title'); ?></span>
                <?php endif; ?>
            </a>
            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && basename($_SERVER['PHP_SELF']) !== 'admin.php'): ?>
            <a href="/admin.php" class="btn btn-sm btn-outline-primary">Admin Panel</a>
            <?php endif; ?>
        </div>
    </nav>
    <div class="container py-4"> 
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 