<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

$exam_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM exams WHERE id = ? AND created_by = ?');
$stmt->execute([$exam_id, $_SESSION['user_id']]);
$exam = $stmt->fetch();

if (!$exam) {
    header('Location: exams.php');
    exit;
}

// Ensure grades table exists (safe to run repeatedly)
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS exam_grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_id INT NOT NULL UNIQUE,
        grader_id INT NULL,
        score DECIMAL(6,2) NULL,
        comment TEXT NULL,
        graded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (submission_id) REFERENCES exam_submissions(id) ON DELETE CASCADE,
        FOREIGN KEY (grader_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$subs = $pdo->prepare(
    'SELECT s.id AS submission_id, s.user_id, s.submitted_at, u.full_name, g.score
     FROM exam_submissions s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN (SELECT submission_id, score FROM exam_grades) g ON g.submission_id = s.id
     WHERE s.exam_id = ? AND s.status = "submitted"
     ORDER BY s.submitted_at DESC'
);
$subs->execute([$exam_id]);
$subs = $subs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submissions - <?= htmlspecialchars($exam['title']) ?></title>
    <style>/* reuse simple styles from admin pages */
        body { font-family: 'Segoe UI', Arial, sans-serif; background:#f7fafc; margin:0; padding:20px; }
        .card { background:#fff; border-radius:6px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); max-width:980px; margin:20px auto; }
        table { width:100%; border-collapse:collapse; }
        th,td { padding:10px 8px; text-align:left; border-bottom:1px solid #eef2f7; }
        .btn { padding:6px 10px; border-radius:4px; background:#4a7fd4; color:#fff; text-decoration:none; }
        .muted { color:#6b7280; font-size:13px; }
    </style>
</head>
<body>
    <div class="card">
        <a href="exams.php" class="muted">&larr; Back to Exams</a>
        <h2 style="margin-top:6px;">Submissions for "<?= htmlspecialchars($exam['title']) ?>"</h2>
        <?php if (!$subs): ?>
            <p class="muted">No submissions yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Submitted At</th>
                        <th>Score</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subs as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['full_name']) ?></td>
                            <td><?= htmlspecialchars($s['submitted_at']) ?></td>
                            <td><?= $s['score'] !== null ? htmlspecialchars($s['score']) : '<span class="muted">Not graded</span>' ?></td>
                            <td><a class="btn" href="view_submission.php?submission_id=<?= (int)$s['submission_id'] ?>">View / Grade</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
