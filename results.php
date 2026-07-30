<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

startSecureSession(); // only needed so we can show a "back to dashboard" link if logged in

$pdo = getDbConnection();

$positions = $pdo->query('SELECT id, title FROM positions ORDER BY id ASC')->fetchAll();

$resultsByPosition = [];
foreach ($positions as $position) {
    $stmt = $pdo->prepare('
        SELECT
            c.id,
            c.first_name,
            c.last_name,
            c.photo,
            COUNT(v.id) AS vote_count
        FROM candidates c
        LEFT JOIN votes v ON v.candidate_id = c.id
        WHERE c.position_id = :position_id
        GROUP BY c.id, c.first_name, c.last_name, c.photo
        ORDER BY vote_count DESC, c.last_name ASC
    ');
    $stmt->execute(['position_id' => $position['id']]);
    $candidates = $stmt->fetchAll();

    $totalVotes = array_sum(array_column($candidates, 'vote_count'));

    $resultsByPosition[] = [
        'title' => $position['title'],
        'candidates' => $candidates,
        'total_votes' => $totalVotes,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Results | School Elections</title>
<link rel="stylesheet" href="assets/style.css">
<meta http-equiv="refresh" content="15">
</head>
<body>
<header class="top-bar">
    <div class="top-bar-inner">
        <img src="assets/logo.png" alt="FICT Logo" class="logo-small">
        <span class="welcome">Live Election Results</span>
        <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="logout.php" class="btn btn-outline btn-small">Log Out</a>
        <?php else: ?>
            <a href="index.php" class="btn btn-outline btn-small">Student Login</a>
        <?php endif; ?>
    </div>
</header>

<main class="page-container">
    <h1 class="tagline">Vote Wisely!</h1>
    <p class="muted center">Results update automatically every 15 seconds.</p>

    <?php if (empty($resultsByPosition)): ?>
        <div class="alert alert-info">No positions have been set up yet.</div>
    <?php endif; ?>

    <?php foreach ($resultsByPosition as $result): ?>
        <section class="results-block">
            <h2><?= h($result['title']) ?></h2>

            <?php if (empty($result['candidates'])): ?>
                <p class="muted">No candidates registered for this position.</p>
            <?php else: ?>
                <?php foreach ($result['candidates'] as $candidate): ?>
                    <?php
                        $voteCount = (int) $candidate['vote_count'];
                        $percentage = $result['total_votes'] > 0
                            ? round(($voteCount / $result['total_votes']) * 100, 1)
                            : 0;
                    ?>
                    <div class="result-row">
                        <div class="result-header">
                            <span class="candidate-name">
                                <?= h($candidate['first_name']) ?> <?= h($candidate['last_name']) ?>
                            </span>
                            <span class="vote-count"><?= $voteCount ?> vote<?= $voteCount === 1 ? '' : 's' ?> (<?= $percentage ?>%)</span>
                        </div>
                        <div class="progress-bar-track">
                            <div class="progress-bar-fill" style="width: <?= $percentage ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <p class="muted total-votes">Total votes cast for this position: <?= (int) $result['total_votes'] ?></p>
        </section>
    <?php endforeach; ?>
</main>
</body>
</html>
