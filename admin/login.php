<?php
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

if (!empty($_SESSION['is_admin'])) {
    header('Location: dashboard.php');
    exit;
}

/**
 * Admin password is stored as a bcrypt hash in the ADMIN_PASSWORD_HASH
 * environment variable. Generate one with:
 *   php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT), PHP_EOL;"
 *
 * A default fallback hash is provided for LOCAL DEVELOPMENT ONLY
 * (default password: "admin123"). Always set ADMIN_PASSWORD_HASH in
 * production (Render environment variables).
 */
$adminPasswordHash = getenv('ADMIN_PASSWORD_HASH')
    ?: '$2b$10$h2LzDnXC7UxtLvu96v48g./hdzLqugLq.ZL/EeZhS0zT07kkjGvdC'; // "admin123" - CHANGE IN PRODUCTION

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';

        if (password_verify($password, $adminPasswordHash)) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Incorrect admin password.';
    }
}

$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | School Elections</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="page-center">
    <div class="card">
        <h1 class="tagline">Vote Wisely!</h1>
        <h2 class="card-title">Admin Login</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="form">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <label for="password">Admin Password</label>
            <input type="password" id="password" name="password" required autofocus autocomplete="current-password">

            <button type="submit" class="btn btn-primary">Log In</button>
        </form>

        <div class="footer-links">
            <a href="../index.php">Student Login</a>
        </div>
    </div>
</div>
</body>
</html>
