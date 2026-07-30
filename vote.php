<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

startSecureSession();
header('Content-Type: application/json');

// Must be logged in.
if (empty($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'You must be logged in to vote.'], 401);
}

// Only accept POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// CSRF check.
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    jsonResponse(['success' => false, 'message' => 'Your session expired. Please refresh and try again.'], 403);
}

$userId = (int) $_SESSION['user_id'];
$pdo = getDbConnection();

try {
    // Lock the user row and re-check has_voted inside the transaction to prevent
    // race conditions from double submissions (e.g. double-click, two tabs).
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT has_voted FROM users WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'User not found.'], 404);
    }

    if ((int) $user['has_voted'] === 1) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'You have already voted.'], 409);
    }

    // Load valid positions and candidates so we can validate submitted data
    // instead of trusting raw POST field names/values.
    $positions = $pdo->query('SELECT id FROM positions')->fetchAll(PDO::FETCH_COLUMN);

    if (empty($positions)) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'No positions are configured.'], 400);
    }

    $candidateStmt = $pdo->prepare('SELECT id FROM candidates WHERE id = :id AND position_id = :position_id');
    $insertVoteStmt = $pdo->prepare(
        'INSERT INTO votes (user_id, candidate_id, position_id) VALUES (:user_id, :candidate_id, :position_id)'
    );

    $votesToCast = [];

    foreach ($positions as $positionId) {
        $fieldName = 'position_' . $positionId;

        if (!isset($_POST[$fieldName]) || $_POST[$fieldName] === '') {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'Please select a candidate for every position.'], 400);
        }

        $candidateId = (int) $_POST[$fieldName];

        // Confirm the candidate actually belongs to this position (prevents tampering).
        $candidateStmt->execute(['id' => $candidateId, 'position_id' => $positionId]);
        if (!$candidateStmt->fetch()) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'Invalid candidate selection.'], 400);
        }

        $votesToCast[] = ['candidate_id' => $candidateId, 'position_id' => $positionId];
    }

    // All selections validated — insert them all together.
    foreach ($votesToCast as $vote) {
        $insertVoteStmt->execute([
            'user_id' => $userId,
            'candidate_id' => $vote['candidate_id'],
            'position_id' => $vote['position_id'],
        ]);
    }

    $updateStmt = $pdo->prepare('UPDATE users SET has_voted = 1 WHERE id = :id');
    $updateStmt->execute(['id' => $userId]);

    $pdo->commit();

    jsonResponse(['success' => true, 'message' => 'Your vote has been recorded. Thank you for voting!']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Unique constraint violation on (user_id, position_id) — belt-and-braces
    // in case of a race condition despite the row lock above.
    error_log('Vote insertion failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Could not record your vote. You may have already voted.'], 409);
}
