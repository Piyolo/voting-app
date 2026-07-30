<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

startSecureSession();

// If already logged in, skip straight to the dashboard.
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $studentId = trim($_POST['student_id'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($studentId === '' || $password === '') {
            $error = 'Please enter both Student ID and Password.';
        } else {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('SELECT id, name, password, has_voted FROM users WHERE student_id = :student_id');
            $stmt->execute(['student_id' => $studentId]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];

                if ((int) $user['has_voted'] === 1) {
                    header('Location: results.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            }

            $error = 'Invalid Student ID or Password.';
        }
    }
}

startSecureSession();
$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Login | School Elections</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="page-center">
    <div class="card">
        <div class="logo-area">
            <img src="assets/logo.png" alt="FICT Logo" class="logo">
        </div>
        <h1 class="tagline">Vote Wisely!</h1>
        <h2 class="card-title">Student Login</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php" class="form">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <label for="student_id">Student ID</label>
            <input type="text" id="student_id" name="student_id" required autofocus autocomplete="username">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">

            <button type="submit" class="btn btn-primary">Log In</button>
        </form>

        <div class="footer-links">
            <a href="results.php">View Live Results</a>
        </div>
    </div>
</div>
</body>
</html>
