<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('super_admin');
require_once __DIR__ . '/../config/db_connect.php';

$total_interns = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'intern' AND status = 'active'")->fetchColumn();

// Interns who have logged at least once
$active_today = $pdo->query(
    "SELECT COUNT(DISTINCT user_id) FROM dtr_logs d
     JOIN users u ON u.id = d.user_id
     WHERE u.role = 'intern' AND DATE(d.log_time) = CURDATE()"
)->fetchColumn();

// Completed hours per intern (sum of daily worked hours where both time_in and time_out exist)
$hours_stmt = $pdo->query(
    "SELECT u.id, u.full_name, u.required_hours,
            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sub.time_in, sub.time_out)), 0) / 60 AS completed_hours
     FROM users u
     LEFT JOIN (
        SELECT user_id,
               MIN(CASE WHEN log_type = 'time_in' THEN log_time END) AS time_in,
               MAX(CASE WHEN log_type = 'time_out' THEN log_time END) AS time_out
        FROM dtr_logs
        GROUP BY user_id, DATE(log_time)
     ) sub ON sub.user_id = u.id
     WHERE u.role = 'intern' AND u.status = 'active'
     GROUP BY u.id"
)->fetchAll();

$completed_count = 0;
foreach ($hours_stmt as $h) {
    if ($h['required_hours'] !== null && $h['completed_hours'] >= (float)$h['required_hours']) {
        $completed_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Super Admin Dashboard - Trainee Evaluation System</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f2f4f7; }
    .layout { display: flex; min-height: 100vh; }
    .sidebar {
        width: 260px; background: #0d2d4e; color: #fff;
        display: flex; flex-direction: column; padding: 24px 0;
    }
    .sidebar .brand { padding: 0 24px 24px 24px; }
    .sidebar .brand strong { display:block; font-size: 16px; }
    .sidebar .brand span { display:block; font-size: 12px; color: #b8c6e0; margin-top:4px; }
    .sidebar a { color: #d7e0f2; text-decoration: none; padding: 12px 24px; font-size: 14px; display: block; }
    .sidebar a.active { background: rgba(255,255,255,0.12); color: #fff; font-weight: bold; }
    .sidebar a:hover { background: rgba(255,255,255,0.08); }
    .sidebar .bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px; }

    .main { flex: 1; padding: 30px 40px; }
    .main h2 { color: #0d2d4e; margin-top: 0; }

    .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
    .card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .card .label { font-size: 13px; color: #6b7280; margin-bottom: 8px; }
    .card .value { font-size: 32px; font-weight: bold; color: #0d2d4e; }

    .panel { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .panel h3 { margin-top: 0; color: #0d2d4e; }
    .btn {
        display: inline-block; background: #0ea5e9; color: #fff; text-decoration: none;
        padding: 10px 18px; border-radius: 5px; font-size: 14px; font-weight: bold;
    }
    .btn:hover { background: #0c8bc4; }
</style>
</head>
<body>
<div class="layout">
    <div class="sidebar">
        <div class="brand">
            <strong>Super Admin</strong>
            <span>NKTI Anesthesiology &middot; Intern Tracking</span>
        </div>
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="biometrics_device.php">Biometrics Device</a>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <h2>Super Admin Dashboard</h2>

        <div class="cards">
            <div class="card">
                <div class="label">Total active interns</div>
                <div class="value"><?= (int)$total_interns ?></div>
            </div>
            <div class="card">
                <div class="label">Logged in today</div>
                <div class="value"><?= (int)$active_today ?></div>
            </div>
            <div class="card">
                <div class="label">Completed required hours</div>
                <div class="value"><?= (int)$completed_count ?></div>
            </div>
        </div>

        <div class="panel">
            <h3>Biometrics Device</h3>
            <p>Track intern Time In / Time Out records, monitor remaining internship hours, and open the intern biometrics kiosk.</p>
            <a class="btn" href="biometrics_device.php">Open Biometrics Device</a>
        </div>
    </div>
</div>
</body>
</html>
