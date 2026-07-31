<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

startSecureSession();
requireLogin();

$pdo = getDbConnection();

// Get current user
$stmt = $pdo->prepare('SELECT id, name, has_voted FROM users WHERE id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$hasVoted = (int)$user['has_voted'] === 1;

// --------------------------------
// HARDCODED ELECTION CONFIG (front-end only)
// In production, this would come from an 'elections' table.
// For now, we map existing positions to one of two elections.
// --------------------------------
$elections = [
    1 => [
        'id' => 1,
        'name' => 'Supreme Student Government',
        'start_date' => '2026-07-01 08:00:00',
        'end_date'   => '2026-07-31 23:59:59',
        'status'     => 'open',
        'position_ids' => [1, 2],
        'accent' => '#3b82f6', // blue
    ],
    2 => [
        'id' => 2,
        'name' => 'Department Student Government',
        'start_date' => '2026-07-01 08:00:00',
        'end_date'   => '2026-07-31 23:59:59',
        'status'     => 'open',
        'position_ids' => [3], // unused for display now — DSG positions/candidates are hardcoded below
        'accent' => '#f97316', // orange
    ],
];

// Fetch all positions and candidates from DB
$allPositions = $pdo->query('SELECT id, title FROM positions ORDER BY id')->fetchAll();
$allCandidates = [];
$candStmt = $pdo->query('SELECT id, position_id, first_name, last_name, photo FROM candidates ORDER BY last_name');
foreach ($candStmt->fetchAll() as $c) {
    $allCandidates[$c['position_id']][] = $c;
}

// Build election data
$electionData = [];
foreach ($elections as $eid => $config) {
    $positions = array_filter($allPositions, function($p) use ($config) {
        return in_array($p['id'], $config['position_ids']);
    });
    $candidatesByPosition = [];
    foreach ($positions as $p) {
        $candidatesByPosition[$p['id']] = $allCandidates[$p['id']] ?? [];
    }
    $electionData[$eid] = [
        'config' => $config,
        'positions' => $positions,
        'candidatesByPosition' => $candidatesByPosition,
        'totalPositions' => count($positions),
    ];
}

// --------------------------------
// HARDCODED DSG POSITIONS & CANDIDATES (front-end only, no DB involved)
// 4 positions, 2 candidates each (8 total). Overrides whatever came from
// the DB for election id 2 so this works purely for presentation.
// --------------------------------
$dsgPositions = [
    ['id' => 401, 'title' => 'President'],
    ['id' => 402, 'title' => 'Vice President'],
    ['id' => 403, 'title' => 'Secretary'],
    ['id' => 404, 'title' => 'Treasurer'],
];
$dsgCandidatesByPosition = [
    401 => [
        ['id' => 4001, 'position_id' => 401, 'first_name' => 'Josiah', 'last_name' => 'Bautista', 'photo' => 'assets/josiah-bautista.jpg'],
        ['id' => 4002, 'position_id' => 401, 'first_name' => 'Krystal Anne', 'last_name' => 'Panganiban', 'photo' => null],
    ],
    402 => [
        ['id' => 4003, 'position_id' => 402, 'first_name' => 'Miguel', 'last_name' => 'Dela Pena', 'photo' => null],
        ['id' => 4004, 'position_id' => 402, 'first_name' => 'Samantha', 'last_name' => 'Reyes', 'photo' => null],
    ],
    403 => [
        ['id' => 4005, 'position_id' => 403, 'first_name' => 'Adrian', 'last_name' => 'Villaruz', 'photo' => null],
        ['id' => 4006, 'position_id' => 403, 'first_name' => 'Bea Francesca', 'last_name' => 'Solis', 'photo' => null],
    ],
    404 => [
        ['id' => 4007, 'position_id' => 404, 'first_name' => 'Nathaniel', 'last_name' => 'Cordero', 'photo' => null],
        ['id' => 4008, 'position_id' => 404, 'first_name' => 'Angelica Marie', 'last_name' => 'Fajardo', 'photo' => null],
    ],
];
$electionData[2]['positions'] = $dsgPositions;
$electionData[2]['candidatesByPosition'] = $dsgCandidatesByPosition;
$electionData[2]['totalPositions'] = count($dsgPositions);

// Determine page
$page = $_GET['page'] ?? 'home';
$selectedElectionId = isset($_GET['election_id']) ? (int)$_GET['election_id'] : null;

$csrfToken = csrfToken();

// For results page
$resultsByPosition = [];
if ($page === 'results') {
    foreach ($allPositions as $position) {
        $stmt = $pdo->prepare('
            SELECT c.id, c.first_name, c.last_name, c.photo, COUNT(v.id) AS vote_count
            FROM candidates c
            LEFT JOIN votes v ON v.candidate_id = c.id
            WHERE c.position_id = :position_id
            GROUP BY c.id, c.first_name, c.last_name, c.photo
            ORDER BY vote_count DESC, c.last_name ASC
        ');
        $stmt->execute(['position_id' => $position['id']]);
        $candidates = $stmt->fetchAll();
        $totalVotes = array_sum(array_column($candidates, 'vote_count'));
        $electionName = 'General';
        foreach ($elections as $e) {
            if (in_array($position['id'], $e['position_ids'])) {
                $electionName = $e['name'];
                break;
            }
        }
        $resultsByPosition[] = [
            'election_name' => $electionName,
            'title' => $position['title'],
            'candidates' => $candidates,
            'total_votes' => $totalVotes,
        ];
    }
}

// Helper for candidate details
function getCandidateDetails($candidate) {
    $courses = ['BS IT', 'BS CS', 'BS IS', 'BS ECE'];
    $platforms = [
        'Improve campus Wi-Fi',
        'More student events',
        'Better library resources',
        'Mental health awareness',
        'Sustainable campus initiatives'
    ];
    $idx = abs(crc32($candidate['first_name'])) % count($courses);
    $year = (abs(crc32($candidate['last_name'])) % 3) + 1;
    return [
        'major' => $courses[$idx] . ' major in ' . ($idx % 2 ? 'Network Security' : 'Software Development'),
        'year' => $year . 'rd Year',
        'platform' => $platforms[abs(crc32($candidate['first_name'] . $candidate['last_name'])) % count($platforms)]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | School Elections</title>
<link rel="stylesheet" href="assets/style.css">
<style>
* { box-sizing: border-box; }
body { margin: 0; font-family: system-ui, -apple-system, sans-serif; background: #f4f7fa; display: flex; min-height: 100vh; }

/* ----- Sidebar with fixed bottom profile & logout ----- */
.sidebar {
    width: 240px;
    background: #1e293b;
    color: #e2e8f0;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    z-index: 1000;
    transition: transform 0.3s ease;
    overflow: hidden;
}
.sidebar .logo-area {
    padding: 1.5rem 1.5rem 1rem;
    text-align: center;
    flex-shrink: 0;
}
.sidebar .logo-area img { max-width: 100px; height: auto; }
.sidebar .logo-area h2 { color: white; margin: 0.5rem 0 0; font-weight: 400; font-size: 1.1rem; }

.sidebar nav {
    flex: 1;
    overflow-y: auto;
    padding: 0 0 0.5rem 0;
}
.sidebar nav a {
    display: block;
    padding: 0.75rem 1.5rem;
    color: #cbd5e1;
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: background 0.2s, border-color 0.2s;
}
.sidebar nav a:hover { background: #334155; border-left-color: #84cc16; }
.sidebar nav a.active { background: #334155; border-left-color: #84cc16; color: white; }

.sidebar .profile-section {
    padding: 1rem 1.5rem;
    border-top: 1px solid #334155;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
    background: #1e293b;
}
.sidebar .profile-section .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #84cc16;
    color: #052e16;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.sidebar .profile-section .user-info { flex: 1; overflow: hidden; }
.sidebar .profile-section .user-info .name { font-weight: 500; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sidebar .profile-section .user-info .role { font-size: 0.75rem; color: #94a3b8; }
.sidebar .logout-link {
    padding: 0.75rem 1.5rem;
    border-top: 1px solid #334155;
    flex-shrink: 0;
    background: #1e293b;
}
.sidebar .logout-link a { color: #f87171; text-decoration: none; }
.sidebar .logout-link a:hover { text-decoration: underline; }

/* Main content */
.main-content {
    margin-left: 240px;
    flex: 1;
    padding: 2rem;
    transition: margin-left 0.3s;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
    flex-wrap: wrap;
    gap: 0.5rem;
    position: sticky;
    top: 0;
    background: #f4f7fa;
    z-index: 500;
    padding-top: 0.5rem;
    padding-bottom: 0.75rem;
}
.page-header h1 { margin: 0; font-weight: 400; color: #1e293b; font-size: 1.5rem; }
.page-header .date { font-size: 0.95rem; color: #64748b; }
.hamburger { display: none; background: none; border: none; font-size: 1.8rem; color: #1e293b; cursor: pointer; padding: 0.25rem 0.5rem; line-height: 1; }
.sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 999; opacity: 0; transition: opacity 0.3s; }
.sidebar-overlay.active { display: block; opacity: 1; }

/* Stats bar */
.stats-bar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-box {
    background: white;
    border-radius: 16px;
    padding: 1rem 1.25rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    text-align: center;
}
.stat-box .stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #0f172a;
}
.stat-box .stat-label {
    font-size: 0.85rem;
    color: #64748b;
    margin-top: 0.25rem;
}
.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #0f172a;
    margin: 1.5rem 0 1rem;
}

/* ----- Election Cards ----- */
.election-cards { display: flex; flex-direction: column; gap: 1.5rem; }
.election-card {
    background: white;
    border-radius: 18px;
    border: 1px solid #eef1f5;
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.05);
    padding: 1.6rem 1.75rem;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
    border-top: 4px solid transparent;
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    min-height: 230px;
}
.election-card:hover {
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.09);
    transform: translateY(-2px);
}

/* Top row: status pill + countdown pill */
.card-top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.6rem;
}
.status-large {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #15803d;
    background: rgba(34, 197, 94, 0.12);
    padding: 0.4rem 0.85rem 0.4rem 0.7rem;
    border-radius: 999px;
    line-height: 1;
}
.status-large .status-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
    flex-shrink: 0;
}
.status-large.closed { color: #b91c1c; background: rgba(239, 68, 68, 0.1); }
.status-large.closed .status-dot { background: #ef4444; }

.remaining-time {
    display: inline-flex;
    align-items: baseline;
    gap: 0.4rem;
    font-size: 0.85rem;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.45rem 0.9rem;
    border-radius: 999px;
    white-space: nowrap;
}
.remaining-time strong { color: #334155; font-weight: 600; font-size: 0.8rem; }
.remaining-time span { color: #0f172a; font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: 0.02em; }

.election-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    line-height: 1.3;
}

/* Status card */
.status-card {
    background: #f8fafc;
    border-radius: 12px;
    padding: 0.85rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}
.status-card .status-label { font-weight: 600; color: #64748b; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em; }
.status-card .status-value {
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
}
.status-card .status-value.voted { color: #16a34a; background: rgba(34,197,94,0.12); }
.status-card .status-value.not-voted { color: #dc2626; background: rgba(239,68,68,0.1); }
.status-card .thank-you { font-size: 0.85rem; color: #16a34a; margin-left: auto; font-weight: 500; }

/* ----- CARD BUTTONS – equal-width grid keeps them perfectly aligned ----- */
.card-actions {
    margin-top: auto;
    padding-top: 0.25rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}
.card-actions .btn { width: 100%; }

/* ----- Modern button styles ----- */
.btn {
    box-sizing: border-box;
    height: 46px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    font-family: inherit;
    line-height: 1;
    margin: 0;
    padding: 0 1.25rem;
    transition: background 0.2s, border-color 0.2s, transform 0.15s;
    border: 2px solid transparent;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    vertical-align: middle;
}
.btn:active { transform: scale(0.98); }
/* Firefox adds an invisible inner border/padding to <button> only — this
   is what breaks alignment between a <button class="btn"> (e.g. a disabled
   "Vote Now") and an <a class="btn"> (e.g. "View Candidates") even though
   both share the exact same class. */
button.btn::-moz-focus-inner {
    border: 0;
    padding: 0;
}

.btn-primary {
    background: #84cc16;
    color: #ffffff;
    border-color: #84cc16;
}
.btn-primary:hover { background: #65a30d; border-color: #65a30d; }
.btn-primary:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    background: #84cc16;
    border-color: #84cc16;
    color: #ffffff;
}

.btn-secondary {
    background: #ffffff;
    color: #1e293b;
    border-color: #e2e8f0;
}
.btn-secondary:hover { background: #f8fafc; border-color: #94a3b8; }

/* ----- Form actions (vote page) ----- */
.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}
.form-actions .btn {
    flex: 1;
    text-align: center;
}

/* Featured Candidates */
.featured-section {
    margin-top: 2.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
}
.featured-section .featured-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.featured-section .featured-header h3 {
    font-size: 1.2rem;
    font-weight: 600;
    color: #0f172a;
    margin: 0;
}
.featured-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1.5rem;
}
.featured-card {
    background: white;
    border-radius: 16px;
    padding: 1rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.featured-card img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 0.5rem;
    background: #f1f5f9;
}
.featured-card .name { font-weight: 600; color: #0f172a; }
.featured-card .position { font-size: 0.85rem; color: #64748b; }

/* Other page styles */
.card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; }
.candidate-card {
    position: relative;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    padding: 3.25rem 1rem 1.25rem;
    text-align: center;
    margin-top: 45px; /* leaves room for the photo poking out above */
}
.candidate-card img {
    position: absolute;
    top: -45px;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 50%;
    background: #f1f5f9;
    border: 4px solid #ffffff;
    box-shadow: 0 4px 10px rgba(0,0,0,0.12);
}
.candidate-card h3 { margin: 0.5rem 0 0.25rem; font-size: 1rem; }
.candidate-card .position { font-size: 0.85rem; color: #64748b; }
.candidate-card .details { font-size: 0.85rem; color: #334155; margin-top: 0.5rem; }
.position-block { background: white; padding: 1rem 1.5rem 1.5rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
.position-block legend { font-weight: 600; font-size: 1.1rem; padding: 0 0.5rem; }
.candidate-list { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 0.5rem; }
.candidate-option { display: flex; align-items: center; gap: 0.5rem; background: #f8fafc; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
.candidate-option:hover { background: #e2e8f0; }
.candidate-option input[type="radio"] { margin: 0; }
.candidate-option img { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; }
.result-row { margin-bottom: 1rem; }
.result-header { display: flex; justify-content: space-between; }
.progress-bar-track { background: #e2e8f0; border-radius: 20px; height: 8px; overflow: hidden; margin-top: 0.25rem; }
.progress-bar-fill { background: #84cc16; height: 100%; border-radius: 20px; }
.results-block { margin-bottom: 2rem; }
.alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
.alert-success { background: #d1fae5; color: #065f46; }
.alert-error { background: #fee2e2; color: #991b1b; }
.alert-info { background: #dbeafe; color: #1e40af; }
.muted { color: #64748b; }

/* Responsive */
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); width: 280px; padding-top: 0; }
    .sidebar.open { transform: translateX(0); }
    .main-content { margin-left: 0; padding: 1rem; }
    .page-header { flex-direction: row; align-items: center; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 0.5rem 1rem; margin-bottom: 1.5rem; border-bottom: none; border-radius: 0 0 8px 8px; }
    .page-header h1 { font-size: 1.2rem; }
    .page-header .date { font-size: 0.8rem; }
    .hamburger { display: block; }
    .sidebar-overlay.active { display: block; opacity: 1; }
    .election-card { padding: 1.25rem; min-height: auto; }
    .card-top-row { flex-wrap: wrap; }
    .stats-bar { grid-template-columns: 1fr 1fr; }
    .card-actions { grid-template-columns: 1fr; }
    .form-actions { flex-wrap: wrap; }
    .form-actions .btn { flex: 1 1 100%; }
}
</style>
</head>
<body>
<!-- Sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo-area">
        <img src="assets/logo.png" alt="FICT Logo">
        <h2>Election</h2>
    </div>
    <nav>
        <a href="?page=home" class="<?= $page === 'home' ? 'active' : '' ?>">Dashboard</a>
        <a href="?page=vote" class="<?= $page === 'vote' ? 'active' : '' ?>">Vote</a>
        <a href="?page=candidates" class="<?= $page === 'candidates' ? 'active' : '' ?>">View Candidate Profile</a>
        <a href="?page=results" class="<?= $page === 'results' ? 'active' : '' ?>">Results</a>
    </nav>
    <div class="profile-section">
        <div class="avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div class="user-info">
            <div class="name"><?= h($user['name']) ?></div>
            <div class="role">Student</div>
        </div>
    </div>
    <div class="logout-link">
        <a href="logout.php">Log Out</a>
    </div>
</div>

<!-- Main content -->
<div class="main-content">
    <div class="page-header">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <button class="hamburger" id="hamburgerBtn" aria-label="Toggle sidebar">☰</button>
            <h1>Welcome, <?= h($user['name']) ?></h1>
        </div>
        <span class="date"><?= date('l, F j, Y') ?></span>
    </div>

    <?php if ($page === 'home'): ?>
        <!-- Dashboard Home -->

        <?php
            $active = 0;
            $completed = 0;
            foreach ($electionData as $data) {
                $now = new DateTime();
                $end = new DateTime($data['config']['end_date']);
                $start = new DateTime($data['config']['start_date']);
                $isOpen = ($now >= $start && $now <= $end && $data['config']['status'] === 'open');
                if ($isOpen) $active++;
                else $completed++;
            }
        ?>
        <div class="stats-bar">
            <div class="stat-box">
                <div class="stat-number"><?= $active ?></div>
                <div class="stat-label">Active Elections</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $completed ?></div>
                <div class="stat-label">Completed Elections</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= count($electionData) ?></div>
                <div class="stat-label">Total Elections</div>
            </div>
        </div>

        <h2 class="section-title">Your Elections</h2>

        <div class="election-cards">
            <?php foreach ($electionData as $eid => $data): 
                $config = $data['config'];
                $now = new DateTime();
                $start = new DateTime($config['start_date']);
                $end = new DateTime($config['end_date']);
                $isOpen = ($now >= $start && $now <= $end && $config['status'] === 'open');
                $statusText = $isOpen ? 'Ongoing' : ($now < $start ? 'Not started' : 'Closed');
                $statusClass = $isOpen ? '' : 'closed';
                $remaining = '';
                if ($isOpen) {
                    $diff = $end->getTimestamp() - $now->getTimestamp();
                    if ($diff > 0) {
                        $hours = floor($diff / 3600);
                        $minutes = floor(($diff % 3600) / 60);
                        $seconds = $diff % 60;
                        $remaining = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
                    } else {
                        $remaining = '00:00:00';
                    }
                }
                $canVote = $isOpen && !$hasVoted && $data['totalPositions'] > 0;
                $accentColor = $config['accent'] ?? '#84cc16';
            ?>
                <div class="election-card" style="border-top-color: <?= $accentColor ?>;">
                    <div class="card-top-row">
                        <div class="status-large <?= $statusClass ?>">
                            <span class="status-dot"></span>
                            <?= $statusText ?>
                        </div>
                        <?php if ($isOpen): ?>
                            <div class="remaining-time"><strong>Ends in:</strong> <span id="countdown_<?= $eid ?>"><?= $remaining ?></span></div>
                        <?php elseif ($now < $start): ?>
                            <div class="remaining-time">Starts on <?= date('M j, Y g:i A', $start->getTimestamp()) ?></div>
                        <?php else: ?>
                            <div class="remaining-time">This election has ended.</div>
                        <?php endif; ?>
                    </div>
                    <h2 class="election-title"><?= h($config['name']) ?></h2>
                    <div class="status-card">
                        <span class="status-label">Voting Status</span>
                        <span class="status-value <?= $hasVoted ? 'voted' : 'not-voted' ?>">
                            <?= $hasVoted ? '✔ Vote Submitted' : '❌ Not Submitted' ?>
                        </span>
                        <?php if ($hasVoted): ?>
                            <span class="thank-you">Thank you for participating.</span>
                        <?php endif; ?>
                    </div>
                    <!-- ⬇️ BUTTON ROW – margin-top: auto pushes it to bottom -->
                    <div class="card-actions">
                        <a href="?page=candidates&election_id=<?= $eid ?>" class="btn btn-secondary">View Candidates</a>
                        <?php if ($canVote): ?>
                            <a href="?page=vote&election_id=<?= $eid ?>" class="btn btn-primary">Vote Now</a>
                        <?php else: ?>
                            <button class="btn btn-primary" disabled>Vote Now</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
        // Countdown timers
        <?php foreach ($electionData as $eid => $data): 
            $end = new DateTime($data['config']['end_date']);
            $endTimestamp = $end->getTimestamp();
        ?>
            (function() {
                const endTime = <?= $endTimestamp ?> * 1000;
                const el = document.getElementById('countdown_<?= $eid ?>');
                if (!el) return;
                function update() {
                    const now = Date.now();
                    const diff = endTime - now;
                    if (diff <= 0) {
                        el.textContent = '00:00:00';
                        return;
                    }
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                    el.textContent = 
                        String(hours).padStart(2, '0') + ':' +
                        String(minutes).padStart(2, '0') + ':' +
                        String(seconds).padStart(2, '0');
                }
                update();
                setInterval(update, 1000);
            })();
        <?php endforeach; ?>
        </script>

        <!-- Featured Candidates -->
        <?php
            $featured = [];
            foreach ($electionData as $data) {
                foreach ($data['candidatesByPosition'] as $posId => $cands) {
                    foreach ($cands as $c) {
                        $featured[] = [
                            'candidate' => $c,
                            'position' => $allPositions[array_search($posId, array_column($allPositions, 'id'))]['title'] ?? 'Position'
                        ];
                    }
                }
            }
            shuffle($featured);
            $featured = array_slice($featured, 0, 3);
        ?>
        <?php if (!empty($featured)): ?>
        <div class="featured-section">
            <div class="featured-header">
                <h3>Featured Candidates</h3>
                <a href="?page=candidates" class="btn btn-secondary" style="padding:0.3rem 1rem; font-size:0.85rem;">View All</a>
            </div>
            <div class="featured-grid">
                <?php foreach ($featured as $item): 
                    $c = $item['candidate'];
                ?>
                    <div class="featured-card">
                        <?php if (!empty($c['photo'])): ?>
                            <img src="<?= h($c['photo']) ?>" alt="<?= h($c['first_name']) ?>">
                        <?php else: ?>
                            <img src="assets/default-avatar.png" alt="No photo">
                        <?php endif; ?>
                        <div class="name"><?= h($c['first_name']) ?> <?= h($c['last_name']) ?></div>
                        <div class="position"><?= h($item['position']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($page === 'vote'): ?>
        <!-- Vote page: show election cards if no election_id, else show voting form -->
        <?php if (!$selectedElectionId): ?>
            <h2>Choose an Election to Vote In</h2>
            <div class="election-cards">
                <?php foreach ($electionData as $eid => $data): 
                    $config = $data['config'];
                    $now = new DateTime();
                    $start = new DateTime($config['start_date']);
                    $end = new DateTime($config['end_date']);
                    $isOpen = ($now >= $start && $now <= $end && $config['status'] === 'open');
                    $statusText = $isOpen ? 'Ongoing' : ($now < $start ? 'Not started' : 'Closed');
                    $statusClass = $isOpen ? '' : 'closed';
                    $remaining = '';
                    if ($isOpen) {
                        $diff = $end->getTimestamp() - $now->getTimestamp();
                        if ($diff > 0) {
                            $hours = floor($diff / 3600);
                            $minutes = floor(($diff % 3600) / 60);
                            $seconds = $diff % 60;
                            $remaining = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
                        } else {
                            $remaining = '00:00:00';
                        }
                    }
                    $canVote = $isOpen && !$hasVoted && $data['totalPositions'] > 0;
                    $accentColor = $config['accent'] ?? '#84cc16';
                ?>
                    <div class="election-card" style="border-top-color: <?= $accentColor ?>;">
                        <div class="card-top-row">
                            <div class="status-large <?= $statusClass ?>">
                                <span class="status-dot"></span>
                                <?= $statusText ?>
                            </div>
                            <?php if ($isOpen): ?>
                                <div class="remaining-time"><strong>Ends in:</strong> <span id="countdown_vote_<?= $eid ?>"><?= $remaining ?></span></div>
                            <?php elseif ($now < $start): ?>
                                <div class="remaining-time">Starts on <?= date('M j, Y g:i A', $start->getTimestamp()) ?></div>
                            <?php else: ?>
                                <div class="remaining-time">This election has ended.</div>
                            <?php endif; ?>
                        </div>
                        <h2 class="election-title"><?= h($config['name']) ?></h2>
                        <div class="status-card">
                            <span class="status-label">Voting Status</span>
                            <span class="status-value <?= $hasVoted ? 'voted' : 'not-voted' ?>">
                                <?= $hasVoted ? '✔ Vote Submitted' : '❌ Not Submitted' ?>
                            </span>
                            <?php if ($hasVoted): ?>
                                <span class="thank-you">Thank you for participating.</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-actions">
                            <a href="?page=candidates&election_id=<?= $eid ?>" class="btn btn-secondary">View Candidates</a>
                            <?php if ($canVote): ?>
                                <a href="?page=vote&election_id=<?= $eid ?>" class="btn btn-primary">Vote Now</a>
                            <?php else: ?>
                                <button class="btn btn-primary" disabled>Vote Now</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <script>
            // Countdown for vote page cards
            <?php foreach ($electionData as $eid => $data): 
                $end = new DateTime($data['config']['end_date']);
                $endTimestamp = $end->getTimestamp();
            ?>
                (function() {
                    const endTime = <?= $endTimestamp ?> * 1000;
                    const el = document.getElementById('countdown_vote_<?= $eid ?>');
                    if (!el) return;
                    function update() {
                        const now = Date.now();
                        const diff = endTime - now;
                        if (diff <= 0) {
                            el.textContent = '00:00:00';
                            return;
                        }
                        const hours = Math.floor(diff / (1000 * 60 * 60));
                        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                        el.textContent = 
                            String(hours).padStart(2, '0') + ':' +
                            String(minutes).padStart(2, '0') + ':' +
                            String(seconds).padStart(2, '0');
                    }
                    update();
                    setInterval(update, 1000);
                })();
            <?php endforeach; ?>
            </script>
        <?php else: 
            // Voting form for a specific election
            if (!isset($electionData[$selectedElectionId])) {
                echo '<div class="alert alert-error">Invalid election.</div>';
            } else {
                $data = $electionData[$selectedElectionId];
                $config = $data['config'];
                $positions = $data['positions'];
                $candidatesByPosition = $data['candidatesByPosition'];

                $now = new DateTime();
                $start = new DateTime($config['start_date']);
                $end = new DateTime($config['end_date']);
                $isOpen = ($now >= $start && $now <= $end && $config['status'] === 'open');

                if ($hasVoted) {
                    echo '<div class="alert alert-info">You have already voted in this election.</div>';
                } elseif (!$isOpen) {
                    echo '<div class="alert alert-error">This election is not currently open.</div>';
                } elseif (empty($positions)) {
                    echo '<div class="alert alert-info">No positions set up for this election yet.</div>';
                } else {
        ?>
                    <h2>Vote for <?= h($config['name']) ?></h2>
                    <div id="statusMessage" class="alert" style="display:none;"></div>
                    <form id="voteForm" data-election="<?= $selectedElectionId ?>">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="election_id" value="<?= $selectedElectionId ?>">
                        <?php foreach ($positions as $position): ?>
                            <fieldset class="position-block">
                                <legend><?= h($position['title']) ?></legend>
                                <?php $candidates = $candidatesByPosition[$position['id']] ?? []; ?>
                                <?php if (empty($candidates)): ?>
                                    <p class="muted">No candidates registered for this position.</p>
                                <?php else: ?>
                                    <div class="candidate-list">
                                        <?php foreach ($candidates as $candidate): ?>
                                            <label class="candidate-option">
                                                <input type="radio" name="position_<?= (int) $position['id'] ?>" value="<?= (int) $candidate['id'] ?>" required>
                                                <?php if (!empty($candidate['photo'])): ?>
                                                    <img src="<?= h($candidate['photo']) ?>" alt="">
                                                <?php endif; ?>
                                                <span><?= h($candidate['first_name']) ?> <?= h($candidate['last_name']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </fieldset>
                        <?php endforeach; ?>
                        <!-- Vote page buttons – equal width -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="submitBtn">Submit My Vote</button>
                            <a href="?page=vote" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
        <?php 
                }
            }
        endif; ?>

    <?php elseif ($page === 'candidates'): ?>
        <!-- Candidate Profiles -->
        <h2>All Candidates</h2>
        <?php 
            $filterEid = isset($_GET['election_id']) ? (int)$_GET['election_id'] : null;
            $displayElections = $filterEid ? [$filterEid => $electionData[$filterEid]] : $electionData;
            $hasAny = false;
            foreach ($displayElections as $eid => $data):
                $positions = $data['positions'];
                if (empty($positions)) continue;
                $hasAny = true;
        ?>
                <h3><?= h($data['config']['name']) ?></h3>
                <div class="card-grid">
                <?php foreach ($positions as $position): 
                    $candidates = $data['candidatesByPosition'][$position['id']] ?? [];
                    foreach ($candidates as $candidate):
                        $details = getCandidateDetails($candidate);
                ?>
                        <div class="candidate-card">
                            <?php if (!empty($candidate['photo'])): ?>
                                <img src="<?= h($candidate['photo']) ?>" alt="<?= h($candidate['first_name']) ?>">
                            <?php else: ?>
                                <img src="assets/default-avatar.png" alt="No photo">
                            <?php endif; ?>
                            <h3><?= h($candidate['first_name']) ?> <?= h($candidate['last_name']) ?></h3>
                            <div class="position"><?= h($position['title']) ?></div>
                            <div class="details">
                                <div><?= h($details['major']) ?></div>
                                <div><?= h($details['year']) ?></div>
                                <div><strong>Platform:</strong> <?= h($details['platform']) ?></div>
                            </div>
                        </div>
                <?php endforeach; endforeach; ?>
                </div>
        <?php endforeach; 
        if (!$hasAny) echo '<p>No candidates found.</p>';
        ?>

    <?php elseif ($page === 'results'): ?>
        <!-- Results page -->
        <h2>Live Results</h2>
        <p class="muted">Results update automatically every 15 seconds.</p>
        <?php if (empty($resultsByPosition)): ?>
            <div class="alert alert-info">No results yet.</div>
        <?php else: ?>
            <?php 
            $currentElection = '';
            foreach ($resultsByPosition as $result): 
                if ($result['election_name'] !== $currentElection) {
                    if ($currentElection !== '') echo '<hr>';
                    $currentElection = $result['election_name'];
                    echo '<h2>' . h($currentElection) . '</h2>';
                }
            ?>
                <div class="results-block">
                    <h3><?= h($result['title']) ?></h3>
                    <?php if (empty($result['candidates'])): ?>
                        <p class="muted">No candidates.</p>
                    <?php else: ?>
                        <?php foreach ($result['candidates'] as $candidate): ?>
                            <?php
                            $voteCount = (int) $candidate['vote_count'];
                            $percentage = $result['total_votes'] > 0 ? round(($voteCount / $result['total_votes']) * 100, 1) : 0;
                            ?>
                            <div class="result-row">
                                <div class="result-header">
                                    <span class="candidate-name"><?= h($candidate['first_name']) ?> <?= h($candidate['last_name']) ?></span>
                                    <span class="vote-count"><?= $voteCount ?> vote<?= $voteCount === 1 ? '' : 's' ?> (<?= $percentage ?>%)</span>
                                </div>
                                <div class="progress-bar-track">
                                    <div class="progress-bar-fill" style="width: <?= $percentage ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <p class="muted">Total votes: <?= (int) $result['total_votes'] ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Hamburger toggle & Vote form AJAX -->
<script>
const hamburger = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
function toggleSidebar(open) {
    if (open === undefined) open = !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', open);
    overlay.classList.toggle('active', open);
    document.body.style.overflow = open ? 'hidden' : '';
}
hamburger.addEventListener('click', () => toggleSidebar());
overlay.addEventListener('click', () => toggleSidebar(false));
window.addEventListener('resize', () => { if (window.innerWidth > 768) toggleSidebar(false); });

// Vote form submission
const voteForm = document.getElementById('voteForm');
if (voteForm) {
    voteForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const submitBtn = document.getElementById('submitBtn');
        const statusMessage = document.getElementById('statusMessage');
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
                setTimeout(() => { window.location.href = '?page=home'; }, 1500);
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
