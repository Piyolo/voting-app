<?php
/**
 * QUICK PANEL — DB-CONNECTED
 * ------------------------------------------------------------------
 * This is the original, working admin tool (formerly admin/index.php):
 * add positions, add candidates, delete candidates — all hitting the
 * real database via PDO.
 *
 * It's kept around as a fallback for real data entry while the new
 * dashboard.php election wizard is still a frontend-only prototype.
 * Once that wizard is wired up to the DB, this page can be folded
 * into it (or kept as a lightweight power-user shortcut).
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

startSecureSession();

if (empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDbConnection();
$message = '';
$error = '';

// ---- Handle form actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'add_position') {
                $title = trim($_POST['title'] ?? '');
                if ($title === '') {
                    $error = 'Position title is required.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO positions (title) VALUES (:title)');
                    $stmt->execute(['title' => $title]);
                    $message = 'Position added.';
                }
            } elseif ($action === 'add_candidate') {
                $positionId = (int) ($_POST['position_id'] ?? 0);
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $photo = trim($_POST['photo'] ?? '');

                if ($positionId <= 0 || $firstName === '' || $lastName === '') {
                    $error = 'Position, first name, and last name are required.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO candidates (position_id, first_name, last_name, photo)
                         VALUES (:position_id, :first_name, :last_name, :photo)'
                    );
                    $stmt->execute([
                        'position_id' => $positionId,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'photo' => $photo !== '' ? $photo : null,
                    ]);
                    $message = 'Candidate added.';
                }
            } elseif ($action === 'delete_candidate') {
                $candidateId = (int) ($_POST['candidate_id'] ?? 0);
                if ($candidateId > 0) {
                    // Votes reference candidates; delete dependent votes first.
                    $pdo->beginTransaction();
                    $pdo->prepare('DELETE FROM votes WHERE candidate_id = :id')->execute(['id' => $candidateId]);
                    $pdo->prepare('DELETE FROM candidates WHERE id = :id')->execute(['id' => $candidateId]);
                    $pdo->commit();
                    $message = 'Candidate deleted.';
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Admin action failed: ' . $e->getMessage());
            $error = 'The action could not be completed.';
        }
    }
}

// ---- Load data for display ----
$positions = $pdo->query('SELECT id, title FROM positions ORDER BY id ASC')->fetchAll();

$candidatesByPosition = [];
$stmt = $pdo->query('
    SELECT c.id, c.position_id, c.first_name, c.last_name, c.photo, COUNT(v.id) AS vote_count
    FROM candidates c
    LEFT JOIN votes v ON v.candidate_id = c.id
    GROUP BY c.id, c.position_id, c.first_name, c.last_name, c.photo
    ORDER BY c.last_name ASC
');
foreach ($stmt->fetchAll() as $row) {
    $candidatesByPosition[$row['position_id']][] = $row;
}

// Voter turnout stats (no passwords ever selected/shown).
$turnout = $pdo->query('SELECT COUNT(*) AS total, SUM(has_voted) AS voted FROM users')->fetch();

$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quick Panel | School Elections</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="top-bar">
    <div class="top-bar-inner">
        <img src="../assets/logo.png" alt="FICT Logo" class="logo-small">
        <span class="welcome">Quick Panel — DB Connected</span>
        <a href="dashboard.php" class="btn btn-outline btn-small">Back to Dashboard</a>
        <a href="logout.php" class="btn btn-outline btn-small">Log Out</a>
    </div>
</header>

<main class="page-container">
    <h1 class="tagline">Vote Wisely!</h1>
    <p class="muted">This page writes directly to the database. Use it for real position/candidate data until the new dashboard's election wizard is wired up.</p>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <section class="admin-card">
        <h2>Voter Turnout</h2>
        <p><?= (int) ($turnout['voted'] ?? 0) ?> of <?= (int) ($turnout['total'] ?? 0) ?> registered students have voted.</p>
        <p class="muted">Voter passwords are never accessible from this panel.</p>
        <p><a href="../results.php" target="_blank">View public results page &rarr;</a></p>
    </section>

    <section class="admin-card">
        <h2>Add a Position</h2>
        <form method="POST" class="form form-inline">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="add_position">
            <input type="text" name="title" placeholder="e.g. SSG Governor" required>
            <button type="submit" class="btn btn-primary">Add Position</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Add a Candidate</h2>
        <?php if (empty($positions)): ?>
            <p class="muted">Add a position first.</p>
        <?php else: ?>
            <form method="POST" class="form form-inline">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="add_candidate">
                <select name="position_id" required>
                    <option value="">Select position</option>
                    <?php foreach ($positions as $position): ?>
                        <option value="<?= (int) $position['id'] ?>"><?= h($position['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="first_name" placeholder="First name" required>
                <input type="text" name="last_name" placeholder="Last name" required>
                <input type="text" name="photo" placeholder="Photo URL (optional)">
                <button type="submit" class="btn btn-primary">Add Candidate</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="admin-card">
        <h2>Candidates</h2>
        <?php foreach ($positions as $position): ?>
            <h3><?= h($position['title']) ?></h3>
            <?php $candidates = $candidatesByPosition[$position['id']] ?? []; ?>
            <?php if (empty($candidates)): ?>
                <p class="muted">No candidates yet.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr><th>Name</th><th>Votes</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $candidate): ?>
                            <tr>
                                <td><?= h($candidate['first_name']) ?> <?= h($candidate['last_name']) ?></td>
                                <td><?= (int) $candidate['vote_count'] ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Delete this candidate? This also removes their votes.');">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete_candidate">
                                        <input type="hidden" name="candidate_id" value="<?= (int) $candidate['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
