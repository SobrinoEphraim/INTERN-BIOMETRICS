<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('intern');
require_once __DIR__ . '/../config/db_connect.php';

$stmt = $pdo->prepare(
    "SELECT u.required_hours,
            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sub.time_in, sub.time_out)), 0) / 60 AS completed_hours
     FROM users u
     LEFT JOIN (
        SELECT user_id,
               MIN(CASE WHEN log_type = 'time_in' THEN log_time END) AS time_in,
               MAX(CASE WHEN log_type = 'time_out' THEN log_time END) AS time_out
        FROM dtr_logs
        WHERE user_id = ?
        GROUP BY DATE(log_time)
     ) sub ON 1=1
     WHERE u.id = ?
     GROUP BY u.id"
);
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$row = $stmt->fetch();

$required = $row && $row['required_hours'] !== null ? (float)$row['required_hours'] : null;
$completed = $row ? round((float)$row['completed_hours'], 2) : 0;
$remaining = $required !== null ? round($required - $completed, 2) : null;
$pct = ($required && $required > 0) ? min(100, round(($completed / $required) * 100)) : 0;
$is_done = $remaining !== null && $remaining <= 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Intern Dashboard - Trainee Evaluation System</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f2f4f7; }
    .layout { display: flex; min-height: 100vh; }
    .sidebar { width: 260px; background: #0d2d4e; color: #fff; display: flex; flex-direction: column; padding: 24px 0; }
    .sidebar .brand { padding: 0 24px 24px 24px; }
    .sidebar .brand strong { display:block; font-size: 16px; }
    .sidebar .brand span { display:block; font-size: 12px; color: #b8c6e0; margin-top:4px; }
    .sidebar a { color: #d7e0f2; text-decoration: none; padding: 12px 24px; font-size: 14px; display: block; }
    .sidebar a.active { background: rgba(255,255,255,0.12); color: #fff; font-weight: bold; }
    .sidebar a:hover { background: rgba(255,255,255,0.08); }
    .sidebar .bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px; }

    .main { flex: 1; padding: 30px 40px; }
    .main h2 { color: #0d2d4e; margin-top: 0; }

    .hours-card {
        background: #fff; border-radius: 10px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        max-width: 480px; text-align: center; margin-bottom: 24px;
    }
    .hours-card .big-number { font-size: 44px; font-weight: bold; color: #0d2d4e; }
    .hours-card .big-number.done { color: #1e7a34; }
    .hours-card .caption { font-size: 13px; color: #6b7280; margin-bottom: 18px; }
    .progress-bar { width: 100%; height: 10px; background: #eef0f3; border-radius: 5px; overflow: hidden; margin-bottom: 8px; }
    .progress-bar .fill { height: 100%; background: #0ea5e9; }
    .progress-bar .fill.done { background: #1e7a34; }
    .stats-row { display:flex; justify-content:space-between; font-size: 12px; color: #6b7280; margin-top: 12px; }

    .panel { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); max-width: 480px; }
    .panel h3 { margin-top: 0; color: #0d2d4e; font-size: 15px; }
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
            <strong>Intern Portal</strong>
            <span>NKTI Anesthesiology</span>
        </div>
        <a href="dashboard.php" class="active">My Dashboard</a>
        <a href="dtr.php">My DTR</a>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <h2>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></h2>

        <div class="hours-card">
            <?php if ($required !== null): ?>
                <?php if ($is_done): ?>
                    <div class="big-number done">Completed!</div>
                <?php else: ?>
                    <div class="big-number"><?= number_format($remaining, 1) ?></div>
                    <div class="caption">hours remaining</div>
                <?php endif; ?>
                <div class="progress-bar"><div class="fill <?= $is_done ? 'done' : '' ?>" style="width:<?= $pct ?>%;"></div></div>
                <div class="stats-row">
                    <span><?= number_format($completed, 1) ?> hrs completed</span>
                    <span><?= number_format($required, 1) ?> hrs required</span>
                </div>
            <?php else: ?>
                <div class="caption">No required hours have been set for your account yet. Contact your Super Admin.</div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h3>Time In / Time Out</h3>
            <p style="font-size:13px; color:#6b7280;">Tap in and out at the intern biometrics station to log your hours.</p>
            <a class="btn" href="dtr.php">View My DTR</a>
        </div>
    </div>
</div>
</body>
</html>
