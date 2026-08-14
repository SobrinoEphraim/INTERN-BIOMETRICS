<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pending     = $pdo->query("SELECT COUNT(*) FROM users WHERE must_reset_password = 1")->fetchColumn();
$never_login = $pdo->query("SELECT COUNT(*) FROM users WHERE last_login_at IS NULL")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Trainee Evaluation System</title>
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
    .sidebar a {
        color: #d7e0f2; text-decoration: none; padding: 12px 24px;
        font-size: 14px; display: block;
    }
    .sidebar a.active { background: rgba(255,255,255,0.12); color: #fff; font-weight: bold; }
    .sidebar a:hover { background: rgba(255,255,255,0.08); }
    .sidebar .section-label {
        padding: 18px 24px 6px 24px; font-size: 11px; letter-spacing: 0.5px;
        text-transform: uppercase; color: #8fa3c8;
    }
    .sidebar .bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px; }

    .main { flex: 1; padding: 30px 40px; }
    .topbar { display: flex; align-items: center; gap: 16px; margin-bottom: 26px; }
    .topbar h2 { margin: 0; color: #1a3a6b; }

    .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
    .card {
        background: #fff; border-radius: 8px; padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .card .label { font-size: 13px; color: #6b7280; margin-bottom: 8px; }
    .card .value { font-size: 32px; font-weight: bold; color: #1a3a6b; }

    .panel {
        background: #fff; border-radius: 8px; padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 24px;
    }
    .panel h3 { margin-top: 0; color: #1a3a6b; }
    .btn {
        display: inline-block; background: #4a7fd4; color: #fff; text-decoration: none;
        padding: 10px 18px; border-radius: 5px; font-size: 14px; font-weight: bold;
    }
    .btn:hover { background: #3c6cc0; }
</style>
</head>
<body>
<div class="layout">
    <div class="sidebar">
        <div class="brand">
            <strong>Trainee Evaluation System</strong>
            <span>NKTI Anesthesiology &middot; Admin</span>
        </div>

        <div class="section-label">Manage</div>
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="manage_users.php">Users / Roster</a>
        <a href="create_user.php">Add New User</a>
        <a href="assign_evaluations.php">Assign Evaluations</a>
        <a href="exams.php">Exams</a>
        <a href="grades.php">Grades</a>
        <a href="class_record.php">Class Record</a>
        <a href="pds_forms.php">PDS Forms</a>
        <a href="pds_records.php">PDS Records</a>
        <a href="pds_approvals.php">PDS Approvals</a>
        <a href="dtr.php">DTR</a>

        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <div class="topbar">
            <h2>Admin Dashboard</h2>
        </div>

        <div class="cards">
            <div class="card">
                <div class="label">Total registered users</div>
                <div class="value"><?= (int)$total_users ?></div>
            </div>
            <div class="card">
                <div class="label">Pending password setup</div>
                <div class="value"><?= (int)$pending ?></div>
            </div>
            <div class="card">
                <div class="label">Never signed in</div>
                <div class="value"><?= (int)$never_login ?></div>
            </div>
        </div>

        <div class="panel">
            <h3>Quick actions</h3>
            <p>Create accounts for trainees, consultants, and raters. New accounts sign in using the default access code, then set their own password.</p>
            <a class="btn" href="create_user.php">+ Add New User</a>
            &nbsp;
            <a class="btn" href="manage_users.php" style="background:#6b7280;">View All Users</a>
        </div>
    </div>
</div>
</body>
</html>
