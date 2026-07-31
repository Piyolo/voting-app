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
            $error = 'Please enter both ID and Password.';
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

            $error = 'Invalid ID or Password.';
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
<title>Login | School Elections</title>
<link rel="stylesheet" href="assets/style.css">
<style>
    /* Blurred school background, kept separate from content so the blur
       doesn't affect text/card sharpness. Oversized + centered to avoid
       blurry edges showing at the viewport border. */
    body {
        margin: 0;
        min-height: 100vh;
    }

    .bg-photo {
        position: fixed;
        inset: 0;
        z-index: -1;
        background-image: url('assets/school-bg.jpg');
        background-size: cover;
        background-position: center;
        filter: blur(6px);
        transform: scale(1.1); /* hides blur edge artifacts */
    }

    /* Optional dark overlay so the login card has more contrast. */
    .bg-overlay {
        position: fixed;
        inset: 0;
        z-index: -1;
        background: rgba(0, 0, 0, 0.35);
    }

    .page-center {
        position: relative;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    /* Glass-like login card */
    .page-center .card {
        background: rgba(255, 255, 255, 0.16);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        border: 1px solid rgba(255, 255, 255, 0.25);
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
        border-radius: 16px;
    }

    .page-center .tagline,
    .page-center .card-title,
    .page-center label {
        color: #ffffff;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
    }

    .page-center .tagline {
        background: none;
        box-shadow: none;
        padding: 0;
        margin: 0 0 0.5rem;
    }

    .page-center label {
        font-size: 1.15rem;
        font-weight: 600;
        display: block;
        margin-top: 1rem;
        margin-bottom: 0.35rem;
    }

    /* Swap the site's blue accents for a lime / light-green accent on this page. */
    .page-center input[type="text"],
    .page-center input[type="password"] {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(255, 255, 255, 0.4);
    }

    .page-center input[type="text"]:focus,
    .page-center input[type="password"]:focus {
        outline: none;
        border-color: #84cc16;
        box-shadow: 0 0 0 3px rgba(132, 204, 22, 0.4);
    }

    .page-center .btn-primary {
        background: #84cc16;
        border-color: #84cc16;
        color: #052e16;
    }

    .page-center .btn-primary:hover {
        background: #65a30d;
        border-color: #65a30d;
    }

    .page-center a {
        color: #bef264;
    }

    .page-center a:hover {
        color: #d9f99d;
    }

    .page-center .logo {
        width: 140px;
        height: 140px;
        object-fit: contain;
    }

    /* Show/hide password toggle – Slash by default, open eye when visible */
    .password-field {
        position: relative;
        display: flex;
        align-items: stretch;
    }

    .password-field input {
        flex: 1;
        padding-right: 2.5rem;
    }

    .toggle-password-btn {
        position: absolute;
        right: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        padding: 0.25rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 0;
    }

    .toggle-password-btn svg {
        width: 20px;
        height: 20px;
        stroke: #333333;
    }

    /* Default: show the slashed eye (password hidden) */
    .toggle-password-btn .icon-eye {
        display: none;
    }
    .toggle-password-btn .icon-eye-off {
        display: block;
    }

    /* When visible (password shown), show the open eye */
    .toggle-password-btn.is-visible .icon-eye {
        display: block;
    }
    .toggle-password-btn.is-visible .icon-eye-off {
        display: none;
    }
</style>
</head>
<body>
<div class="bg-photo"></div>
<div class="bg-overlay"></div>
<div class="page-center">
    <div class="card">
        <div class="logo-area">
            <img src="assets/logo.png" alt="FICT Logo" class="logo">
        </div>
        <h2 class="card-title">Login</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php" class="form">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <label for="student_id">ID</label>
            <input type="text" id="student_id" name="student_id" required autofocus autocomplete="username">

            <label for="password">Password</label>
            <div class="password-field">
                <input type="password" id="password" name="password" required autocomplete="current-password">
                <button type="button" class="toggle-password-btn" id="togglePasswordBtn" aria-label="Show password" aria-pressed="false">
                    <!-- Open eye icon -->
                    <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <!-- Slashed eye icon (default) -->
                    <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 11 7 11 7a20.3 20.3 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>
            </div>

            <button type="submit" class="btn btn-primary">Log In</button>
        </form>
    </div>
</div>
<script>
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    const passwordInput = document.getElementById('password');

    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', function () {
            const isHidden = passwordInput.type === 'password';
            // Toggle input type
            passwordInput.type = isHidden ? 'text' : 'password';
            // Toggle the class that controls which icon is shown
            this.classList.toggle('is-visible', isHidden);
            // Update ARIA attributes
            this.setAttribute('aria-pressed', String(isHidden));
            this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    }
</script>
</body>
</html>
