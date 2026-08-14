<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role(['trainee', 'consultant', 'rater']);
require_once __DIR__ . '/../config/db_connect.php';

$stmt = $pdo->prepare(
    'SELECT s.*, e.title AS exam_title
     FROM exam_submissions s
     JOIN exams e ON e.id = s.exam_id
     WHERE s.user_id = ? AND s.status = "submitted"
     ORDER BY s.submitted_at DESC'
);
$stmt->execute([$_SESSION['user_id']]);
$submissions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Grades - Trainee Evaluation System</title>
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
    th, td { text-align: left; padding: 14px 16px; font-size: 14px; border-bottom: 1px solid #eef0f3; }
    th { background: #f8f9fb; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    tr:last-child td { border-bottom: none; }

    .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
    .badge.graded { background:#eaf7ee; color:#1e7a34; }
    .badge.pending { background:#fdf3e3; color:#a06b0a; }

    .score { font-weight: bold; color: #1a3a6b; font-size: 15px; }
    .percent { color: #6b7280; font-size: 12px; margin-left: 6px; }

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
        <a href="grades.php" class="active">Grades</a>
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
        <h2>My Grades</h2>

        <?php if ($submissions): ?>
        <table>
            <thead>
                <tr>
                    <th>Exam</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['exam_title']) ?></td>
                    <td>
                        <?php if ($s['grading_status'] === 'graded'): ?>
                            <span class="score"><?= (float)$s['total_score'] ?> / <?= (float)$s['max_score'] ?></span>
                            <?php if ((float)$s['max_score'] > 0): ?>
                                <span class="percent">(<?= round(((float)$s['total_score'] / (float)$s['max_score']) * 100) ?>%)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $s['grading_status'] ?>"><?= $s['grading_status'] === 'graded' ? 'Graded' : 'Pending Grading' ?></span></td>
                    <td><?= htmlspecialchars($s['submitted_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state">
                You haven't submitted any exams yet.
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
