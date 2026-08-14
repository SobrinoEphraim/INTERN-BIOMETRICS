<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role(['trainee', 'consultant', 'rater']);
require_once __DIR__ . '/../config/db_connect.php';

$exam_id = (int)($_GET['id'] ?? 0);

// Load exam and make sure it's published and targeted to this user's role
$stmt = $pdo->prepare(
    'SELECT * FROM exams
     WHERE id = ? AND status = "published" AND (target_role = ? OR target_role = "all")'
);
$stmt->execute([$exam_id, $_SESSION['role']]);
$exam = $stmt->fetch();

if (!$exam) {
    header('Location: exams.php');
    exit;
}

// Block re-taking if already submitted
$sub_check = $pdo->prepare('SELECT * FROM exam_submissions WHERE exam_id = ? AND user_id = ?');
$sub_check->execute([$exam_id, $_SESSION['user_id']]);
$existing_submission = $sub_check->fetch();

if ($existing_submission && $existing_submission['status'] === 'submitted') {
    header('Location: exams.php');
    exit;
}

// Load questions + options
$q_stmt = $pdo->prepare('SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY sort_order ASC');
$q_stmt->execute([$exam_id]);
$questions = $q_stmt->fetchAll();

foreach ($questions as &$q) {
    if ($q['question_type'] !== 'essay') {
        $o_stmt = $pdo->prepare('SELECT * FROM exam_options WHERE question_id = ? ORDER BY sort_order ASC');
        $o_stmt->execute([$q['id']]);
        $q['options'] = $o_stmt->fetchAll();
    } else {
        $q['options'] = [];
    }
}
unset($q);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers_text    = $_POST['answer_text'] ?? [];
    $answers_choice  = $_POST['answer_choice'] ?? [];   // multiple_choice: question_id => option_id
    $answers_checkbox = $_POST['answer_checkbox'] ?? []; // checkbox: question_id => [option_id, ...]

    // Basic validation: every question must have an answer
    $missing = false;
    foreach ($questions as $q) {
        if ($q['question_type'] === 'essay') {
            if (trim($answers_text[$q['id']] ?? '') === '') { $missing = true; break; }
        } elseif ($q['question_type'] === 'multiple_choice') {
            if (empty($answers_choice[$q['id']])) { $missing = true; break; }
        } elseif ($q['question_type'] === 'checkbox') {
            if (empty($answers_checkbox[$q['id']])) { $missing = true; break; }
        }
    }

    if ($missing) {
        $error = 'Please answer every question before submitting.';
    } else {
        $pdo->beginTransaction();
        try {
            if ($existing_submission) {
                $submission_id = $existing_submission['id'];
                $pdo->prepare('DELETE FROM exam_answers WHERE submission_id = ?')->execute([$submission_id]);
                $pdo->prepare('DELETE FROM exam_answer_options WHERE submission_id = ?')->execute([$submission_id]);
                $pdo->prepare('UPDATE exam_submissions SET status = "submitted", submitted_at = NOW() WHERE id = ?')
                    ->execute([$submission_id]);
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO exam_submissions (exam_id, user_id, status, submitted_at) VALUES (?, ?, "submitted", NOW())'
                );
                $ins->execute([$exam_id, $_SESSION['user_id']]);
                $submission_id = (int) $pdo->lastInsertId();
            }

            $text_ins = $pdo->prepare(
                'INSERT INTO exam_answers (submission_id, question_id, answer_text) VALUES (?, ?, ?)'
            );
            $opt_ins = $pdo->prepare(
                'INSERT INTO exam_answer_options (submission_id, question_id, option_id) VALUES (?, ?, ?)'
            );

            foreach ($questions as $q) {
                if ($q['question_type'] === 'essay') {
                    $text_ins->execute([$submission_id, $q['id'], trim($answers_text[$q['id']])]);
                } elseif ($q['question_type'] === 'multiple_choice') {
                    $opt_ins->execute([$submission_id, $q['id'], (int)$answers_choice[$q['id']]]);
                } elseif ($q['question_type'] === 'checkbox') {
                    foreach ($answers_checkbox[$q['id']] as $opt_id) {
                        $opt_ins->execute([$submission_id, $q['id'], (int)$opt_id]);
                    }
                }
            }

            // ----------------------------------------------------
            // Auto-grade multiple_choice / checkbox questions.
            // Essay questions are left ungraded (NULL) until an
            // admin reviews them on the Grades page.
            // ----------------------------------------------------
            $pdo->prepare('DELETE FROM exam_question_scores WHERE submission_id = ?')->execute([$submission_id]);

            $score_ins = $pdo->prepare(
                'INSERT INTO exam_question_scores (submission_id, question_id, points_earned, graded_at)
                 VALUES (?, ?, ?, ?)'
            );

            $max_score = 0;
            $has_pending = false;
            $total_score = 0;

            foreach ($questions as $q) {
                $max_score += (int)$q['points'];

                if ($q['question_type'] === 'essay') {
                    $score_ins->execute([$submission_id, $q['id'], null, null]);
                    $has_pending = true;
                    continue;
                }

                $correct_ids = [];
                foreach ($q['options'] as $opt) {
                    if ((int)$opt['is_correct'] === 1) {
                        $correct_ids[] = (int)$opt['id'];
                    }
                }

                if ($q['question_type'] === 'multiple_choice') {
                    $selected = [(int)$answers_choice[$q['id']]];
                } else {
                    $selected = array_map('intval', $answers_checkbox[$q['id']]);
                }

                sort($correct_ids);
                sort($selected);
                $is_fully_correct = ($correct_ids === $selected);
                $points_earned = $is_fully_correct ? (int)$q['points'] : 0;

                $score_ins->execute([$submission_id, $q['id'], $points_earned, date('Y-m-d H:i:s')]);
                $total_score += $points_earned;
            }

            $grading_status = $has_pending ? 'pending' : 'graded';
            $final_total = $has_pending ? null : $total_score;

            $pdo->prepare(
                'UPDATE exam_submissions SET max_score = ?, total_score = ?, grading_status = ? WHERE id = ?'
            )->execute([$max_score, $final_total, $grading_status, $submission_id]);

            $pdo->commit();
            header('Location: exams.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Something went wrong while submitting your exam. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($exam['title']) ?> - Trainee Evaluation System</title>
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

    .main { flex: 1; padding: 30px 40px; max-width: 760px; }
    .back-link { color:#3b6fd6; font-size: 13px; text-decoration:none; }
    .main h2 { color: #1a3a6b; margin: 10px 0 4px 0; }
    .main .desc { color: #6b7280; font-size: 14px; margin-bottom: 24px; }

    .error-box { background:#fdecea; color:#b3261e; border:1px solid #f5c2c0; border-radius:5px; padding:10px 14px; font-size:13px; margin-bottom:16px; }

    .question-card {
        background: #fff; border-radius: 8px; padding: 22px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 18px;
    }
    .question-text { font-size: 15px; font-weight: bold; color: #1e1e1e; margin-bottom: 14px; }

    textarea {
        width: 100%; padding: 10px 12px; border: 1px solid #d1d5db;
        border-radius: 5px; font-size: 14px; font-family: inherit;
        resize: vertical; min-height: 80px;
    }

    .option-choice { display: flex; align-items: center; gap: 10px; padding: 10px 0; }
    .option-choice input { width: 18px; height: 18px; }
    .option-choice label { font-size: 14px; color: #333; cursor: pointer; }

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
        <a class="back-link" href="exams.php">&larr; Back to Exams</a>
        <h2><?= htmlspecialchars($exam['title']) ?></h2>
        <?php if ($exam['description']): ?>
            <p class="desc"><?= htmlspecialchars($exam['description']) ?></p>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="take_exam.php?id=<?= (int)$exam_id ?>">
            <?php foreach ($questions as $i => $q): ?>
                <div class="question-card">
                    <div class="question-text"><?= ($i + 1) ?>. <?= htmlspecialchars($q['question_text']) ?></div>

                    <?php if ($q['question_type'] === 'essay'): ?>
                        <textarea name="answer_text[<?= $q['id'] ?>]" placeholder="Type your answer here..."></textarea>

                    <?php elseif ($q['question_type'] === 'multiple_choice'): ?>
                        <?php foreach ($q['options'] as $opt): ?>
                            <div class="option-choice">
                                <input type="radio"
                                       id="opt<?= $opt['id'] ?>"
                                       name="answer_choice[<?= $q['id'] ?>]"
                                       value="<?= $opt['id'] ?>">
                                <label for="opt<?= $opt['id'] ?>"><?= htmlspecialchars($opt['option_text']) ?></label>
                            </div>
                        <?php endforeach; ?>

                    <?php elseif ($q['question_type'] === 'checkbox'): ?>
                        <?php foreach ($q['options'] as $opt): ?>
                            <div class="option-choice">
                                <input type="checkbox"
                                       id="opt<?= $opt['id'] ?>"
                                       name="answer_checkbox[<?= $q['id'] ?>][]"
                                       value="<?= $opt['id'] ?>">
                                <label for="opt<?= $opt['id'] ?>"><?= htmlspecialchars($opt['option_text']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="submit-bar">
                <button type="submit">Submit Exam</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
