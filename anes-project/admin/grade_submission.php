<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

$submission_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT s.*, u.full_name AS student_name, e.title AS exam_title, e.id AS exam_id, e.created_by
     FROM exam_submissions s
     JOIN users u ON u.id = s.user_id
     JOIN exams e ON e.id = s.exam_id
     WHERE s.id = ?'
);
$stmt->execute([$submission_id]);
$submission = $stmt->fetch();

if (!$submission || (int)$submission['created_by'] !== (int)$_SESSION['user_id']) {
    header('Location: grades.php');
    exit;
}

// Handle posting feedback (separate small form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_text'])) {
    $feedback_text = trim($_POST['feedback_text']);
    if ($feedback_text !== '') {
        $pdo->prepare(
            'INSERT INTO exam_feedback (submission_id, reviewer_id, feedback_text) VALUES (?, ?, ?)'
        )->execute([$submission_id, $_SESSION['user_id'], $feedback_text]);
    }
    header('Location: grade_submission.php?id=' . $submission_id);
    exit;
}

// Handle grading essay questions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['score'])) {
    $scores = $_POST['score'] ?? []; // question_id => points

    $pdo->beginTransaction();
    try {
        foreach ($scores as $question_id => $val) {
            $val = ($val === '') ? null : (float)$val;
            $pdo->prepare(
                'UPDATE exam_question_scores SET points_earned = ?, graded_at = NOW()
                 WHERE submission_id = ? AND question_id = ?'
            )->execute([$val, $submission_id, (int)$question_id]);
        }

        // Recompute total; grading_status becomes "graded" only if no NULLs remain
        $pending_check = $pdo->prepare(
            'SELECT COUNT(*) FROM exam_question_scores WHERE submission_id = ? AND points_earned IS NULL'
        );
        $pending_check->execute([$submission_id]);
        $still_pending = (int)$pending_check->fetchColumn() > 0;

        $sum_stmt = $pdo->prepare(
            'SELECT SUM(points_earned) FROM exam_question_scores WHERE submission_id = ?'
        );
        $sum_stmt->execute([$submission_id]);
        $total = $sum_stmt->fetchColumn();

        $pdo->prepare(
            'UPDATE exam_submissions SET total_score = ?, grading_status = ? WHERE id = ?'
        )->execute([
            $still_pending ? null : $total,
            $still_pending ? 'pending' : 'graded',
            $submission_id
        ]);

        $pdo->commit();
        header('Location: grades.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Something went wrong while saving grades. Please try again.';
    }
}

// Load questions + this student's answers
$q_stmt = $pdo->prepare('SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY sort_order ASC');
$q_stmt->execute([$submission['exam_id']]);
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

    $score_stmt = $pdo->prepare('SELECT points_earned FROM exam_question_scores WHERE submission_id = ? AND question_id = ?');
    $score_stmt->execute([$submission_id, $q['id']]);
    $q['points_earned'] = $score_stmt->fetchColumn();
}
unset($q);

// Load feedback thread (consultant + admin comments)
$fb_stmt = $pdo->prepare(
    'SELECT f.*, u.full_name AS reviewer_name, u.role AS reviewer_role
     FROM exam_feedback f
     JOIN users u ON u.id = f.reviewer_id
     WHERE f.submission_id = ?
     ORDER BY f.created_at ASC'
);
$fb_stmt->execute([$submission_id]);
$feedback_list = $fb_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Grade Submission - Admin</title>
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
    .sidebar .section-label { padding: 18px 24px 6px 24px; font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase; color: #8fa3c8; }
    .sidebar .bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px; }

    .main { flex: 1; padding: 30px 40px; max-width: 780px; }
    .back-link { color:#3b6fd6; font-size: 13px; text-decoration:none; }
    .main h2 { color: #1a3a6b; margin: 10px 0 2px 0; }
    .main .meta { color: #6b7280; font-size: 14px; margin-bottom: 24px; }

    .question-card {
        background: #fff; border-radius: 8px; padding: 22px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 18px;
    }
    .question-top { display:flex; justify-content:space-between; align-items:flex-start; gap: 14px; margin-bottom: 14px; }
    .question-text { font-size: 15px; font-weight: bold; color: #1e1e1e; }
    .points-badge { font-size: 12px; color: #6b7280; white-space: nowrap; }

    .answer-block { background: #f8f9fb; border-radius: 6px; padding: 14px; font-size: 14px; color: #333; margin-bottom: 14px; white-space: pre-wrap; }

    .option-row { display:flex; align-items:center; gap: 10px; padding: 6px 0; font-size: 14px; }
    .option-row .icon { width: 18px; text-align: center; font-weight:bold; }
    .option-row.correct .icon { color: #1e7a34; }
    .option-row.selected-wrong .icon { color: #b3261e; }
    .option-row.selected-correct { color: #1e7a34; font-weight:bold; }
    .option-row.selected-wrong { color: #b3261e; font-weight:bold; }

    .grade-input { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
    .grade-input label { font-size: 12px; color: #4b5563; }
    .grade-input input {
        width: 80px; padding: 8px 10px; border: 1px solid #d1d5db;
        border-radius: 5px; font-size: 14px;
    }

    .auto-graded-note { font-size: 12px; color: #1e7a34; margin-top: 10px; }

    .submit-bar { margin-top: 10px; }
    button {
        padding: 14px 28px; border: none; border-radius: 5px;
        background: #4a7fd4; color: #fff; font-size: 15px; font-weight: bold; cursor: pointer;
    }
    button:hover { background: #3c6cc0; }
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
        <a href="dashboard.php">Dashboard</a>
        <a href="manage_users.php">Users / Roster</a>
        <a href="create_user.php">Add New User</a>
        <a href="assign_evaluations.php">Assign Evaluations</a>
        <a href="exams.php">Exams</a>
        <a href="grades.php" class="active">Grades</a>
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
        <a class="back-link" href="grades.php">&larr; Back to Grades</a>
        <h2><?= htmlspecialchars($submission['exam_title']) ?></h2>
        <p class="meta">Student: <strong><?= htmlspecialchars($submission['student_name']) ?></strong></p>

        <form method="POST" action="grade_submission.php?id=<?= (int)$submission_id ?>">
            <?php foreach ($questions as $i => $q): ?>
                <div class="question-card">
                    <div class="question-top">
                        <div class="question-text"><?= ($i + 1) ?>. <?= htmlspecialchars($q['question_text']) ?></div>
                        <div class="points-badge"><?= (int)$q['points'] ?> pt<?= $q['points'] == 1 ? '' : 's' ?></div>
                    </div>

                    <?php if ($q['question_type'] === 'essay'): ?>
                        <div class="answer-block"><?= htmlspecialchars($q['answer_text'] ?: '(no answer submitted)') ?></div>
                        <div class="grade-input">
                            <label>Score:</label>
                            <input type="number" step="0.5" min="0" max="<?= (int)$q['points'] ?>"
                                   name="score[<?= $q['id'] ?>]"
                                   value="<?= $q['points_earned'] !== false && $q['points_earned'] !== null ? htmlspecialchars($q['points_earned']) : '' ?>">
                            <span>/ <?= (int)$q['points'] ?></span>
                        </div>

                    <?php else: ?>
                        <?php foreach ($q['options'] as $opt):
                            $isCorrect = (int)$opt['is_correct'] === 1;
                            $isSelected = in_array($opt['id'], $q['selected']);
                            $rowClass = $isCorrect ? 'correct' : '';
                            if ($isSelected) { $rowClass .= $isCorrect ? ' selected-correct' : ' selected-wrong'; }
                        ?>
                            <div class="option-row <?= $rowClass ?>">
                                <span class="icon"><?= $isSelected ? (($isCorrect) ? '&#10003;' : '&#10007;') : '' ?></span>
                                <span><?= htmlspecialchars($opt['option_text']) ?></span>
                                <?php if ($isCorrect && !$isSelected): ?><em style="font-size:12px;color:#9ca3af;">(correct answer)</em><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="auto-graded-note">
                            Auto-graded &middot; <?= number_format((float)$q['points_earned'], 1) ?> / <?= (int)$q['points'] ?> pts
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="submit-bar">
                <button type="submit">Save Grades</button>
            </div>
        </form>

        <h3 style="color:#1a3a6b; margin-top: 30px;">Feedback</h3>
        <div class="feedback-thread" style="margin-bottom: 20px;">
            <?php if ($feedback_list): ?>
                <?php foreach ($feedback_list as $fb): ?>
                    <div class="question-card">
                        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                            <strong style="color:#1a3a6b; font-size:13px;">
                                <?= htmlspecialchars($fb['reviewer_name']) ?>
                                <span style="font-size:11px; color:#6b7280; text-transform:uppercase; margin-left:6px;"><?= htmlspecialchars($fb['reviewer_role']) ?></span>
                            </strong>
                            <span style="font-size:12px; color:#9ca3af;"><?= htmlspecialchars($fb['created_at']) ?></span>
                        </div>
                        <div style="font-size:14px; color:#333; white-space:pre-wrap;"><?= htmlspecialchars($fb['feedback_text']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="question-card" style="text-align:center; color:#6b7280;">No feedback yet.</div>
            <?php endif; ?>
        </div>

        <form method="POST" action="grade_submission.php?id=<?= (int)$submission_id ?>" class="question-card">
            <textarea name="feedback_text" placeholder="Write feedback for this trainee..." required
                      style="width:100%; min-height:80px; padding:10px 12px; border:1px solid #d1d5db; border-radius:5px; font-size:14px; font-family:inherit; resize:vertical;"></textarea>
            <button type="submit" style="margin-top:12px;">Post Feedback</button>
        </form>
    </div>
</div>
</body>
</html>
