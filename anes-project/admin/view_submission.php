<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

$submission_id = (int)($_GET['submission_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT s.*, u.full_name AS user_name, e.title AS exam_title, e.id AS exam_id
     FROM exam_submissions s
     JOIN users u ON u.id = s.user_id
     JOIN exams e ON e.id = s.exam_id
     WHERE s.id = ?'
);
$stmt->execute([$submission_id]);
$submission = $stmt->fetch();

if (!$submission) {
    header('Location: exams.php');
    exit;
}

// Load questions
$q_stmt = $pdo->prepare('SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY sort_order ASC');
$q_stmt->execute([$submission['exam_id']]);
$questions = $q_stmt->fetchAll();

// Load text answers
$a_stmt = $pdo->prepare('SELECT * FROM exam_answers WHERE submission_id = ?');
$a_stmt->execute([$submission_id]);
$answers_text = [];
foreach ($a_stmt->fetchAll() as $a) {
    $answers_text[$a['question_id']] = $a['answer_text'];
}

// Load option answers
$o_stmt = $pdo->prepare(
    'SELECT ao.question_id, GROUP_CONCAT(o.option_text SEPARATOR "|||") AS opts
     FROM exam_answer_options ao
     JOIN exam_options o ON o.id = ao.option_id
     WHERE ao.submission_id = ?
     GROUP BY ao.question_id'
);
$o_stmt->execute([$submission_id]);
$answers_opts = [];
foreach ($o_stmt->fetchAll() as $r) {
    $answers_opts[$r['question_id']] = explode('|||', $r['opts']);
}

// Ensure grades table exists
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

// Handle grade POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $score = trim($_POST['score'] ?? '');
    $comment = trim($_POST['comment'] ?? '');

    if ($score === '') {
        $msg = 'Please provide a numeric score.';
    } else {
        $score_val = (float)$score;
        $ins = $pdo->prepare(
            'INSERT INTO exam_grades (submission_id, grader_id, score, comment, graded_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE score = VALUES(score), comment = VALUES(comment), grader_id = VALUES(grader_id), graded_at = NOW()'
        );
        $ins->execute([$submission_id, $_SESSION['user_id'], $score_val, $comment !== '' ? $comment : null]);
        header('Location: view_submission.php?submission_id=' . $submission_id);
        exit;
    }
}

// Load existing grade if any
$g_stmt = $pdo->prepare('SELECT * FROM exam_grades WHERE submission_id = ?');
$g_stmt->execute([$submission_id]);
$grade = $g_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Submission - <?= htmlspecialchars($submission['exam_title']) ?></title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background:#f7fafc; margin:0; padding:20px; }
        .container { max-width:980px; margin:20px auto; }
        .card { background:#fff; border-radius:6px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
        .question { border-bottom:1px solid #eef2f7; padding:14px 0; }
        .q-title { font-weight:bold; color:#111827; }
        .answer { margin-top:8px; color:#374151; }
        .meta { color:#6b7280; font-size:13px; }
        .grade-form { margin-top:18px; }
        input[type="text"], textarea { width:100%; padding:8px; border:1px solid #d1d5db; border-radius:4px; }
        button { background:#4a7fd4; color:#fff; padding:8px 12px; border-radius:4px; border:none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <a class="meta" href="view_submissions.php?id=<?= (int)$submission['exam_id'] ?>">&larr; Back to Submissions</a>
            <h2 style="margin-top:6px;"><?= htmlspecialchars($submission['exam_title']) ?> — <?= htmlspecialchars($submission['user_name']) ?></h2>
            <p class="meta">Submitted at: <?= htmlspecialchars($submission['submitted_at']) ?></p>

            <?php foreach ($questions as $i => $q): ?>
                <div class="question">
                    <div class="q-title"><?= ($i + 1) ?>. <?= htmlspecialchars($q['question_text']) ?></div>
                    <div class="answer">
                        <?php if ($q['question_type'] === 'essay'): ?>
                            <?= nl2br(htmlspecialchars($answers_text[$q['id']] ?? '')) ?>
                        <?php else: ?>
                            <?php $opts = $answers_opts[$q['id']] ?? []; ?>
                            <?php if (empty($opts)): ?>
                                <span class="meta">No answer provided.</span>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($opts as $opt): ?>
                                        <li><?= htmlspecialchars($opt) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="grade-form">
                <h3>Grade this submission</h3>
                <?php if (!empty($msg)): ?>
                    <div style="color:#b3261e; margin-bottom:8px;"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>
                <form method="POST" action="view_submission.php?submission_id=<?= (int)$submission_id ?>">
                    <label>Score (numeric):</label>
                    <input type="text" name="score" value="<?= htmlspecialchars($grade['score'] ?? '') ?>">
                    <label style="margin-top:8px; display:block;">Comment (optional):</label>
                    <textarea name="comment" rows="4"><?= htmlspecialchars($grade['comment'] ?? '') ?></textarea>
                    <div style="margin-top:10px;"><button type="submit">Save Grade</button></div>
                </form>
                <?php if ($grade): ?>
                    <p class="meta" style="margin-top:10px;">Last graded at <?= htmlspecialchars($grade['graded_at']) ?> by user #<?= htmlspecialchars($grade['grader_id']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
