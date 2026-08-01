<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

startSecureSession();

// Real session-based guard — same is_admin flag that login.php sets.
if (empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit;
}

$csrfToken = csrfToken();

/**
 * ADMIN DASHBOARD
 * ------------------------------------------------------------------
 * Access is gated by the real session above (see login.php / logout.php).
 *
 * The DATA rendered inside this page — departments, elections, candidates,
 * logs — is still mock data living in the `state` object inside the
 * <script> tag below, and resets on reload. That part is a UI prototype,
 * not yet wired to positions/candidates/votes in the DB.
 *
 * For real, DB-backed position/candidate management right now, use
 * quick-panel.php (linked in the sidebar below) — it's the same tool
 * that used to live at admin/index.php.
 * ------------------------------------------------------------------
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | School Elections</title>
<style>
:root{
    --navy:#1e293b;
    --navy-2:#334155;
    --lime:#84cc16;
    --lime-dark:#65a30d;
    --bg:#f4f7fa;
    --ink:#0f172a;
    --muted:#64748b;
    --line:#e2e8f0;
}
*{box-sizing:border-box;}
body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);}

/* ================= SHELL / SIDEBAR ================= */
/* Access is gated server-side (see the PHP guard at the top of this file),
   so the shell renders directly — no client-side login screen anymore. */
#appShell{display:flex;min-height:100vh;}
.sidebar{
    width:240px;background:var(--navy);color:#e2e8f0;display:flex;flex-direction:column;
    position:fixed;top:0;left:0;bottom:0;z-index:1000;transition:transform .3s ease;overflow:hidden;
}
.sidebar .logo-area{padding:1.5rem 1.5rem 1rem;text-align:center;flex-shrink:0;}
.sidebar .logo-area .badge{
    width:56px;height:56px;border-radius:50%;background:var(--lime);color:#052e16;
    display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.3rem;margin:0 auto;
}
.sidebar .logo-area h2{color:#fff;margin:.6rem 0 0;font-weight:400;font-size:1.05rem;}
.sidebar .logo-area .tag{color:#94a3b8;font-size:.7rem;letter-spacing:.05em;text-transform:uppercase;}
.sidebar nav{flex:1;overflow-y:auto;padding:.5rem 0;}
.sidebar nav a{
    display:flex;align-items:center;gap:.6rem;padding:.75rem 1.5rem;color:#cbd5e1;text-decoration:none;
    border-left:3px solid transparent;transition:background .2s,border-color .2s;cursor:pointer;font-size:.92rem;
}
.sidebar nav a .ic{width:1.1em;display:inline-block;text-align:center;}
.sidebar nav a:hover{background:var(--navy-2);border-left-color:var(--lime);}
.sidebar nav a.active{background:var(--navy-2);border-left-color:var(--lime);color:#fff;}
.sidebar nav a.disabled{opacity:.45;cursor:not-allowed;}
.sidebar nav a.disabled:hover{background:transparent;border-left-color:transparent;}
.sidebar .profile-section{
    padding:1rem 1.5rem;border-top:1px solid var(--navy-2);display:flex;align-items:center;gap:.75rem;
    flex-shrink:0;background:var(--navy);
}
.sidebar .profile-section .avatar{
    width:40px;height:40px;border-radius:50%;background:var(--lime);color:#052e16;
    display:flex;align-items:center;justify-content:center;font-weight:600;font-size:1.1rem;flex-shrink:0;
}
.sidebar .profile-section .user-info{flex:1;overflow:hidden;}
.sidebar .profile-section .user-info .name{font-weight:500;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sidebar .profile-section .user-info .role{font-size:.75rem;color:#94a3b8;}
.sidebar .logout-link{padding:.75rem 1.5rem;border-top:1px solid var(--navy-2);flex-shrink:0;background:var(--navy);}
.sidebar .logout-link a{color:#f87171;text-decoration:none;cursor:pointer;font-size:.9rem;}
.sidebar .logout-link a:hover{text-decoration:underline;}

.main-content{margin-left:240px;flex:1;padding:2rem;transition:margin-left .3s;}
.page-header{
    display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;padding-bottom:1rem;
    border-bottom:1px solid var(--line);flex-wrap:wrap;gap:.5rem;position:sticky;top:0;background:var(--bg);
    z-index:500;padding-top:.5rem;
}
.page-header h1{margin:0;font-weight:400;color:var(--navy);font-size:1.5rem;}
.page-header .date{font-size:.95rem;color:var(--muted);}
.hamburger{display:none;background:none;border:none;font-size:1.8rem;color:var(--navy);cursor:pointer;padding:.25rem .5rem;line-height:1;}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;opacity:0;transition:opacity .3s;}
.sidebar-overlay.active{display:block;opacity:1;}

.panel{display:none;}
.panel.active{display:block;}

/* ================= SHARED COMPONENTS ================= */
.stats-bar{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:2rem;}
.stat-box{background:#fff;border-radius:16px;padding:1rem 1.25rem;box-shadow:0 2px 8px rgba(0,0,0,.04);text-align:center;}
.stat-box .stat-number{font-size:2rem;font-weight:700;color:var(--ink);}
.stat-box .stat-label{font-size:.85rem;color:var(--muted);margin-top:.25rem;}
.section-title{font-size:1.3rem;font-weight:600;color:var(--ink);margin:0 0 1rem;}
.card{background:#fff;border-radius:16px;box-shadow:0 2px 8px rgba(0,0,0,.04);padding:1.5rem;}
.grid-2{display:grid;grid-template-columns:1.3fr 1fr;gap:1.5rem;}
@media (max-width:1000px){.grid-2{grid-template-columns:1fr;}}
.muted{color:var(--muted);}
.btn{
    box-sizing:border-box;height:42px;display:inline-flex;align-items:center;justify-content:center;
    border-radius:10px;font-weight:600;font-size:.85rem;font-family:inherit;padding:0 1.1rem;
    transition:background .2s,border-color .2s,transform .15s;border:2px solid transparent;
    text-decoration:none;cursor:pointer;white-space:nowrap;
}
.btn:active{transform:scale(.98);}
.btn-primary{background:var(--lime);color:#fff;border-color:var(--lime);}
.btn-primary:hover{background:var(--lime-dark);border-color:var(--lime-dark);}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;}
.btn-secondary{background:#fff;color:var(--navy);border-color:var(--line);}
.btn-secondary:hover{background:#f8fafc;border-color:#94a3b8;}
.btn-danger{background:#fff;color:#dc2626;border-color:#fecaca;}
.btn-danger:hover{background:#fef2f2;}
.btn-navy{background:var(--navy);color:#fff;border-color:var(--navy);}
.btn-navy:hover{background:var(--navy-2);}
.btn-sm{height:34px;font-size:.78rem;padding:0 .8rem;}
.btn-block{width:100%;}
.pill-toggle{display:flex;gap:.5rem;flex-wrap:wrap;}
.pill-toggle button{
    border:1.5px solid var(--line);background:#fff;color:var(--muted);padding:.4rem .85rem;border-radius:999px;
    font-size:.78rem;font-weight:600;cursor:pointer;transition:.15s;
}
.pill-toggle button.selected{background:var(--navy);color:#fff;border-color:var(--navy);}
.alert{padding:.9rem 1rem;border-radius:10px;margin-bottom:1rem;font-size:.9rem;}
.alert-success{background:#d1fae5;color:#065f46;}
.alert-error{background:#fee2e2;color:#991b1b;}
.alert-info{background:#dbeafe;color:#1e40af;}
.progress-bar-track{background:var(--line);border-radius:20px;height:8px;overflow:hidden;margin-top:.3rem;}
.progress-bar-fill{background:var(--lime);height:100%;border-radius:20px;}

/* status pill (matches student side) */
.status-large{display:inline-flex;align-items:center;gap:.5rem;font-size:.75rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#15803d;background:rgba(34,197,94,.12);padding:.4rem .85rem .4rem .7rem;border-radius:999px;line-height:1;}
.status-large .status-dot{width:8px;height:8px;border-radius:50%;background:#22c55e;flex-shrink:0;}
.status-large.closed{color:#b91c1c;background:rgba(239,68,68,.1);}
.status-large.closed .status-dot{background:#ef4444;}
.status-large.paused{color:#92400e;background:rgba(245,158,11,.15);}
.status-large.paused .status-dot{background:#f59e0b;}
.status-large.scheduled{color:#3730a3;background:rgba(99,102,241,.12);}
.status-large.scheduled .status-dot{background:#6366f1;}

/* ================= DASHBOARD PANEL ================= */
.dept-row{display:flex;align-items:center;gap:1rem;padding:.7rem 0;border-bottom:1px solid #f1f5f9;}
.dept-row:last-child{border-bottom:none;}
.dept-row .dept-name{width:70px;flex-shrink:0;font-weight:600;font-size:.85rem;}
.dept-row .dept-bar{flex:1;}
.dept-row .dept-nums{width:150px;flex-shrink:0;text-align:right;font-size:.78rem;color:var(--muted);}
.donut-wrap{display:flex;align-items:center;justify-content:center;gap:1.5rem;flex-wrap:wrap;}
.donut-legend{display:flex;flex-direction:column;gap:.5rem;font-size:.85rem;}
.donut-legend .lg-item{display:flex;align-items:center;gap:.5rem;}
.donut-legend .dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.insight-list{margin:0;padding-left:1.1rem;font-size:.87rem;color:#334155;line-height:1.7;}

.log-item{display:flex;gap:.75rem;padding:.6rem 0;border-bottom:1px solid #f1f5f9;font-size:.85rem;}
.log-item:last-child{border-bottom:none;}
.log-item .log-time{color:var(--muted);white-space:nowrap;font-variant-numeric:tabular-nums;flex-shrink:0;}
.log-item .log-text{color:#334155;}
.panel-flex-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;}

.results-mini{border-bottom:1px solid #f1f5f9;padding-bottom:1rem;margin-bottom:1rem;}
.results-mini:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0;}
.results-mini .rm-head{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;margin-bottom:.5rem;}
.results-mini .rm-title{font-weight:600;font-size:.92rem;}

/* ================= ELECTION PANEL — CARDS ================= */
.election-cards{display:flex;flex-direction:column;gap:1.5rem;}
.election-card{background:#fff;border-radius:18px;border:1px solid #eef1f5;box-shadow:0 3px 12px rgba(15,23,42,.05);padding:1.6rem 1.75rem;border-top:4px solid var(--lime);display:flex;flex-direction:column;gap:1rem;}
.card-top-row{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.6rem;}
.remaining-time{display:inline-flex;align-items:baseline;gap:.4rem;font-size:.82rem;color:var(--muted);background:#f1f5f9;padding:.4rem .8rem;border-radius:999px;white-space:nowrap;}
.remaining-time strong{color:#334155;font-weight:600;font-size:.78rem;}
.remaining-time span{color:var(--ink);font-weight:700;font-variant-numeric:tabular-nums;}
.election-title{font-size:1.3rem;font-weight:700;color:var(--ink);margin:0;}
.election-sub{font-size:.82rem;color:var(--muted);margin-top:-.6rem;}
.schedule-box{background:#f8fafc;border-radius:12px;padding:1rem 1.1rem;display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;}
.schedule-field{display:flex;flex-direction:column;gap:.3rem;}
.schedule-field label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);}
.schedule-field input{padding:.45rem .6rem;border:1px solid var(--line);border-radius:8px;font-size:.85rem;}
.schedule-field input:disabled{background:#eef1f5;color:#94a3b8;}
.schedule-actions{display:flex;gap:.5rem;margin-left:auto;flex-wrap:wrap;}
.card-actions{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;}
.card-actions .btn{width:100%;}
.turnout-line{font-size:.82rem;color:var(--muted);}

/* ================= CREATE ELECTION WIZARD ================= */
.wizard-topbar{display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;}
.wizard-topbar .back-btn{background:none;border:none;color:var(--navy);font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.3rem;font-size:.9rem;padding:.3rem 0;}
.wizard-topbar .back-btn:hover{color:var(--lime-dark);}
.type-choice-row{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem;}
@media (max-width:700px){.type-choice-row{grid-template-columns:1fr;}}
.type-choice{
    border:2px solid var(--line);border-radius:16px;padding:1.5rem;text-align:center;cursor:pointer;background:#fff;
    transition:.15s;
}
.type-choice:hover{border-color:#bbf7d0;}
.type-choice.selected{border-color:var(--lime);background:#f7fee7;}
.type-choice .tc-icon{font-size:2rem;margin-bottom:.5rem;}
.type-choice h3{margin:.2rem 0;color:var(--ink);}
.type-choice p{color:var(--muted);font-size:.85rem;margin:0;}

.wizard-section{background:#fff;border-radius:16px;box-shadow:0 2px 8px rgba(0,0,0,.04);padding:1.5rem;margin-bottom:1.25rem;}
.wizard-section h3{margin:0 0 1rem;font-size:1.05rem;color:var(--ink);}
.form-row{display:flex;flex-direction:column;gap:.35rem;margin-bottom:1rem;}
.form-row label{font-size:.82rem;font-weight:600;color:#334155;}
.form-row input[type=text], .form-row input[type=number], .form-row select, .form-row textarea{
    padding:.6rem .75rem;border:1px solid var(--line);border-radius:8px;font-size:.88rem;font-family:inherit;
}
.form-row textarea{resize:vertical;min-height:70px;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
@media (max-width:640px){.two-col{grid-template-columns:1fr;}}

.toggle-switch{display:inline-flex;align-items:center;gap:.6rem;cursor:pointer;user-select:none;}
.toggle-switch .track{width:42px;height:24px;border-radius:999px;background:#cbd5e1;position:relative;transition:.2s;flex-shrink:0;}
.toggle-switch .track::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:.2s;}
.toggle-switch.on .track{background:var(--lime);}
.toggle-switch.on .track::after{left:21px;}

.party-input-list{display:flex;flex-direction:column;gap:.6rem;margin-top:.75rem;}
.party-input-list input{padding:.55rem .7rem;border:1px solid var(--line);border-radius:8px;font-size:.85rem;}

table.pos-table{width:100%;border-collapse:collapse;margin-top:1rem;font-size:.85rem;}
table.pos-table th{text-align:left;color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;padding:.5rem .4rem;border-bottom:2px solid var(--line);}
table.pos-table td{padding:.5rem .4rem;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
table.pos-table input, table.pos-table select{width:100%;padding:.4rem .5rem;border:1px solid var(--line);border-radius:6px;font-size:.83rem;}

.candidate-block{border:1px solid var(--line);border-radius:14px;padding:1.1rem 1.25rem;margin-bottom:1rem;background:#fbfcfe;}
.candidate-block h4{margin:0 0 .8rem;font-size:.95rem;color:var(--ink);}
.candidate-photo-row{display:flex;gap:1rem;align-items:center;margin-bottom:.9rem;}
.candidate-photo-row img{width:64px;height:64px;border-radius:50%;object-fit:cover;background:#e2e8f0;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.1);}
.position-group-title{font-weight:700;color:var(--navy);margin:1.5rem 0 .75rem;font-size:1rem;}
.position-group-title:first-child{margin-top:0;}

.wizard-footer{display:flex;justify-content:space-between;gap:1rem;margin-top:1.5rem;}

/* ================= RESULTS PANEL ================= */
.results-visibility-row{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-bottom:.75rem;}
.results-visibility-row .vis-label{font-size:.78rem;color:var(--muted);font-weight:600;margin-right:.3rem;}
.result-row{margin-bottom:.9rem;}
.result-header{display:flex;justify-content:space-between;font-size:.88rem;}
.results-block{margin-bottom:1.75rem;}

/* ================= LOGS PANEL ================= */
.logs-full{background:#fff;border-radius:16px;box-shadow:0 2px 8px rgba(0,0,0,.04);}
.logs-full .log-item{padding:.85rem 1.5rem;}
.logs-filter{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;}

.coming-soon{background:#fff;border-radius:16px;padding:3rem 2rem;text-align:center;color:var(--muted);box-shadow:0 2px 8px rgba(0,0,0,.04);}
.coming-soon .cs-icon{font-size:2.5rem;margin-bottom:.75rem;}

/* ================= STUDENTS PANEL ================= */
.students-toolbar{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin-bottom:1.25rem;}
.students-toolbar input[type=text]{flex:1;min-width:180px;padding:.55rem .8rem;border:1px solid var(--line);border-radius:8px;font-size:.85rem;}
.students-toolbar select{padding:.55rem .7rem;border:1px solid var(--line);border-radius:8px;font-size:.83rem;background:#fff;}
.students-table-wrap{background:#fff;border-radius:16px;box-shadow:0 2px 8px rgba(0,0,0,.04);overflow-x:auto;}
table.students-table{width:100%;border-collapse:collapse;font-size:.85rem;min-width:920px;}
table.students-table th{text-align:left;color:var(--muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.03em;padding:.85rem 1rem;border-bottom:2px solid var(--line);white-space:nowrap;}
table.students-table td{padding:.7rem 1rem;border-bottom:1px solid #f1f5f9;vertical-align:middle;white-space:nowrap;}
table.students-table tr:last-child td{border-bottom:none;}
table.students-table tr:hover td{background:#fafcff;}
.name-cell{white-space:normal !important;min-width:160px;}
.name-cell .sub{font-size:.72rem;color:var(--muted);}
.status-pill{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700;padding:.28rem .65rem;border-radius:999px;white-space:nowrap;}
.status-pill.voted{color:#15803d;background:rgba(34,197,94,.12);}
.status-pill.not-voted{color:#b45309;background:rgba(245,158,11,.12);}
.status-pill.active{color:#1e40af;background:rgba(59,130,246,.12);}
.status-pill.suspended{color:#b91c1c;background:rgba(239,68,68,.1);}
.row-actions{display:flex;gap:.4rem;}
.row-actions button{border:none;background:#f1f5f9;color:var(--navy);width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:.85rem;display:inline-flex;align-items:center;justify-content:center;}
.row-actions button:hover{background:#e2e8f0;}
.row-actions button.danger{color:#dc2626;}
.row-actions button.danger:hover{background:#fee2e2;}
.empty-row td{text-align:center;color:var(--muted);padding:2rem 1rem;}

.pw-field{position:relative;display:flex;align-items:stretch;}
.pw-field input{flex:1;padding-right:2.5rem;}
.pw-toggle-btn{position:absolute;right:.4rem;top:50%;transform:translateY(-50%);background:none;border:none;padding:.25rem;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:0;}
.pw-toggle-btn svg{width:18px;height:18px;stroke:#64748b;}
.pw-toggle-btn .pw-icon-off{display:block;}
.pw-toggle-btn .pw-icon-on{display:none;}
.pw-toggle-btn.is-visible .pw-icon-off{display:none;}
.pw-toggle-btn.is-visible .pw-icon-on{display:block;}
.pw-hint{font-size:.75rem;color:var(--muted);margin-top:.3rem;}

/* ================= RESPONSIVE ================= */
@media (max-width:768px){
    .sidebar{transform:translateX(-100%);width:280px;}
    .sidebar.open{transform:translateX(0);}
    .main-content{margin-left:0;padding:1rem;}
    .page-header{flex-direction:row;align-items:center;background:#fff;box-shadow:0 2px 4px rgba(0,0,0,.05);padding:.5rem 1rem;margin-bottom:1.5rem;border-bottom:none;border-radius:0 0 8px 8px;}
    .page-header h1{font-size:1.15rem;}
    .hamburger{display:block;}
    .card-actions{grid-template-columns:1fr;}
    .schedule-actions{margin-left:0;width:100%;}
}
</style>
</head>
<body>

<!-- ================= APP SHELL ================= -->
<div id="appShell">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="sidebar" id="sidebar">
        <div class="logo-area">
            <div class="badge">FICT</div>
            <h2>Election Admin</h2>
            <div class="tag">Control Panel</div>
        </div>
        <nav>
            <a data-panel="dashboard" class="nav-link active" onclick="showPanel('dashboard')"><span class="ic">▦</span> Dashboard</a>
            <a data-panel="election" class="nav-link" onclick="showPanel('election')"><span class="ic">🗳</span> Election</a>
            <a data-panel="students" class="nav-link" onclick="showPanel('students')"><span class="ic">🎓</span> Students</a>
            <a data-panel="results" class="nav-link" onclick="showPanel('results')"><span class="ic">📊</span> Results</a>
            <a data-panel="archive" class="nav-link disabled" onclick="return false;"><span class="ic">🗄</span> Archive <span class="muted" style="font-size:.65rem;margin-left:.3rem;">(soon)</span></a>
            <a data-panel="logs" class="nav-link" onclick="showPanel('logs')"><span class="ic">📜</span> Logs</a>
            <a href="quick-panel.php" class="nav-link"><span class="ic">🛠</span> Quick Panel <span class="muted" style="font-size:.65rem;margin-left:.3rem;">(DB-connected)</span></a>
        </nav>
        <div class="profile-section">
            <div class="avatar">A</div>
            <div class="user-info">
                <div class="name">Admin</div>
                <div class="role">Administrator</div>
            </div>
        </div>
        <div class="logout-link">
            <a href="logout.php">Log Out</a>
        </div>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;align-items:center;gap:.75rem;">
                <button class="hamburger" id="hamburgerBtn" aria-label="Toggle sidebar">☰</button>
                <h1 id="pageHeaderTitle">Dashboard</h1>
            </div>
            <span class="date" id="pageHeaderDate"></span>
        </div>

        <!-- ============ DASHBOARD PANEL ============ -->
        <div class="panel active" id="panel-dashboard"></div>

        <!-- ============ ELECTION PANEL ============ -->
        <div class="panel" id="panel-election"></div>

        <!-- ============ STUDENTS PANEL ============ -->
        <div class="panel" id="panel-students"></div>

        <!-- ============ RESULTS PANEL ============ -->
        <div class="panel" id="panel-results"></div>

        <!-- ============ ARCHIVE (placeholder) ============ -->
        <div class="panel" id="panel-archive">
            <div class="coming-soon"><div class="cs-icon">🗄</div><h3>Archive is coming soon</h3><p>Past, completed elections will be stored here for reference.</p></div>
        </div>

        <!-- ============ LOGS PANEL ============ -->
        <div class="panel" id="panel-logs"></div>
    </div>
</div>

<script>
/* =========================================================================
   MOCK STATE  (frontend-only — replace with real API/DB calls later)
   ========================================================================= */
const state = {
    // Every student account lives here now — department turnout on the
    // Dashboard is computed FROM this list (see departmentStats()) instead
    // of being separately hard-coded, so the two panels can't drift apart.
    //
    // SECURITY NOTE ON `password`: storing it in plain, readable form here
    // is a UI-prototype shortcut only. See the note above registerStudent()
    // further down for what this needs to become on the real backend.
    students: [
        {id:1,  studentId:'2023-00101', fullName:'John Alonsagay',        department:'FICT', major:'BS Information Technology', section:'3A', yearLevel:'3rd Year', email:'john.alonsagay@fict.edu.ph',        status:'active',    hasVoted:true,  password:'Passw0rd!01', dateRegistered:'2026-06-10'},
        {id:2,  studentId:'2023-00102', fullName:'Kim Nathaniel Uy',      department:'FICT', major:'BS Information Technology', section:'3A', yearLevel:'3rd Year', email:'kim.uy@fict.edu.ph',                status:'active',    hasVoted:true,  password:'Passw0rd!02', dateRegistered:'2026-06-10'},
        {id:3,  studentId:'2023-00103', fullName:'Maria Dolores Ferrer',  department:'FICT', major:'BS Computer Science',       section:'2B', yearLevel:'2nd Year', email:'maria.ferrer@fict.edu.ph',          status:'active',    hasVoted:true,  password:'Passw0rd!03', dateRegistered:'2026-06-11'},
        {id:4,  studentId:'2023-00104', fullName:'Rafael Tan',            department:'FICT', major:'BS Information Systems',    section:'4A', yearLevel:'4th Year', email:'rafael.tan@fict.edu.ph',            status:'active',    hasVoted:false, password:'Passw0rd!04', dateRegistered:'2026-06-11'},
        {id:5,  studentId:'2023-00105', fullName:'Ella Marasigan',        department:'FICT', major:'BS Information Technology', section:'1A', yearLevel:'1st Year', email:'ella.marasigan@fict.edu.ph',        status:'active',    hasVoted:false, password:'Passw0rd!05', dateRegistered:'2026-06-12'},
        {id:6,  studentId:'2023-00106', fullName:'Vincent Ocampo',        department:'FICT', major:'BS Computer Science',       section:'4A', yearLevel:'4th Year', email:'vincent.ocampo@fict.edu.ph',        status:'suspended', hasVoted:false, password:'Passw0rd!06', dateRegistered:'2026-06-12'},

        {id:7,  studentId:'2023-00201', fullName:'Patricia Gomez',        department:'CBA',  major:'BS Business Administration', section:'2A', yearLevel:'2nd Year', email:'patricia.gomez@cba.edu.ph',        status:'active',    hasVoted:true,  password:'Passw0rd!07', dateRegistered:'2026-06-10'},
        {id:8,  studentId:'2023-00202', fullName:'Carlo Mendoza',         department:'CBA',  major:'BS Accountancy',            section:'3B', yearLevel:'3rd Year', email:'carlo.mendoza@cba.edu.ph',         status:'active',    hasVoted:false, password:'Passw0rd!08', dateRegistered:'2026-06-10'},
        {id:9,  studentId:'2023-00203', fullName:'Diane Salvador',        department:'CBA',  major:'BS Entrepreneurship',       section:'1A', yearLevel:'1st Year', email:'diane.salvador@cba.edu.ph',        status:'active',    hasVoted:false, password:'Passw0rd!09', dateRegistered:'2026-06-11'},
        {id:10, studentId:'2023-00204', fullName:'Josef Ramirez',         department:'CBA',  major:'BS Business Administration', section:'4A', yearLevel:'4th Year', email:'josef.ramirez@cba.edu.ph',        status:'active',    hasVoted:true,  password:'Passw0rd!10', dateRegistered:'2026-06-11'},
        {id:11, studentId:'2023-00205', fullName:'Faith Dizon',           department:'CBA',  major:'BS Accountancy',            section:'2A', yearLevel:'2nd Year', email:'faith.dizon@cba.edu.ph',           status:'active',    hasVoted:false, password:'Passw0rd!11', dateRegistered:'2026-06-12'},
        {id:12, studentId:'2023-00206', fullName:'Nico Aguilar',          department:'CBA',  major:'BS Entrepreneurship',       section:'3A', yearLevel:'3rd Year', email:'nico.aguilar@cba.edu.ph',          status:'active',    hasVoted:false, password:'Passw0rd!12', dateRegistered:'2026-06-12'},

        {id:13, studentId:'2023-00301', fullName:'Grace Lim',             department:'CTHM', major:'BS Hospitality Management', section:'2A', yearLevel:'2nd Year', email:'grace.lim@cthm.edu.ph',            status:'active',    hasVoted:true,  password:'Passw0rd!13', dateRegistered:'2026-06-10'},
        {id:14, studentId:'2023-00302', fullName:'Marco Padilla',         department:'CTHM', major:'BS Tourism Management',     section:'3A', yearLevel:'3rd Year', email:'marco.padilla@cthm.edu.ph',        status:'active',    hasVoted:true,  password:'Passw0rd!14', dateRegistered:'2026-06-10'},
        {id:15, studentId:'2023-00303', fullName:'Isabel Cruz',           department:'CTHM', major:'BS Hospitality Management', section:'1A', yearLevel:'1st Year', email:'isabel.cruz@cthm.edu.ph',          status:'active',    hasVoted:false, password:'Passw0rd!15', dateRegistered:'2026-06-11'},
        {id:16, studentId:'2023-00304', fullName:'Julius Navarro',        department:'CTHM', major:'BS Tourism Management',     section:'4A', yearLevel:'4th Year', email:'julius.navarro@cthm.edu.ph',       status:'active',    hasVoted:true,  password:'Passw0rd!16', dateRegistered:'2026-06-11'},
        {id:17, studentId:'2023-00305', fullName:'Cristine Yap',          department:'CTHM', major:'BS Hospitality Management', section:'2B', yearLevel:'2nd Year', email:'cristine.yap@cthm.edu.ph',         status:'active',    hasVoted:false, password:'Passw0rd!17', dateRegistered:'2026-06-12'},
        {id:18, studentId:'2023-00306', fullName:'Aaron Bautista',        department:'CTHM', major:'BS Tourism Management',     section:'1A', yearLevel:'1st Year', email:'aaron.bautista@cthm.edu.ph',       status:'active',    hasVoted:false, password:'Passw0rd!18', dateRegistered:'2026-06-12'},

        {id:19, studentId:'2023-00401', fullName:'Bianca Delos Santos',   department:'CAS',  major:'AB Communication',          section:'3A', yearLevel:'3rd Year', email:'bianca.delossantos@cas.edu.ph',    status:'active',    hasVoted:false, password:'Passw0rd!19', dateRegistered:'2026-06-10'},
        {id:20, studentId:'2023-00402', fullName:'Renz Villamor',         department:'CAS',  major:'BS Psychology',             section:'2A', yearLevel:'2nd Year', email:'renz.villamor@cas.edu.ph',         status:'active',    hasVoted:false, password:'Passw0rd!20', dateRegistered:'2026-06-10'},
        {id:21, studentId:'2023-00403', fullName:'Kaye Fernandez',        department:'CAS',  major:'BS Biology',                section:'4A', yearLevel:'4th Year', email:'kaye.fernandez@cas.edu.ph',        status:'active',    hasVoted:true,  password:'Passw0rd!21', dateRegistered:'2026-06-11'},
        {id:22, studentId:'2023-00404', fullName:'Aldrin Pascual',        department:'CAS',  major:'AB Communication',          section:'1A', yearLevel:'1st Year', email:'aldrin.pascual@cas.edu.ph',        status:'active',    hasVoted:false, password:'Passw0rd!22', dateRegistered:'2026-06-11'},
        {id:23, studentId:'2023-00405', fullName:'Michelle Torres',       department:'CAS',  major:'BS Psychology',             section:'3B', yearLevel:'3rd Year', email:'michelle.torres@cas.edu.ph',       status:'active',    hasVoted:false, password:'Passw0rd!23', dateRegistered:'2026-06-12'},
        {id:24, studentId:'2023-00406', fullName:'Louie Manalo',          department:'CAS',  major:'BS Biology',                section:'2A', yearLevel:'2nd Year', email:'louie.manalo@cas.edu.ph',          status:'active',    hasVoted:false, password:'Passw0rd!24', dateRegistered:'2026-06-12'},

        {id:25, studentId:'2023-00501', fullName:'Cherry Domingo',        department:'CTE',  major:'BSEd — English',            section:'3A', yearLevel:'3rd Year', email:'cherry.domingo@cte.edu.ph',        status:'active',    hasVoted:false, password:'Passw0rd!25', dateRegistered:'2026-06-10'},
        {id:26, studentId:'2023-00502', fullName:'Paolo Rivera',          department:'CTE',  major:'BEEd — General Education',  section:'2A', yearLevel:'2nd Year', email:'paolo.rivera@cte.edu.ph',          status:'active',    hasVoted:false, password:'Passw0rd!26', dateRegistered:'2026-06-10'},
        {id:27, studentId:'2023-00503', fullName:'Angeline Custodio',     department:'CTE',  major:'BSEd — Mathematics',        section:'4A', yearLevel:'4th Year', email:'angeline.custodio@cte.edu.ph',     status:'active',    hasVoted:true,  password:'Passw0rd!27', dateRegistered:'2026-06-11'},
        {id:28, studentId:'2023-00504', fullName:'Kristoffer Sales',      department:'CTE',  major:'BEEd — General Education',  section:'1A', yearLevel:'1st Year', email:'kristoffer.sales@cte.edu.ph',      status:'active',    hasVoted:false, password:'Passw0rd!28', dateRegistered:'2026-06-11'},
        {id:29, studentId:'2023-00505', fullName:'Divine Alcantara',      department:'CTE',  major:'BSEd — English',            section:'2B', yearLevel:'2nd Year', email:'divine.alcantara@cte.edu.ph',      status:'active',    hasVoted:false, password:'Passw0rd!29', dateRegistered:'2026-06-12'},
        {id:30, studentId:'2023-00506', fullName:'Emmanuel Roque',        department:'CTE',  major:'BSEd — Mathematics',        section:'3A', yearLevel:'3rd Year', email:'emmanuel.roque@cte.edu.ph',        status:'suspended', hasVoted:false, password:'Passw0rd!30', dateRegistered:'2026-06-12'},
    ],
    nextStudentId: 31,
    logs: [
        {time:'2026-07-02 09:00 PM', text:'Admin added a candidate: Angelica Marie Fajardo (Treasurer, DSG FICT).'},
        {time:'2026-07-02 08:12 PM', text:'Admin edited election schedule for Supreme Student Government.'},
        {time:'2026-07-01 08:45 AM', text:'John Alonsagay voted.'},
        {time:'2026-07-01 08:41 AM', text:'Maria Dolores Ferrer voted.'},
        {time:'2026-07-01 08:33 AM', text:'Kim Nathaniel Uy voted.'},
        {time:'2026-06-30 06:00 PM', text:'Admin started election: Supreme Student Government.'},
        {time:'2026-06-30 05:40 PM', text:'Admin created election: Department Student Government (FICT).'},
        {time:'2026-06-29 02:15 PM', text:'Admin added candidate: Maria Santos (SSG Governor).'},
    ],
    elections: [
        {
            id: 1, type: 'SSG', department: null,
            name: 'Supreme Student Government',
            status: 'ongoing',
            start: '2026-07-01T08:00', end: '2026-07-31T23:59',
            resultsVisibility: 'after',
            partiesEnabled: true,
            parties: ['Bagong Sibol Coalition', 'Bantay Kabataan Party'],
            positions: [
                {title:'SSG Governor', candidatesCount:2, winners:1, limit:'everyone', limitYear:''},
                {title:'DSG Governor', candidatesCount:2, winners:1, limit:'everyone', limitYear:''},
            ],
            candidates: [
                {position:'SSG Governor', name:'Maria Santos', party:'Bagong Sibol Coalition', course:'BS IT', year:'3rd Year', platform:'Improve campus Wi-Fi and student services.', photo:''},
                {position:'SSG Governor', name:'Juan Dela Cruz', party:'Bantay Kabataan Party', course:'BS CS', year:'4th Year', platform:'More student events and mental health awareness.', photo:''},
                {position:'DSG Governor', name:'Angela Reyes', party:'Bagong Sibol Coalition', course:'BS IS', year:'2nd Year', platform:'Better library resources.', photo:''},
                {position:'DSG Governor', name:'Mark Villanueva', party:'No Party / Independent', course:'BS ECE', year:'3rd Year', platform:'Sustainable campus initiatives.', photo:''},
            ],
        },
        {
            id: 2, type: 'DSG', department: 'FICT',
            name: 'Department Student Government — FICT',
            status: 'paused',
            start: '2026-07-05T08:00', end: '2026-07-15T17:00',
            resultsVisibility: 'never',
            partiesEnabled: false,
            parties: [],
            positions: [
                {title:'President', candidatesCount:2, winners:1, limit:'everyone', limitYear:''},
                {title:'Vice President', candidatesCount:2, winners:1, limit:'everyone', limitYear:''},
                {title:'Secretary', candidatesCount:2, winners:1, limit:'everyone', limitYear:''},
                {title:'Treasurer', candidatesCount:2, winners:1, limit:'limit', limitYear:'1st Year'},
            ],
            candidates: [
                {position:'President', name:'Josiah Bautista', party:'No Party / Independent', course:'BS IT', year:'4th Year', platform:'Stronger student-faculty dialogue.', photo:''},
                {position:'President', name:'Krystal Anne Panganiban', party:'No Party / Independent', course:'BS CS', year:'3rd Year', platform:'Transparent budget reporting.', photo:''},
                {position:'Vice President', name:'Miguel Dela Pena', party:'No Party / Independent', course:'BS IS', year:'3rd Year', platform:'More hands-on workshops.', photo:''},
                {position:'Vice President', name:'Samantha Reyes', party:'No Party / Independent', course:'BS IT', year:'2nd Year', platform:'Peer tutoring program.', photo:''},
                {position:'Secretary', name:'Adrian Villaruz', party:'No Party / Independent', course:'BS CS', year:'2nd Year', platform:'Digitize department records.', photo:''},
                {position:'Secretary', name:'Bea Francesca Solis', party:'No Party / Independent', course:'BS IT', year:'1st Year', platform:'Better communication channels.', photo:''},
                {position:'Treasurer', name:'Nathaniel Cordero', party:'No Party / Independent', course:'BS IS', year:'1st Year', platform:'Fair fund allocation.', photo:''},
                {position:'Treasurer', name:'Angelica Marie Fajardo', party:'No Party / Independent', course:'BS ECE', year:'1st Year', platform:'Fundraising for department events.', photo:''},
            ],
        },
    ],
    nextElectionId: 3,
};

const departmentOptions = ['FICT','CBA','CTHM','CAS','CTE'];
const yearOptions = ['1st Year','2nd Year','3rd Year','4th Year'];
const majorsByDept = {
    FICT: ['BS Information Technology','BS Computer Science','BS Information Systems'],
    CBA:  ['BS Business Administration','BS Accountancy','BS Entrepreneurship'],
    CTHM: ['BS Hospitality Management','BS Tourism Management'],
    CAS:  ['BS Psychology','AB Communication','BS Biology'],
    CTE:  ['BEEd — General Education','BSEd — English','BSEd — Mathematics'],
};

// Single source of truth for "how many students / how many voted" per
// department — both the Dashboard panel and election turnout math read
// from this instead of a separately-maintained number.
function departmentStats(){
    const map = {};
    departmentOptions.forEach(d => { map[d] = {name:d, total:0, voted:0}; });
    state.students.forEach(s => {
        if (!map[s.department]) map[s.department] = {name:s.department, total:0, voted:0};
        map[s.department].total++;
        if (s.hasVoted) map[s.department].voted++;
    });
    return Object.values(map);
}

/* =========================================================================
   SIDEBAR NAV / RESPONSIVE
   ========================================================================= */
const panelTitles = {dashboard:'Dashboard', election:'Election Management', students:'Students', results:'Live Results', archive:'Archive', logs:'Activity Logs'};

function showPanel(name){
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + name).classList.add('active');
    document.querySelectorAll('.nav-link').forEach(a => a.classList.remove('active'));
    document.querySelector('.nav-link[data-panel="' + name + '"]').classList.add('active');
    document.getElementById('pageHeaderTitle').textContent = panelTitles[name] || 'Dashboard';
    if (name === 'dashboard') renderDashboard();
    if (name === 'election') renderElectionList();
    if (name === 'students') renderStudentList();
    if (name === 'results') renderResultsPanel();
    if (name === 'logs') renderLogsPanel();
    toggleSidebar(false);
    window.scrollTo(0,0);
}

const hamburger = document.getElementById('hamburgerBtn');
const sidebarEl = document.getElementById('sidebar');
const overlayEl = document.getElementById('sidebarOverlay');
function toggleSidebar(open){
    if (open === undefined) open = !sidebarEl.classList.contains('open');
    sidebarEl.classList.toggle('open', open);
    overlayEl.classList.toggle('active', open);
    document.body.style.overflow = open ? 'hidden' : '';
}
hamburger.addEventListener('click', () => toggleSidebar());
overlayEl.addEventListener('click', () => toggleSidebar(false));
window.addEventListener('resize', () => { if (window.innerWidth > 768) toggleSidebar(false); });

document.getElementById('pageHeaderDate').textContent = new Date().toLocaleDateString('en-US', {weekday:'long', year:'numeric', month:'long', day:'numeric'});

/* =========================================================================
   HELPERS
   ========================================================================= */
function escapeHtml(str){
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}
function fmtDateTime(iso){
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleString('en-US', {month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit'});
}
function statusLabel(s){
    return {ongoing:'Ongoing', paused:'Paused', scheduled:'Not Started', closed:'Closed'}[s] || s;
}
function statusClass(s){
    return {ongoing:'', paused:'paused', scheduled:'scheduled', closed:'closed'}[s] || '';
}
function addLog(text){
    const now = new Date();
    const time = now.toLocaleString('en-US', {month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit'});
    state.logs.unshift({time, text});
}
function electionTurnout(election){
    const departments = departmentStats();
    let voters = 0;
    if (election.type === 'SSG') {
        voters = departments.reduce((a,d) => a + d.total, 0);
    } else {
        const dep = departments.find(d => d.name === election.department);
        voters = dep ? dep.total : 0;
    }
    const votesCast = Math.round(voters * (election.status === 'ongoing' ? 0.42 : election.status === 'closed' ? 0.81 : 0.0));
    return {voters, votesCast};
}
function resultsVisibleToStudents(election){
    if (election.resultsVisibility === 'always') return true;
    if (election.resultsVisibility === 'never') return false;
    return election.status === 'closed'; // 'after'
}

/* =========================================================================
   DASHBOARD PANEL
   ========================================================================= */
function renderDashboard(){
    const departments = departmentStats();
    const totalStudents = departments.reduce((a,d)=>a+d.total,0);
    const totalVoted = departments.reduce((a,d)=>a+d.voted,0);
    const totalNotVoted = totalStudents - totalVoted;
    const overallPct = totalStudents ? Math.round((totalVoted/totalStudents)*100) : 0;
    const activeElections = state.elections.filter(e => e.status === 'ongoing').length;

    const deptRows = departments.map(d => {
        const pct = d.total ? Math.round((d.voted/d.total)*100) : 0;
        return `
        <div class="dept-row">
            <div class="dept-name">${escapeHtml(d.name)}</div>
            <div class="dept-bar">
                <div class="progress-bar-track"><div class="progress-bar-fill" style="width:${pct}%;"></div></div>
            </div>
            <div class="dept-nums">${d.voted}/${d.total} voted (${pct}%)</div>
        </div>`;
    }).join('');

    // Donut chart via SVG stroke-dasharray
    const r = 54, circ = 2 * Math.PI * r;
    const votedLen = circ * (overallPct/100);
    const donut = `
    <svg width="150" height="150" viewBox="0 0 150 150">
        <circle cx="75" cy="75" r="${r}" fill="none" stroke="#e2e8f0" stroke-width="16"></circle>
        <circle cx="75" cy="75" r="${r}" fill="none" stroke="#84cc16" stroke-width="16"
            stroke-dasharray="${votedLen} ${circ - votedLen}" stroke-linecap="round"
            transform="rotate(-90 75 75)"></circle>
        <text x="75" y="70" text-anchor="middle" font-size="26" font-weight="700" fill="#0f172a">${overallPct}%</text>
        <text x="75" y="90" text-anchor="middle" font-size="11" fill="#64748b">turnout</text>
    </svg>`;

    const best = [...departments].sort((a,b)=> (b.voted/b.total) - (a.voted/a.total))[0];
    const worst = [...departments].sort((a,b)=> (a.voted/a.total) - (b.voted/b.total))[0];
    const bestPct = Math.round((best.voted/best.total)*100);
    const worstPct = Math.round((worst.voted/worst.total)*100);

    const logsPreview = state.logs.slice(0,5).map(l => `
        <div class="log-item"><span class="log-time">${escapeHtml(l.time)}</span><span class="log-text">${escapeHtml(l.text)}</span></div>
    `).join('');

    const resultsPreview = state.elections.map(e => `
        <div class="results-mini">
            <div class="rm-head">
                <span class="rm-title">${escapeHtml(e.name)}</span>
                <span class="status-large ${statusClass(e.status)}"><span class="status-dot"></span>${statusLabel(e.status)}</span>
            </div>
            <div class="results-visibility-row">
                <span class="vis-label">Student visibility:</span>
                ${visibilityButtons(e.id, e.resultsVisibility)}
            </div>
        </div>
    `).join('');

    document.getElementById('panel-dashboard').innerHTML = `
        <div class="stats-bar">
            <div class="stat-box"><div class="stat-number">${totalStudents}</div><div class="stat-label">Total Students</div></div>
            <div class="stat-box"><div class="stat-number">${totalVoted}</div><div class="stat-label">Total Voted</div></div>
            <div class="stat-box"><div class="stat-number">${totalNotVoted}</div><div class="stat-label">Not Yet Voted</div></div>
            <div class="stat-box"><div class="stat-number">${activeElections}</div><div class="stat-label">Active Elections</div></div>
        </div>

        <div class="grid-2">
            <div class="card">
                <h2 class="section-title">Turnout by Department</h2>
                ${deptRows}
            </div>
            <div class="card">
                <h2 class="section-title">Overall Turnout</h2>
                <div class="donut-wrap">
                    ${donut}
                    <div class="donut-legend">
                        <div class="lg-item"><span class="dot" style="background:#84cc16;"></span> Voted — ${totalVoted}</div>
                        <div class="lg-item"><span class="dot" style="background:#e2e8f0;"></span> Not voted — ${totalNotVoted}</div>
                    </div>
                </div>
                <h3 style="font-size:.95rem;margin:1.5rem 0 .5rem;">Insights</h3>
                <ul class="insight-list">
                    <li><strong>${escapeHtml(best.name)}</strong> has the highest turnout at ${bestPct}%.</li>
                    <li><strong>${escapeHtml(worst.name)}</strong> has the lowest turnout at ${worstPct}% — consider a reminder push.</li>
                    <li>${totalNotVoted} students (${100-overallPct}%) have not voted yet across all departments.</li>
                </ul>
            </div>
        </div>

        <div class="grid-2" style="margin-top:1.5rem;">
            <div class="card">
                <div class="panel-flex-header">
                    <h2 class="section-title" style="margin:0;">Recent Activity</h2>
                    <button class="btn btn-secondary btn-sm" onclick="showPanel('logs')">View All Logs</button>
                </div>
                ${logsPreview}
            </div>
            <div class="card">
                <div class="panel-flex-header">
                    <h2 class="section-title" style="margin:0;">Results Visibility</h2>
                    <button class="btn btn-secondary btn-sm" onclick="showPanel('results')">Open Results</button>
                </div>
                ${resultsPreview}
            </div>
        </div>
    `;
}

function visibilityButtons(electionId, current){
    const opts = [
        {key:'always', label:'Show Results'},
        {key:'after', label:'Only After Election'},
        {key:'never', label:'Never Show'},
    ];
    return `<div class="pill-toggle">` + opts.map(o =>
        `<button class="${o.key===current?'selected':''}" onclick="setVisibility(${electionId}, '${o.key}')">${o.label}</button>`
    ).join('') + `</div>`;
}
function setVisibility(id, vis){
    const e = state.elections.find(e => e.id === id);
    if (!e) return;
    e.resultsVisibility = vis;
    addLog(`Admin set results visibility for "${e.name}" to "${vis === 'always' ? 'Show Results' : vis === 'after' ? 'Only After Election' : 'Never Show'}".`);
    renderDashboard();
    renderResultsPanel();
}

/* =========================================================================
   ELECTION LIST PANEL
   ========================================================================= */
function renderElectionList(){
    const cards = state.elections.map(e => {
        const now = new Date();
        const end = new Date(e.end);
        let remaining = '';
        if (e.status === 'ongoing') {
            const diff = end - now;
            if (diff > 0) {
                const h = Math.floor(diff/3600000), m = Math.floor((diff%3600000)/60000), s = Math.floor((diff%60000)/1000);
                remaining = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
            } else { remaining = '00:00:00'; }
        }
        const {voters, votesCast} = electionTurnout(e);
        const pct = voters ? Math.round((votesCast/voters)*100) : 0;
        const schedEditable = e.status !== 'ongoing';

        return `
        <div class="election-card">
            <div class="card-top-row">
                <span class="status-large ${statusClass(e.status)}"><span class="status-dot"></span>${statusLabel(e.status)}</span>
                ${e.status === 'ongoing'
                    ? `<div class="remaining-time"><strong>Ends in:</strong> <span>${remaining}</span></div>`
                    : `<div class="remaining-time">${e.status === 'closed' ? 'This election has ended.' : 'Not currently running'}</div>`}
            </div>
            <div>
                <h2 class="election-title">${escapeHtml(e.name)}</h2>
                <div class="election-sub">${e.type === 'SSG' ? 'Supreme Student Government' : 'Department Student Government — ' + escapeHtml(e.department)}</div>
            </div>
            <div class="turnout-line">${votesCast} of ${voters} eligible voters have voted (${pct}%)</div>
            <div class="progress-bar-track"><div class="progress-bar-fill" style="width:${pct}%;"></div></div>

            <div class="schedule-box">
                <div class="schedule-field">
                    <label>Start</label>
                    <input type="datetime-local" id="start_${e.id}" value="${e.start}" ${schedEditable ? '' : 'disabled'}>
                </div>
                <div class="schedule-field">
                    <label>End</label>
                    <input type="datetime-local" id="end_${e.id}" value="${e.end}" ${schedEditable ? '' : 'disabled'}>
                </div>
                ${schedEditable ? `<button class="btn btn-secondary btn-sm" onclick="saveSchedule(${e.id})">Save Schedule</button>` : ''}
                <div class="schedule-actions">
                    ${e.status === 'scheduled' || e.status === 'paused'
                        ? `<button class="btn btn-primary btn-sm" onclick="startElection(${e.id})">Start</button>` : ''}
                    ${e.status === 'ongoing'
                        ? `<button class="btn btn-secondary btn-sm" onclick="pauseElection(${e.id})">Pause</button>` : ''}
                    ${(e.status === 'ongoing' || e.status === 'paused')
                        ? `<button class="btn btn-danger btn-sm" onclick="endElection(${e.id})">End</button>` : ''}
                </div>
            </div>

            <div class="card-actions">
                <button class="btn btn-secondary" onclick="viewCandidates(${e.id})">View Candidates</button>
                <button class="btn btn-secondary" onclick="editElection(${e.id})">Edit</button>
                <button class="btn btn-navy" onclick="showPanel('results')">View Results</button>
            </div>
        </div>`;
    }).join('');

    document.getElementById('panel-election').innerHTML = `
        <div id="electionListView">
            <div class="panel-flex-header">
                <h2 class="section-title" style="margin:0;">Current & Upcoming Elections</h2>
                <button class="btn btn-primary" onclick="openCreateElection()">+ Create New Election</button>
            </div>
            <div class="election-cards">${cards || '<div class="coming-soon">No elections yet. Create one to get started.</div>'}</div>
        </div>
        <div id="electionWizardView" style="display:none;"></div>
    `;
}

function saveSchedule(id){
    const e = state.elections.find(e => e.id === id);
    const start = document.getElementById('start_'+id).value;
    const end = document.getElementById('end_'+id).value;
    if (!start || !end) { alert('Please set both a start and end date/time.'); return; }
    if (new Date(end) <= new Date(start)) { alert('End time must be after the start time.'); return; }
    e.start = start; e.end = end;
    addLog(`Admin updated the schedule for "${e.name}" (${fmtDateTime(start)} → ${fmtDateTime(end)}).`);
    renderElectionList();
}
function startElection(id){
    const e = state.elections.find(e => e.id === id);
    e.status = 'ongoing';
    addLog(`Admin started election: ${e.name}.`);
    renderElectionList();
}
function pauseElection(id){
    const e = state.elections.find(e => e.id === id);
    e.status = 'paused';
    addLog(`Admin paused election: ${e.name}.`);
    renderElectionList();
}
function endElection(id){
    const e = state.elections.find(e => e.id === id);
    if (!confirm(`End "${e.name}" now? Voting will be closed immediately.`)) return;
    e.status = 'closed';
    addLog(`Admin ended election: ${e.name}.`);
    renderElectionList();
}
function viewCandidates(id){
    showPanel('results');
}

/* =========================================================================
   CREATE / EDIT ELECTION WIZARD
   ========================================================================= */
let wizard = null; // holds in-progress election draft + UI state

function blankDraft(){
    return {
        id: null, type: null, department: '', name: '',
        status: 'scheduled', start: '', end: '', resultsVisibility: 'after',
        partiesEnabled: false, partyCount: 2, parties: [],
        positionCount: 1, positions: [],
        candidates: [],
        step: 1, // 1 = type+setup, 2 = candidates
    };
}

function openCreateElection(){
    wizard = blankDraft();
    document.getElementById('electionListView').style.display = 'none';
    const w = document.getElementById('electionWizardView');
    w.style.display = 'block';
    renderWizardStep1();
}

function editElection(id){
    const e = state.elections.find(e => e.id === id);
    wizard = JSON.parse(JSON.stringify(e));
    wizard.partyCount = wizard.parties.length || 2;
    wizard.positionCount = wizard.positions.length || 1;
    wizard.step = 1;
    document.getElementById('electionListView').style.display = 'none';
    const w = document.getElementById('electionWizardView');
    w.style.display = 'block';
    renderWizardStep1();
}

function cancelWizard(){
    wizard = null;
    document.getElementById('electionListView').style.display = 'block';
    document.getElementById('electionWizardView').style.display = 'none';
    renderElectionList();
}

function wizardTopbar(title, showBack){
    return `
    <div class="wizard-topbar">
        ${showBack ? `<button class="back-btn" onclick="renderWizardStep1()">&larr; Back</button>` : ''}
        <h2 class="section-title" style="margin:0;flex:1;">${title}</h2>
        <button class="btn btn-secondary btn-sm" onclick="cancelWizard()">Cancel</button>
    </div>`;
}

function renderWizardStep1(){
    wizard.step = 1;
    const w = document.getElementById('electionWizardView');

    const typeChoiceHtml = `
        <div class="type-choice-row">
            <div class="type-choice ${wizard.type==='DSG'?'selected':''}" onclick="selectType('DSG')">
                <div class="tc-icon">🏛</div>
                <h3>Department Student Government</h3>
                <p>Positions and candidates scoped to a single department.</p>
            </div>
            <div class="type-choice ${wizard.type==='SSG'?'selected':''}" onclick="selectType('SSG')">
                <div class="tc-icon">🎓</div>
                <h3>Supreme Student Government</h3>
                <p>School-wide positions open to all departments.</p>
            </div>
        </div>`;

    let setupHtml = '';
    if (wizard.type) setupHtml = renderSetupSection();

    w.innerHTML = wizardTopbar((wizard.id ? 'Edit Election' : 'Create New Election'), false) +
        `<div id="wizardError"></div>` +
        typeChoiceHtml +
        `<div id="setupSection">${setupHtml}</div>`;
}

function selectType(type){
    wizard.type = type;
    if (type === 'SSG') wizard.department = '';
    renderWizardStep1();
}

function renderSetupSection(){
    const deptField = wizard.type === 'DSG' ? `
        <div class="form-row">
            <label>Department Name</label>
            <select id="w_department" onchange="wizard.department=this.value">
                <option value="">Select department</option>
                ${departmentOptions.map(d => `<option value="${d}" ${wizard.department===d?'selected':''}>${d}</option>`).join('')}
            </select>
        </div>` : '';

    const electionName = wizard.type === 'SSG' ? 'Supreme Student Government' :
        (wizard.department ? `Department Student Government — ${wizard.department}` : 'Department Student Government');

    return `
    <div class="wizard-section">
        <h3>Basic Info</h3>
        ${deptField}
        <div class="form-row">
            <label>Election Name</label>
            <input type="text" id="w_name" value="${escapeHtml(wizard.name || electionName)}">
        </div>
    </div>

    <div class="wizard-section">
        <h3>Parties</h3>
        <div class="toggle-switch ${wizard.partiesEnabled ? 'on' : ''}" onclick="togglePartiesEnabled()">
            <div class="track"></div>
            <span>${wizard.partiesEnabled ? 'Parties enabled' : 'Parties disabled (independent candidates only)'}</span>
        </div>
        ${wizard.partiesEnabled ? `
        <div class="form-row" style="max-width:220px;margin-top:1rem;">
            <label>Number of Parties</label>
            <input type="number" min="1" max="10" value="${wizard.partyCount}" onchange="setPartyCount(this.value)">
        </div>
        <div class="party-input-list" id="partyInputs">
            ${renderPartyInputs()}
        </div>` : ''}
    </div>

    <div class="wizard-section">
        <h3>Positions</h3>
        <div class="form-row" style="max-width:220px;">
            <label>Number of Positions</label>
            <input type="number" min="1" max="20" value="${wizard.positionCount}" onchange="setPositionCount(this.value)">
        </div>
        <table class="pos-table" id="positionsTable">
            <thead><tr><th>Position</th><th style="width:130px;">Candidates</th><th style="width:110px;">Winners</th><th style="width:170px;">Limit to see</th></tr></thead>
            <tbody>${renderPositionRows()}</tbody>
        </table>
    </div>

    <div class="wizard-footer">
        <div></div>
        <button class="btn btn-primary" onclick="goToCandidates()">Next: Candidates &rarr;</button>
    </div>`;
}

function togglePartiesEnabled(){
    wizard.partiesEnabled = !wizard.partiesEnabled;
    if (wizard.partiesEnabled && wizard.parties.length === 0) {
        wizard.parties = Array(wizard.partyCount).fill('');
    }
    document.getElementById('setupSection').innerHTML = renderSetupSection();
}
function setPartyCount(n){
    n = Math.max(1, Math.min(10, parseInt(n) || 1));
    wizard.partyCount = n;
    const existing = wizard.parties;
    wizard.parties = Array.from({length:n}, (_,i) => existing[i] || '');
    document.getElementById('partyInputs').innerHTML = renderPartyInputs();
}
function renderPartyInputs(){
    return wizard.parties.map((p,i) => `
        <input type="text" placeholder="Party ${i+1} name" value="${escapeHtml(p)}" onchange="wizard.parties[${i}]=this.value">
    `).join('');
}
function setPositionCount(n){
    n = Math.max(1, Math.min(20, parseInt(n) || 1));
    wizard.positionCount = n;
    const existing = wizard.positions;
    wizard.positions = Array.from({length:n}, (_,i) => existing[i] || {title:'', candidatesCount:2, winners:1, limit:'everyone', limitYear:''});
    document.getElementById('positionsTable').querySelector('tbody').innerHTML = renderPositionRows();
}
function renderPositionRows(){
    if (wizard.positions.length === 0) {
        wizard.positions = Array.from({length:wizard.positionCount}, () => ({title:'', candidatesCount:2, winners:1, limit:'everyone', limitYear:''}));
    }
    return wizard.positions.map((p,i) => `
        <tr>
            <td><input type="text" placeholder="e.g. President" value="${escapeHtml(p.title)}" onchange="wizard.positions[${i}].title=this.value"></td>
            <td><input type="number" min="1" value="${p.candidatesCount}" onchange="wizard.positions[${i}].candidatesCount=parseInt(this.value)||1"></td>
            <td><input type="number" min="1" value="${p.winners}" onchange="wizard.positions[${i}].winners=parseInt(this.value)||1"></td>
            <td>
                <select onchange="wizard.positions[${i}].limit=this.value; document.getElementById('yearPickerCell${i}').style.display=this.value==='limit'?'block':'none';">
                    <option value="everyone" ${p.limit==='everyone'?'selected':''}>Everyone</option>
                    <option value="limit" ${p.limit==='limit'?'selected':''}>Limited to year level</option>
                </select>
                <select id="yearPickerCell${i}" style="margin-top:.4rem;display:${p.limit==='limit'?'block':'none'};" onchange="wizard.positions[${i}].limitYear=this.value">
                    ${yearOptions.map(y => `<option value="${y}" ${p.limitYear===y?'selected':''}>${y}</option>`).join('')}
                </select>
            </td>
        </tr>
    `).join('');
}

function goToCandidates(){
    // Sync top-level fields
    wizard.name = document.getElementById('w_name') ? document.getElementById('w_name').value.trim() : wizard.name;
    if (wizard.type === 'DSG') {
        const depSel = document.getElementById('w_department');
        if (depSel) wizard.department = depSel.value;
    }

    // Validation
    const errors = [];
    if (!wizard.type) errors.push('Choose an election type.');
    if (wizard.type === 'DSG' && !wizard.department) errors.push('Select a department.');
    if (!wizard.name) errors.push('Enter an election name.');
    if (wizard.partiesEnabled && wizard.parties.some(p => !p.trim())) errors.push('Fill in all party names, or disable parties.');
    wizard.positions.forEach((p,i) => {
        if (!p.title.trim()) errors.push(`Position #${i+1} needs a name.`);
        if (!p.candidatesCount || p.candidatesCount < 1) errors.push(`Position "${p.title || '#'+(i+1)}" needs at least 1 candidate slot.`);
        if (!p.winners || p.winners < 1) errors.push(`Position "${p.title || '#'+(i+1)}" needs at least 1 winner.`);
        if (p.winners > p.candidatesCount) errors.push(`Position "${p.title || '#'+(i+1)}" can't have more winners than candidates.`);
        if (p.limit === 'limit' && !p.limitYear) errors.push(`Position "${p.title || '#'+(i+1)}" needs a year level selected.`);
    });

    if (errors.length) {
        document.getElementById('wizardError').innerHTML = `<div class="alert alert-error"><strong>Please fix the following:</strong><br>${errors.map(escapeHtml).join('<br>')}</div>`;
        window.scrollTo(0,0);
        return;
    }
    document.getElementById('wizardError').innerHTML = '';

    // Build/rebuild candidate slots, preserving already-entered data where possible
    const newCandidates = [];
    wizard.positions.forEach(p => {
        for (let i = 0; i < p.candidatesCount; i++) {
            const existing = wizard.candidates.find(c => c.position === p.title && c._slot === i);
            newCandidates.push(existing || {position:p.title, _slot:i, name:'', party: wizard.partiesEnabled ? '' : 'No Party / Independent', course:'', year:'1st Year', platform:'', photo:''});
        }
    });
    wizard.candidates = newCandidates;
    wizard.step = 2;
    renderWizardStep2();
}

function renderWizardStep2(){
    const grouped = {};
    wizard.candidates.forEach((c,i) => {
        if (!grouped[c.position]) grouped[c.position] = [];
        grouped[c.position].push({...c, _index:i});
    });

    const partyOptions = ['No Party / Independent', ...wizard.parties.filter(p => p.trim())];

    let html = '';
    Object.keys(grouped).forEach(pos => {
        html += `<div class="position-group-title">${escapeHtml(pos)}</div>`;
        grouped[pos].forEach(c => {
            html += `
            <div class="candidate-block">
                <h4>Candidate ${c._slot + 1}</h4>
                <div class="candidate-photo-row">
                    <img id="photoPreview${c._index}" src="${c.photo || ''}" onerror="this.style.opacity=0" style="opacity:${c.photo?1:0}">
                    <div>
                        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Candidate Photo</label>
                        <input type="file" accept="image/*" onchange="handlePhotoUpload(event, ${c._index})">
                    </div>
                </div>
                <div class="two-col">
                    <div class="form-row">
                        <label>Full Name</label>
                        <input type="text" value="${escapeHtml(c.name)}" onchange="wizard.candidates[${c._index}].name=this.value">
                    </div>
                    <div class="form-row">
                        <label>Party</label>
                        ${wizard.partiesEnabled
                            ? `<select onchange="wizard.candidates[${c._index}].party=this.value">
                                ${partyOptions.map(p => `<option value="${escapeHtml(p)}" ${c.party===p?'selected':''}>${escapeHtml(p)}</option>`).join('')}
                               </select>`
                            : `<input type="text" value="No Party / Independent" disabled>`}
                    </div>
                    <div class="form-row">
                        <label>Course &amp; Major</label>
                        <input type="text" placeholder="e.g. BS IT — Network Security" value="${escapeHtml(c.course)}" onchange="wizard.candidates[${c._index}].course=this.value">
                    </div>
                    <div class="form-row">
                        <label>Year Level</label>
                        <select onchange="wizard.candidates[${c._index}].year=this.value">
                            ${yearOptions.map(y => `<option value="${y}" ${c.year===y?'selected':''}>${y}</option>`).join('')}
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label>Platform</label>
                    <textarea onchange="wizard.candidates[${c._index}].platform=this.value">${escapeHtml(c.platform)}</textarea>
                </div>
            </div>`;
        });
    });

    const w = document.getElementById('electionWizardView');
    w.innerHTML = wizardTopbar('Add Candidates', true) +
        `<div id="wizardError"></div>` +
        `<div class="wizard-section">${html}</div>` +
        `<div class="wizard-footer">
            <div></div>
            <button class="btn btn-primary" onclick="finalizeElection()">${wizard.id ? 'Save Changes' : 'Create Election'}</button>
        </div>`;
}

function handlePhotoUpload(evt, index){
    const file = evt.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        wizard.candidates[index].photo = e.target.result;
        const img = document.getElementById('photoPreview' + index);
        img.src = e.target.result;
        img.style.opacity = 1;
    };
    reader.readAsDataURL(file);
}

function finalizeElection(){
    const errors = [];
    wizard.candidates.forEach((c,i) => {
        if (!c.name.trim()) errors.push(`Candidate ${i+1} (${c.position}) needs a name.`);
    });
    if (errors.length) {
        document.getElementById('wizardError').innerHTML = `<div class="alert alert-error"><strong>Please fix the following:</strong><br>${errors.map(escapeHtml).join('<br>')}</div>`;
        window.scrollTo(0,0);
        return;
    }

    wizard.candidates.forEach(c => delete c._slot);
    wizard.candidates.forEach(c => delete c._index);

    if (wizard.id) {
        const idx = state.elections.findIndex(e => e.id === wizard.id);
        const {step, positionCount, partyCount, ...clean} = wizard;
        state.elections[idx] = clean;
        addLog(`Admin edited election: ${wizard.name}.`);
    } else {
        wizard.id = state.nextElectionId++;
        wizard.status = 'scheduled';
        const {step, positionCount, partyCount, ...clean} = wizard;
        state.elections.push(clean);
        addLog(`Admin created a new election: ${wizard.name}.`);
    }

    wizard = null;
    document.getElementById('electionListView').style.display = 'block';
    document.getElementById('electionWizardView').style.display = 'none';
    renderElectionList();
}

/* =========================================================================
   STUDENTS PANEL
   ------------------------------------------------------------------------
   ON PASSWORDS: the request behind this panel was "retrievable, but
   hashed" — those two are mutually exclusive with real hashing (bcrypt/
   Argon2 is one-way by design; that's the whole point of it, and it's
   what users/password.php already uses correctly for the admin login).
   For a password an admin needs to look back up and read, the DB column
   has to hold something the server can *decrypt*, not just verify — e.g.
   AES-256-GCM with the key kept in an env var, not in the repo/DB. That's
   real protection against someone reading the database directly, but it
   is weaker than true hashing, since anyone with the app's encryption
   key (i.e. anyone who compromises the server, not just the DB) can
   recover every password. Worth deciding deliberately rather than by
   default — a "Reset Password" flow (admin sets a new one, old one is
   simply gone) avoids this trade-off entirely and is the more common
   pattern for exactly this reason. Said as much in chat; for now this
   prototype just keeps the value in plain, readable form in `state`
   since it isn't touching a real database yet.
   ========================================================================= */
let studentFilters = { search:'', department:'', year:'', voter:'', account:'' };
let studentDraft = null;

function blankStudentDraft(){
    return {
        id:null, studentId:'', fullName:'', department:'', major:'', section:'',
        yearLevel:'1st Year', email:'', status:'active', hasVoted:false,
        password:'', dateRegistered:'',
    };
}

function renderStudentList(){
    const activeElId = document.activeElement ? document.activeElement.id : null;
    const caret = (activeElId === 'stuSearch') ? document.activeElement.selectionStart : null;

    const total = state.students.length;
    const voted = state.students.filter(s => s.hasVoted).length;
    const notVoted = total - voted;
    const activeAccounts = state.students.filter(s => s.status === 'active').length;

    const filtered = state.students.filter(s => {
        if (studentFilters.search) {
            const q = studentFilters.search.toLowerCase();
            if (!s.fullName.toLowerCase().includes(q) && !s.studentId.toLowerCase().includes(q)) return false;
        }
        if (studentFilters.department && s.department !== studentFilters.department) return false;
        if (studentFilters.year && s.yearLevel !== studentFilters.year) return false;
        if (studentFilters.voter === 'voted' && !s.hasVoted) return false;
        if (studentFilters.voter === 'not-voted' && s.hasVoted) return false;
        if (studentFilters.account && s.status !== studentFilters.account) return false;
        return true;
    }).sort((a,b) => a.fullName.localeCompare(b.fullName));

    const rows = filtered.map(s => `
        <tr>
            <td>${escapeHtml(s.studentId)}</td>
            <td class="name-cell">${escapeHtml(s.fullName)}<div class="sub">${escapeHtml(s.email || '—')}</div></td>
            <td>${escapeHtml(s.department)}</td>
            <td>${escapeHtml(s.major)}</td>
            <td>${escapeHtml(s.section)}</td>
            <td>${escapeHtml(s.yearLevel)}</td>
            <td><span class="status-pill ${s.hasVoted ? 'voted' : 'not-voted'}">${s.hasVoted ? '✔ Voted' : 'Not Voted'}</span></td>
            <td><span class="status-pill ${s.status}">${s.status === 'active' ? 'Active' : 'Suspended'}</span></td>
            <td>
                <div class="row-actions">
                    <button title="Edit" onclick="editStudent(${s.id})">✎</button>
                    <button title="Delete" class="danger" onclick="deleteStudent(${s.id})">🗑</button>
                </div>
            </td>
        </tr>
    `).join('');

    document.getElementById('panel-students').innerHTML = `
        <div id="studentListView">
            <div class="stats-bar">
                <div class="stat-box"><div class="stat-number">${total}</div><div class="stat-label">Total Students</div></div>
                <div class="stat-box"><div class="stat-number">${voted}</div><div class="stat-label">Voted</div></div>
                <div class="stat-box"><div class="stat-number">${notVoted}</div><div class="stat-label">Not Yet Voted</div></div>
                <div class="stat-box"><div class="stat-number">${activeAccounts}</div><div class="stat-label">Active Accounts</div></div>
            </div>

            <div class="panel-flex-header">
                <h2 class="section-title" style="margin:0;">Registered Students</h2>
                <button class="btn btn-primary" onclick="openRegisterStudent()">+ Register Student</button>
            </div>

            <div class="students-toolbar">
                <input type="text" id="stuSearch" placeholder="Search by name or Student ID..." value="${escapeHtml(studentFilters.search)}" oninput="studentFilters.search=this.value; renderStudentList();">
                <select onchange="studentFilters.department=this.value; renderStudentList();">
                    <option value="">All Departments</option>
                    ${departmentOptions.map(d => `<option value="${d}" ${studentFilters.department===d?'selected':''}>${d}</option>`).join('')}
                </select>
                <select onchange="studentFilters.year=this.value; renderStudentList();">
                    <option value="">All Year Levels</option>
                    ${yearOptions.map(y => `<option value="${y}" ${studentFilters.year===y?'selected':''}>${y}</option>`).join('')}
                </select>
                <select onchange="studentFilters.voter=this.value; renderStudentList();">
                    <option value="">Voter Status: All</option>
                    <option value="voted" ${studentFilters.voter==='voted'?'selected':''}>Voted</option>
                    <option value="not-voted" ${studentFilters.voter==='not-voted'?'selected':''}>Not Voted</option>
                </select>
                <select onchange="studentFilters.account=this.value; renderStudentList();">
                    <option value="">Account: All</option>
                    <option value="active" ${studentFilters.account==='active'?'selected':''}>Active</option>
                    <option value="suspended" ${studentFilters.account==='suspended'?'selected':''}>Suspended</option>
                </select>
            </div>

            <div class="students-table-wrap">
                <table class="students-table">
                    <thead><tr>
                        <th>Student ID</th><th>Full Name</th><th>Department</th><th>Major</th><th>Section</th><th>Year</th><th>Voter Status</th><th>Account</th><th></th>
                    </tr></thead>
                    <tbody>${rows || '<tr class="empty-row"><td colspan="9">No students match your filters.</td></tr>'}</tbody>
                </table>
            </div>
        </div>
        <div id="studentFormView" style="display:none;"></div>
    `;

    if (activeElId === 'stuSearch') {
        const el = document.getElementById('stuSearch');
        if (el) { el.focus(); el.setSelectionRange(caret, caret); }
    }
}

function openRegisterStudent(){
    studentDraft = blankStudentDraft();
    document.getElementById('studentListView').style.display = 'none';
    document.getElementById('studentFormView').style.display = 'block';
    renderStudentForm();
}

function editStudent(id){
    const s = state.students.find(s => s.id === id);
    if (!s) return;
    studentDraft = {...s};
    document.getElementById('studentListView').style.display = 'none';
    document.getElementById('studentFormView').style.display = 'block';
    renderStudentForm();
}

function cancelStudentForm(){
    studentDraft = null;
    document.getElementById('studentListView').style.display = 'block';
    document.getElementById('studentFormView').style.display = 'none';
    renderStudentList();
}

function renderStudentForm(){
    const isEdit = !!studentDraft.id;
    const majors = majorsByDept[studentDraft.department] || [];

    document.getElementById('studentFormView').innerHTML = `
    <div class="wizard-topbar">
        <button class="back-btn" onclick="cancelStudentForm()">&larr; Back</button>
        <h2 class="section-title" style="margin:0;flex:1;">${isEdit ? 'Edit Student' : 'Register Student'}</h2>
        <button class="btn btn-secondary btn-sm" onclick="cancelStudentForm()">Cancel</button>
    </div>
    <div id="studentFormError"></div>

    <div class="wizard-section">
        <h3>Student Info</h3>
        <div class="two-col">
            <div class="form-row">
                <label>Student ID</label>
                <input type="text" id="s_studentId" placeholder="e.g. 2026-00123" value="${escapeHtml(studentDraft.studentId)}">
            </div>
            <div class="form-row">
                <label>Full Name</label>
                <input type="text" id="s_fullName" value="${escapeHtml(studentDraft.fullName)}">
            </div>
            <div class="form-row">
                <label>Department</label>
                <select id="s_department" onchange="studentDraft.department=this.value; studentDraft.major=''; renderStudentForm();">
                    <option value="">Select department</option>
                    ${departmentOptions.map(d => `<option value="${d}" ${studentDraft.department===d?'selected':''}>${d}</option>`).join('')}
                </select>
            </div>
            <div class="form-row">
                <label>Major</label>
                <select id="s_major" ${!studentDraft.department ? 'disabled' : ''}>
                    <option value="">${studentDraft.department ? 'Select major' : 'Select a department first'}</option>
                    ${majors.map(m => `<option value="${escapeHtml(m)}" ${studentDraft.major===m?'selected':''}>${escapeHtml(m)}</option>`).join('')}
                </select>
            </div>
            <div class="form-row">
                <label>Section</label>
                <input type="text" id="s_section" placeholder="e.g. 3A" value="${escapeHtml(studentDraft.section)}">
            </div>
            <div class="form-row">
                <label>Year Level</label>
                <select id="s_year">
                    ${yearOptions.map(y => `<option value="${y}" ${studentDraft.yearLevel===y?'selected':''}>${y}</option>`).join('')}
                </select>
            </div>
            <div class="form-row">
                <label>Email <span class="muted" style="font-weight:400;">(optional)</span></label>
                <input type="text" id="s_email" placeholder="name@school.edu.ph" value="${escapeHtml(studentDraft.email)}">
            </div>
            <div class="form-row">
                <label>Account Status</label>
                <div class="toggle-switch ${studentDraft.status==='active'?'on':''}" onclick="studentDraft.status = studentDraft.status==='active' ? 'suspended' : 'active'; renderStudentForm();">
                    <div class="track"></div>
                    <span>${studentDraft.status==='active' ? 'Active — can log in and vote' : 'Suspended — login blocked'}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="wizard-section">
        <h3>Password</h3>
        <div class="form-row" style="max-width:340px;">
            <label>${isEdit ? 'Password (visible to admins)' : 'Set Password'}</label>
            <div class="pw-field">
                <input type="password" id="s_password" value="${escapeHtml(studentDraft.password)}">
                <button type="button" class="pw-toggle-btn" onclick="toggleStudentPassword(this)" aria-label="Show password">
                    <svg class="pw-icon-on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <svg class="pw-icon-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 11 7 11 7a20.3 20.3 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                </button>
            </div>
            <p class="pw-hint">Students can't change or reset this themselves — admins set and look it up here.${isEdit ? ' Editing this immediately changes their login password.' : ''}</p>
        </div>
    </div>

    ${isEdit ? `
    <div class="wizard-section">
        <h3>Voting Status</h3>
        <div class="results-visibility-row">
            <span class="status-pill ${studentDraft.hasVoted?'voted':'not-voted'}">${studentDraft.hasVoted ? '✔ Has voted' : 'Has not voted'}</span>
            ${studentDraft.hasVoted ? `<button class="btn btn-danger btn-sm" onclick="resetVoteStatus()">Reset to Not Voted</button>` : ''}
        </div>
        <p class="pw-hint">Use this only to correct a mistake — it doesn't remove any vote already recorded for an election.</p>
    </div>` : ''}

    <div class="wizard-footer">
        <div></div>
        <button class="btn btn-primary" onclick="saveStudent()">${isEdit ? 'Save Changes' : 'Register Student'}</button>
    </div>
    `;
}

function toggleStudentPassword(btn){
    const input = document.getElementById('s_password');
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.classList.toggle('is-visible', isHidden);
}

function resetVoteStatus(){
    if (!confirm('Reset this student to "Not Voted"? This does not delete any vote records.')) return;
    studentDraft.hasVoted = false;
    addLog(`Admin manually reset voting status for ${studentDraft.fullName} (${studentDraft.studentId}).`);
    renderStudentForm();
}

function saveStudent(){
    const studentId = document.getElementById('s_studentId').value.trim();
    const fullName = document.getElementById('s_fullName').value.trim();
    const department = document.getElementById('s_department').value;
    const major = document.getElementById('s_major').value;
    const section = document.getElementById('s_section').value.trim();
    const yearLevel = document.getElementById('s_year').value;
    const email = document.getElementById('s_email').value.trim();
    const password = document.getElementById('s_password').value;

    const errors = [];
    if (!studentId) errors.push('Student ID is required.');
    if (studentId && state.students.some(s => s.studentId === studentId && s.id !== studentDraft.id)) errors.push('That Student ID is already registered.');
    if (!fullName) errors.push('Full name is required.');
    if (!department) errors.push('Select a department.');
    if (!major) errors.push('Select a major.');
    if (!section) errors.push('Section is required.');
    if (!password) errors.push('Set a password.');
    else if (password.length < 6) errors.push('Password should be at least 6 characters.');

    if (errors.length) {
        document.getElementById('studentFormError').innerHTML = `<div class="alert alert-error"><strong>Please fix the following:</strong><br>${errors.map(escapeHtml).join('<br>')}</div>`;
        window.scrollTo(0,0);
        return;
    }
    document.getElementById('studentFormError').innerHTML = '';

    studentDraft.studentId = studentId;
    studentDraft.fullName = fullName;
    studentDraft.department = department;
    studentDraft.major = major;
    studentDraft.section = section;
    studentDraft.yearLevel = yearLevel;
    studentDraft.email = email;
    studentDraft.password = password;

    if (studentDraft.id) {
        const idx = state.students.findIndex(s => s.id === studentDraft.id);
        state.students[idx] = {...studentDraft};
        addLog(`Admin edited student record: ${fullName} (${studentId}).`);
    } else {
        studentDraft.id = state.nextStudentId++;
        studentDraft.status = studentDraft.status || 'active';
        studentDraft.hasVoted = false;
        studentDraft.dateRegistered = new Date().toISOString().slice(0,10);
        state.students.push({...studentDraft});
        addLog(`Admin registered a new student: ${fullName} (${studentId}).`);
    }

    studentDraft = null;
    document.getElementById('studentListView').style.display = 'block';
    document.getElementById('studentFormView').style.display = 'none';
    renderStudentList();
}

function deleteStudent(id){
    const s = state.students.find(s => s.id === id);
    if (!s) return;
    if (!confirm(`Delete ${s.fullName}? In a real system this would also remove their voting record.`)) return;
    state.students = state.students.filter(st => st.id !== id);
    addLog(`Admin deleted student record: ${s.fullName} (${s.studentId}).`);
    renderStudentList();
}

/* =========================================================================
   RESULTS PANEL
   ========================================================================= */
function renderResultsPanel(){
    const blocks = state.elections.map(e => {
        const visible = resultsVisibleToStudents(e);
        const positionBlocks = e.positions.map(pos => {
            const candidates = e.candidates.filter(c => c.position === pos.title);
            const {votesCast} = electionTurnout(e);
            // Distribute mock votes across candidates for illustration
            const total = candidates.length ? votesCast : 0;
            const shares = candidates.map((c,i) => {
                const base = total / candidates.length;
                const wobble = (i % 2 === 0 ? 1.15 : 0.85);
                return Math.max(0, Math.round(base * wobble));
            });
            const sumShares = shares.reduce((a,b)=>a+b,0) || 1;

            const rows = candidates.map((c,i) => {
                const pct = Math.round((shares[i]/sumShares)*100);
                return `
                <div class="result-row">
                    <div class="result-header">
                        <span>${escapeHtml(c.name)} <span class="muted">${c.party && c.party !== 'No Party / Independent' ? '· ' + escapeHtml(c.party) : ''}</span></span>
                        <span>${shares[i]} vote${shares[i]===1?'':'s'} (${pct}%)</span>
                    </div>
                    <div class="progress-bar-track"><div class="progress-bar-fill" style="width:${pct}%;"></div></div>
                </div>`;
            }).join('');

            return `<div style="margin-bottom:1.25rem;"><h4 style="margin:0 0 .5rem;font-size:.95rem;">${escapeHtml(pos.title)}</h4>${rows || '<p class="muted">No candidates.</p>'}</div>`;
        }).join('');

        return `
        <div class="card results-block">
            <div class="rm-head">
                <div>
                    <h3 style="margin:0;">${escapeHtml(e.name)}</h3>
                    <span class="status-large ${statusClass(e.status)}" style="margin-top:.4rem;"><span class="status-dot"></span>${statusLabel(e.status)}</span>
                </div>
            </div>
            <div class="results-visibility-row" style="margin-top:1rem;">
                <span class="vis-label">Student visibility:</span>
                ${visibilityButtons(e.id, e.resultsVisibility)}
                <span class="muted" style="font-size:.78rem;">${visible ? '(currently visible to students)' : '(currently hidden from students)'}</span>
            </div>
            <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0;">
            ${positionBlocks}
        </div>`;
    }).join('');

    document.getElementById('panel-results').innerHTML = `
        <p class="muted" style="margin-top:0;">Admins can always see live results here, regardless of the student-facing visibility setting.</p>
        ${blocks || '<div class="coming-soon">No elections yet.</div>'}
    `;
}

/* =========================================================================
   LOGS PANEL
   ========================================================================= */
function renderLogsPanel(){
    const items = state.logs.map(l => `
        <div class="log-item"><span class="log-time">${escapeHtml(l.time)}</span><span class="log-text">${escapeHtml(l.text)}</span></div>
    `).join('');
    document.getElementById('panel-logs').innerHTML = `
        <div class="logs-full">${items || '<div class="coming-soon">No activity yet.</div>'}</div>
    `;
}

/* =========================================================================
   INITIAL RENDER
   ========================================================================= */
function renderAll(){
    renderDashboard();
    renderElectionList();
    renderStudentList();
    renderResultsPanel();
    renderLogsPanel();
}
renderAll();
</script>
</body>
</html>