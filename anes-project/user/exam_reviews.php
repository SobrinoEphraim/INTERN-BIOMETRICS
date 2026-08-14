<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('consultant');
require_once __DIR__ . '/../config/db_connect.php';

$stmt = $pdo->query(
    'SELECT s.id AS submission_id, s.submitted_at,
            u.full_name AS student_name,
            e.title AS exam_title,
            (SELECT COUNT(*) FROM exam_feedback f WHERE f.submission_id = s.id) AS feedback_count
     FROM exam_submissions s
     JOIN users u ON u.id = s.user_id
     JOIN exams e ON e.id = s.exam_id
     WHERE s.status = "submitted"
     ORDER BY s.submitted_at DESC'
);
$submissions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Review Exams - Trainee Evaluation System</title>
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
    .badge.given { background:#eaf7ee; color:#1e7a34; }
    .badge.none { background:#f3f4f6; color:#6b7280; }

    .btn {
        display: inline-block; background: #4a7fd4; color: #fff; text-decoration: none;
        padding: 8px 16px; border-radius: 5px; font-size: 13px; font-weight: bold;
    }
    .btn:hover { background: #3c6cc0; }

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
        <a href="dtr.php">My DTR</a>
        <a href="exam_reviews.php" class="active">Review Exams</a>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <h2>Review Trainee Exams</h2>

        <?php if ($submissions): ?>
        <table>
            <thead>
                <tr>
                    <th>Trainee</th>
                    <th>Exam</th>
                    <th>Submitted</th>
                    <th>Feedback</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['student_name']) ?></td>
                    <td><?= htmlspecialchars($s['exam_title']) ?></td>
                    <td><?= htmlspecialchars($s['submitted_at']) ?></td>
                    <td>
                        <?php if ($s['feedback_count'] > 0): ?>
                            <span class="badge given"><?= (int)$s['feedback_count'] ?> comment<?= $s['feedback_count'] == 1 ? '' : 's' ?></span>
                        <?php else: ?>
                            <span class="badge none">No feedback yet</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn" href="exam_feedback.php?id=<?= (int)$s['submission_id'] ?>">
                            <?= $s['feedback_count'] > 0 ? 'View / Add Feedback' : 'Give Feedback' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state">No exam submissions available yet.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
