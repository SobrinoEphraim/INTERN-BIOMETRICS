<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role(['trainee', 'consultant', 'rater']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Dashboard - Trainee Evaluation System</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f2f4f7; }
    .layout { display: flex; min-height: 100vh; }
    .sidebar {
        width: 260px; background: #1a3a6b; color: #fff;
        display: flex; flex-direction: column; padding: 24px 0;
    }
    .sidebar .brand { padding: 0 24px 24px 24px; }
    .sidebar .brand strong { display:block; font-size: 16px; }
    .sidebar .brand span { display:block; font-size: 12px; color: #b8c6e0; margin-top:4px; }
    .sidebar a { color: #d7e0f2; text-decoration: none; padding: 12px 24px; font-size: 14px; display: block; }
    .sidebar a:hover { background: rgba(255,255,255,0.08); }
    .sidebar .bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px; }
    .main { flex: 1; padding: 30px 40px; }
    .main h2 { color: #1a3a6b; }
    .panel { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
</style>
</head>
<body>
<div class="layout">
    <div class="sidebar">
        <div class="brand">
            <strong>Trainee Evaluation System</strong>
            <span>NKTI Anesthesiology</span>
        </div>
        <a href="dashboard.php">My Dashboard</a>
        <a href="rate_peers.php">Rate Peers</a>
        <a href="exams.php">Exams</a>
        <a href="grades.php">Grades</a>
        <a href="class_record.php">Class Record</a>
        <a href="pds.php">PDS</a>
        <a href="dtr.php">My DTR</a>
        <?php if ($_SESSION['role'] === 'consultant'): ?>
        <a href="exam_reviews.php">Review Exams</a>
        <?php endif; ?>
        <a href="#">My Feedback</a>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <h2>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></h2>
        <div class="panel">
            <p>This is your evaluation dashboard. Build out your rating forms,
            pending evaluations, and feedback views here.</p>
        </div>
    </div>
</div>
</body>
</html>
