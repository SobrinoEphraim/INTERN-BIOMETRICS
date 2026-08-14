<?php
require_once __DIR__ . '/../config/auth_check.php';
require_login();
require_once __DIR__ . '/../config/db_connect.php';

$submission_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT s.*, u.full_name AS student_name, u.id AS student_id, e.title AS exam_title
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

// ------------------------------------------------------------
// Access control:
// - consultants can view any submission and post feedback
// - the trainee who owns the submission can view (read-only)
// - anyone else gets redirected away
// ------------------------------------------------------------
$is_consultant = ($_SESSION['role'] === 'consultant');
$is_owner = ((int)$_SESSION['user_id'] === (int)$submission['student_id']);

if (!$is_consultant && !$is_owner) {
    header('Location: exams.php');
    exit;
}

$error = '';

if ($is_consultant && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback_text = trim($_POST['feedback_text'] ?? '');
    if ($feedback_text === '') {
        $error = 'Please write a comment before submitting.';
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO exam_feedback (submission_id, reviewer_id, feedback_text) VALUES (?, ?, ?)'
        );
        $ins->execute([$submission_id, $_SESSION['user_id'], $feedback_text]);
        header('Location: exam_feedback.php?id=' . $submission_id);
        exit;
    }
}

// Load questions + this trainee's answers (read-only view)
$q_stmt = $pdo->prepare('SELECT * FROM exam_questions WHERE exam_id = (SELECT exam_id FROM exam_submissions WHERE id = ?) ORDER BY sort_order ASC');
$q_stmt->execute([$submission_id]);
$questions = $q_stmt->fetchAll();

foreach ($questions as &$q) {
    if ($q['question_type'] === 'essay') {
        $a_stmt = $pdo->prepare('SELECT answer_text FROM exam_answers WHERE submission_id = ? AND question_id = ?');
        $a_stmt->execute([$submission_id, $q['id']]);
        $q['answer_text'] = $a_stmt->fetchColumn();
        $q['options'] = [];
    } else {
        $o_stmt = $pdo->prepare('SELECT * FROM exam_options WHERE question_id = ? ORDER BY sort_order ASC');
        $o_stmt->execute([$q['id']]);
        $q['options'] = $o_stmt->fetchAll();

        $sel_stmt = $pdo->prepare('SELECT option_id FROM exam_answer_options WHERE submission_id = ? AND question_id = ?');
        $sel_stmt->execute([$submission_id, $q['id']]);
        $q['selected'] = array_column($sel_stmt->fetchAll(), 'option_id');
    }
}
unset($q);

// Load feedback thread
$f_stmt = $pdo->prepare(
    'SELECT f.*, u.full_name AS reviewer_name, u.role AS reviewer_role
     FROM exam_feedback f
     JOIN users u ON u.id = f.reviewer_id
     WHERE f.submission_id = ?
     ORDER BY f.created_at ASC'
);
$f_stmt->execute([$submission_id]);
$feedback_list = $f_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Exam Feedback - Trainee Evaluation System</title>
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

    .main { flex: 1; padding: 30px 40px; max-width: 780px; }
    .back-link { color:#3b6fd6; font-size: 13px; text-decoration:none; }
    .main h2 { color: #1a3a6b; margin: 10px 0 2px 0; }
    .main .meta { color: #6b7280; font-size: 14px; margin-bottom: 24px; }

    .error-box { background:#fdecea; color:#b3261e; border:1px solid #f5c2c0; border-radius:5px; padding:10px 14px; font-size:13px; margin-bottom:16px; }

    .section-title { color:#1a3a6b; font-size: 16px; margin: 26px 0 14px 0; }

    .question-card {
        background: #fff; border-radius: 8px; padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 14px;
    }
    .question-text { font-size: 14px; font-weight: bold; color: #1e1e1e; margin-bottom: 10px; }
    .answer-block { background: #f8f9fb; border-radius: 6px; padding: 12px; font-size: 14px; color: #333; white-space: pre-wrap; }
    .option-row { display:flex; align-items:center; gap: 10px; padding: 4px 0; font-size: 14px; }
    .option-row .icon { width: 18px; text-align: center; font-weight:bold; }
    .option-row.selected-correct { color: #1e7a34; font-weight:bold; }
    .option-row.selected-wrong { color: #b3261e; font-weight:bold; }

    .feedback-thread { margin-bottom: 20px; }
    .feedback-item {
        background: #fff; border-radius: 8px; padding: 16px 18px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 12px;
    }
    .feedback-item .fb-header { display:flex; justify-content:space-between; margin-bottom: 6px; }
    .feedback-item .fb-author { font-weight: bold; font-size: 13px; color: #1a3a6b; }
    .feedback-item .fb-role { font-size: 11px; color: #6b7280; text-transform: uppercase; margin-left: 6px; }
    .feedback-item .fb-date { font-size: 12px; color: #9ca3af; }
    .feedback-item .fb-text { font-size: 14px; color: #333; white-space: pre-wrap; }

    .empty-feedback { background:#fff; border-radius:8px; padding: 24px; text-align:center; color:#6b7280; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }

    .panel { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    textarea {
        width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
        border-radius: 5px; font-size: 14px; font-family: inherit;
        resize: vertical; min-height: 90px;
    }
    button {
        margin-top: 12px; padding: 12px 24px; border: none; border-radius: 5px;
        background: #4a7fd4; color: #fff; font-size: 14px; font-weight: bold; cursor: pointer;
    }
    button:hover { background: #3c6cc0; }
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
        <?php if ($is_consultant): ?>
        <a href="exam_reviews.php" class="active">Review Exams</a>
        <?php endif; ?>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <a class="back-link" href="<?= $is_consultant ? 'exam_reviews.php' : 'exams.php' ?>">&larr; Back</a>
        <h2><?= htmlspecialchars($submission['exam_title']) ?></h2>
        <p class="meta">Trainee: <strong><?= htmlspecialchars($submission['student_name']) ?></strong></p>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <h3 class="section-title">Answers</h3>
        <?php foreach ($questions as $i => $q): ?>
            <div class="question-card">
                <div class="question-text"><?= ($i + 1) ?>. <?= htmlspecialchars($q['question_text']) ?></div>

                <?php if ($q['question_type'] === 'essay'): ?>
                    <div class="answer-block"><?= htmlspecialchars($q['answer_text'] ?: '(no answer submitted)') ?></div>
                <?php else: ?>
                    <?php foreach ($q['options'] as $opt):
                        $isCorrect = (int)$opt['is_correct'] === 1;
                        $isSelected = in_array($opt['id'], $q['selected']);
                        $rowClass = $isSelected ? ($isCorrect ? 'selected-correct' : 'selected-wrong') : '';
                    ?>
                        <div class="option-row <?= $rowClass ?>">
                            <span class="icon"><?= $isSelected ? '&#9679;' : '' ?></span>
                            <span><?= htmlspecialchars($opt['option_text']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <h3 class="section-title">Feedback</h3>
        <div class="feedback-thread">
            <?php if ($feedback_list): ?>
                <?php foreach ($feedback_list as $fb): ?>
                    <div class="feedback-item">
                        <div class="fb-header">
                            <div>
                                <span class="fb-author"><?= htmlspecialchars($fb['reviewer_name']) ?></span>
                                <span class="fb-role"><?= htmlspecialchars($fb['reviewer_role']) ?></span>
                            </div>
                            <span class="fb-date"><?= htmlspecialchars($fb['created_at']) ?></span>
                        </div>
                        <div class="fb-text"><?= htmlspecialchars($fb['feedback_text']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-feedback">
                    <?= $is_owner && !$is_consultant ? 'No feedback has been given yet.' : 'No feedback yet. Be the first to leave a comment.' ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($is_consultant): ?>
            <div class="panel">
                <form method="POST" action="exam_feedback.php?id=<?= (int)$submission_id ?>">
                    <textarea name="feedback_text" placeholder="Write your feedback for this trainee..." required></textarea>
                    <button type="submit">Post Feedback</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
