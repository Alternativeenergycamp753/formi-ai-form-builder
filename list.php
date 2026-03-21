<?php
require_once 'includes/Settings.php';
require_once 'vendor/autoload.php';

$settings = Settings::getInstance();
$db = new SQLite3('database.sqlite');

// Set page title and meta description
$settings->set('temp_seo_title', 'Available Forms - ' . $settings->get('site_title'));
$settings->set('temp_meta_description', 'Browse all available forms');

// Pagination settings
$per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Get total forms count
$total_forms = $db->querySingle('SELECT COUNT(*) FROM custom_forms');
$total_pages = ceil($total_forms / $per_page);

// Get forms for current page
$stmt = $db->prepare('SELECT * FROM custom_forms ORDER BY created_at DESC LIMIT ? OFFSET ?');
$stmt->bindValue(1, $per_page, SQLITE3_INTEGER);
$stmt->bindValue(2, $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

// Include header
require_once 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <h1 class="mb-4">Available Forms</h1>
            
            <div class="row row-cols-1 row-cols-md-2 g-4">
                <?php while ($form = $result->fetchArray(SQLITE3_ASSOC)): ?>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($form['name']); ?></h5>
                                <?php if ($form['description']): ?>
                                    <p class="card-text text-muted"><?php echo htmlspecialchars($form['description']); ?></p>
                                <?php endif; ?>
                                <a href="/form/<?php echo htmlspecialchars($form['slug']); ?>" 
                                   class="btn btn-primary">Use Form</a>
                            </div>
                            <div class="card-footer text-muted">
                                <small>Created: <?php echo date('M j, Y', strtotime($form['created_at'])); ?></small>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?> 