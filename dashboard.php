<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

startSecureSession();
requireLogin();

$pdo = getDbConnection();

// Re-check has_voted from the DB (not just the session) in case it changed elsewhere.
$stmt = $pdo->prepare('SELECT has_voted FROM users WHERE id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    // Session points to a user that no longer exists.
    session_destroy();
    header('Location: index.php');
    exit;
}

if ((int) $user['has_voted'] === 1) {
    header('Location: results.php');
    exit;
}

// Fetch positions and their candidates.
$positions = $pdo->query('SELECT id, title FROM positions ORDER BY id ASC')->fetchAll();

$candidatesByPosition = [];
if ($positions) {
    $stmt = $pdo->query('SELECT id, position_id, first_name, last_name, photo FROM candidates ORDER BY last_name ASC');
    foreach ($stmt->fetchAll() as $candidate) {
        $candidatesByPosition[$candidate['position_id']][] = $candidate;
    }
}

$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cast Your Vote | School Elections</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="top-bar">
    <div class="top-bar-inner">
        <img src="assets/logo.png" alt="FICT Logo" class="logo-small">
        <span class="welcome">Welcome, <?= h($_SESSION['name']) ?></span>
        <a href="logout.php" class="btn btn-outline btn-small">Log Out</a>
    </div>
</header>

<main class="page-container">
    <h1 class="tagline">Vote Wisely!</h1>

    <div id="statusMessage" class="alert" style="display:none;"></div>

    <?php if (empty($positions)): ?>
        <div class="alert alert-info">No positions have been set up yet. Please check back later.</div>
    <?php else: ?>
        <form id="voteForm">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <?php foreach ($positions as $position): ?>
                <fieldset class="position-block">
                    <legend><?= h($position['title']) ?></legend>

                    <?php $candidates = $candidatesByPosition[$position['id']] ?? []; ?>

                    <?php if (empty($candidates)): ?>
                        <p class="muted">No candidates registered for this position yet.</p>
                    <?php else: ?>
                        <div class="candidate-list">
                            <?php foreach ($candidates as $candidate): ?>
                                <label class="candidate-option">
                                    <input
                                        type="radio"
                                        name="position_<?= (int) $position['id'] ?>"
                                        value="<?= (int) $candidate['id'] ?>"
                                        required
                                    >
                                    <?php if (!empty($candidate['photo'])): ?>
                                        <img src="<?= h($candidate['photo']) ?>" alt="" class="candidate-photo">
                                    <?php endif; ?>
                                    <span><?= h($candidate['first_name']) ?> <?= h($candidate['last_name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </fieldset>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary btn-large" id="submitBtn">Submit My Vote</button>
        </form>
    <?php endif; ?>
</main>

<script>
const voteForm = document.getElementById('voteForm');
const statusMessage = document.getElementById('statusMessage');
const submitBtn = document.getElementById('submitBtn');

if (voteForm) {
    voteForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
        statusMessage.style.display = 'none';

        const formData = new FormData(voteForm);

        try {
            const response = await fetch('vote.php', {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();

            statusMessage.style.display = 'block';

            if (data.success) {
                statusMessage.className = 'alert alert-success';
                statusMessage.textContent = data.message || 'Your vote has been recorded!';
                voteForm.querySelectorAll('input, button').forEach(el => el.disabled = true);
                setTimeout(() => { window.location.href = 'results.php'; }, 1500);
            } else {
                statusMessage.className = 'alert alert-error';
                statusMessage.textContent = data.message || 'Something went wrong. Please try again.';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit My Vote';
            }
        } catch (err) {
            statusMessage.style.display = 'block';
            statusMessage.className = 'alert alert-error';
            statusMessage.textContent = 'Network error. Please try again.';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit My Vote';
        }
    });
}
</script>
</body>
</html>
