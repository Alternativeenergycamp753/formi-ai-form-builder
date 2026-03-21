<?php
session_start();
require_once 'includes/Env.php';
require_once 'includes/Settings.php';
$env = Env::getInstance();

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === $env->get('ADMIN_USERNAME') && $env->verifyAdminPassword($password)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - AI Question Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            max-width: 400px;
            width: 90%;
            margin: 0 auto;
        }
        .card {
            border: 1px solid #eaeaea;
            box-shadow: none;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo img {
            max-height: 60px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <?php if ($logoUrl = Settings::getInstance()->getImageUrl('site_logo')): ?>
            <img src="<?php echo $logoUrl; ?>" alt="Logo">
            <?php endif; ?>
            <h4>Admin Login</h4>
        </div>
        
        <div class="card">
            <div class="card-body p-4">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="post" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control form-control-sm" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control form-control-sm" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <a href="/" class="text-muted text-decoration-none">← Back to Site</a>
        </div>
    </div>
</body>
</html> 