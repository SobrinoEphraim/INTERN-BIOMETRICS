<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role(['trainee', 'consultant', 'rater']);
require_once __DIR__ . '/../config/db_connect.php';

$stmt = $pdo->prepare(
    'SELECT e.*,
        (SELECT COUNT(*) FROM exam_questions q WHERE q.exam_id = e.id) AS question_count,
        s.id AS submission_id,
        s.status AS my_status,
        (SELECT COUNT(*) FROM exam_feedback f WHERE f.submission_id = s.id) AS feedback_count
     FROM exams e
     LEFT JOIN exam_submissions s ON s.exam_id = e.id AND s.user_id = ?
     WHERE e.status = "published"
       AND (e.target_role = ? OR e.target_role = "all")
     ORDER BY e.created_at DESC'
);
$stmt->execute([$_SESSION['user_id'], $_SESSION['role']]);
$exams = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Exams - Trainee Evaluation System</title>
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

    .exam-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; }
    .exam-card {
        background: #fff; border-radius: 8px; padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-top: 4px solid #4a90d9;
    }
    .exam-card h3 { margin: 0 0 6px 0; color: #1a1a1a; font-size: 17px; }
    .exam-card .desc { color: #6b7280; font-size: 13px; margin-bottom: 14px; min-height: 18px; }
    .exam-stats { font-size: 13px; color: #6b7280; margin-bottom: 16px; }

    .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
    .badge.submitted { background:#eaf7ee; color:#1e7a34; }
    .badge.pending { background:#fdf3e3; color:#a06b0a; }

    .btn {
        display: inline-block; background: #4a7fd4; color: #fff; text-decoration: none;
        padding: 10px 18px; border-radius: 5px; font-size: 14px; font-weight: bold;
    }
    .btn:hover { background: #3c6cc0; }
    .btn.done { background: #9ca3af; pointer-events: none; }

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
        <a href="exams.php" class="active">Exams</a>
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
        <h2>Exams</h2>

        <?php if ($exams): ?>
        <div class="exam-grid">
            <?php foreach ($exams as $e): ?>
            <div class="exam-card">
                <h3><?= htmlspecialchars($e['title']) ?></h3>
                <div class="desc"><?= htmlspecialchars($e['description'] ?: '') ?></div>
                <div class="exam-stats">
                    <?= (int)$e['question_count'] ?> question<?= $e['question_count'] == 1 ? '' : 's' ?>
                    &nbsp;
                    <?php if ($e['my_status'] === 'submitted'): ?>
                        <span class="badge submitted">Submitted</span>
                    <?php else: ?>
                        <span class="badge pending">Not yet taken</span>
                    <?php endif; ?>
                </div>
                <?php if ($e['my_status'] === 'submitted'): ?>
                    <a class="btn done" href="#">Completed</a>
                    <?php if ($e['feedback_count'] > 0): ?>
                        <a class="btn" style="margin-left:8px; background:#2b5cad;" href="exam_feedback.php?id=<?= (int)$e['submission_id'] ?>">View Feedback (<?= (int)$e['feedback_count'] ?>)</a>
                    <?php else: ?>
                        <a class="btn" style="margin-left:8px; background:#9ca3af;" href="exam_feedback.php?id=<?= (int)$e['submission_id'] ?>">No Feedback Yet</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a class="btn" href="take_exam.php?id=<?= (int)$e['id'] ?>">Take Exam</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state">
                No exams available for you right now.
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
