<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role(['trainee', 'consultant', 'rater']);
require_once __DIR__ . '/../config/db_connect.php';

// Grouped by day: earliest Time In + latest Time Out for that day
$stmt = $pdo->prepare(
    "SELECT DATE(log_time) AS log_date,
            MIN(CASE WHEN log_type = 'time_in' THEN log_time END) AS time_in,
            MAX(CASE WHEN log_type = 'time_out' THEN log_time END) AS time_out
     FROM dtr_logs
     WHERE user_id = ?
     GROUP BY DATE(log_time)
     ORDER BY log_date DESC"
);
$stmt->execute([$_SESSION['user_id']]);
$days = $stmt->fetchAll();

// Fetch the photo for each Time In / Time Out on each day
foreach ($days as &$d) {
    $in_photo = $pdo->prepare(
        "SELECT photo_path FROM dtr_logs WHERE user_id = ? AND DATE(log_time) = ? AND log_type = 'time_in' ORDER BY log_time ASC LIMIT 1"
    );
    $in_photo->execute([$_SESSION['user_id'], $d['log_date']]);
    $d['time_in_photo'] = $in_photo->fetchColumn();

    $out_photo = $pdo->prepare(
        "SELECT photo_path FROM dtr_logs WHERE user_id = ? AND DATE(log_time) = ? AND log_type = 'time_out' ORDER BY log_time DESC LIMIT 1"
    );
    $out_photo->execute([$_SESSION['user_id'], $d['log_date']]);
    $d['time_out_photo'] = $out_photo->fetchColumn();
}
unset($d);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My DTR - Trainee Evaluation System</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f2f4f7; }
    .layout { display: flex; min-height: 100vh; }
    .sidebar { width: 260px; background: #1a3a6b; color: #fff; display: flex; flex-direction: column; padding: 24px 0; }
    .sidebar .brand { padding: 0 24px 24px 24px; }
    .sidebar .brand strong { display:block; font-size: 16px; }
    .sidebar .brand span { display:block; font-size: 12px; color: #b8c6e0; margin-top:4px; }
    .sidebar a { color: #d7e0f2; text-decoration: none; padding: 12px 24px; font-size: 14px; display: block; }
    .sidebar a.active { background: rgba(255,255,255,0.12); color: #fff; font-weight: bold; }
    .sidebar a:hover { background: rgba(255,255,255,0.08); }
    .sidebar .bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px; }

    .main { flex: 1; padding: 30px 40px; }
    .main h2 { color: #1a3a6b; margin-top:0; margin-bottom: 24px; }

    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    th, td { text-align: left; padding: 14px 16px; font-size: 14px; border-bottom: 1px solid #eef0f3; vertical-align: middle; }
    th { background: #f8f9fb; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    tr:last-child td { border-bottom: none; }

    .thumb-row { display:flex; align-items:center; gap: 10px; }
    .thumb { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 1px solid #e5e7eb; }
    .thumb.placeholder { background:#eef0f3; }
    .time-in { color: #1e7a34; font-weight: bold; }
    .time-out { color: #a06b0a; font-weight: bold; }
    .missing { color: #d1d5db; }

    .empty-state { background:#fff; border-radius:8px; padding: 50px; text-align:center; color:#6b7280; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
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
        <a href="dtr.php" class="active">My DTR</a>
        <?php if ($_SESSION['role'] === 'consultant'): ?>
        <a href="exam_reviews.php">Review Exams</a>
        <?php endif; ?>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <h2>My Daily Time Record</h2>

        <?php if ($days): ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($days as $d): ?>
                <tr>
                    <td><?= date('F j, Y (D)', strtotime($d['log_date'])) ?></td>
                    <td>
                        <?php if ($d['time_in']): ?>
                            <div class="thumb-row">
                                <?php if ($d['time_in_photo']): ?>
                                    <img class="thumb" src="../uploads/dtr/<?= htmlspecialchars($d['time_in_photo']) ?>" alt="">
                                <?php else: ?>
                                    <div class="thumb placeholder"></div>
                                <?php endif; ?>
                                <span class="time-in"><?= date('h:i A', strtotime($d['time_in'])) ?></span>
                            </div>
                        <?php else: ?>
                            <span class="missing">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($d['time_out']): ?>
                            <div class="thumb-row">
                                <?php if ($d['time_out_photo']): ?>
                                    <img class="thumb" src="../uploads/dtr/<?= htmlspecialchars($d['time_out_photo']) ?>" alt="">
                                <?php else: ?>
                                    <div class="thumb placeholder"></div>
                                <?php endif; ?>
                                <span class="time-out"><?= date('h:i A', strtotime($d['time_out'])) ?></span>
                            </div>
                        <?php else: ?>
                            <span class="missing">&mdash;</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state">No DTR records yet. Tap in at the biometrics station to get started.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
