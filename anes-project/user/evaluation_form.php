<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role(['trainee', 'consultant', 'rater']);
require_once __DIR__ . '/../config/db_connect.php';

$assignment_id = (int)($_GET['id'] ?? 0);

// Load the assignment and make sure it belongs to the logged-in user
$stmt = $pdo->prepare(
    'SELECT a.*, u.full_name AS ratee_name
     FROM evaluation_assignments a
     JOIN users u ON u.id = a.ratee_id
     WHERE a.id = ? AND a.rater_id = ?'
);
$stmt->execute([$assignment_id, $_SESSION['user_id']]);
$assignment = $stmt->fetch();

if (!$assignment) {
    header('Location: rate_peers.php');
    exit;
}

if ($assignment['status'] === 'submitted') {
    header('Location: rate_peers.php');
    exit;
}

// Load the questions for this form type
$q_stmt = $pdo->prepare(
    'SELECT * FROM evaluation_questions WHERE form_type = ? ORDER BY sort_order ASC'
);
$q_stmt->execute([$assignment['form_type']]);
$questions = $q_stmt->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ratings  = $_POST['rating']  ?? [];
    $comments = $_POST['comment'] ?? [];

    $missing = false;
    foreach ($questions as $q) {
        if (empty($ratings[$q['id']])) {
            $missing = true;
            break;
        }
    }

    if ($missing) {
        $error = 'Please provide a rating for every question before submitting.';
    } else {
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO evaluation_answers (assignment_id, question_id, rating, comment)
                 VALUES (?, ?, ?, ?)'
            );
            foreach ($questions as $q) {
                $rating  = (int) $ratings[$q['id']];
                $comment = trim($comments[$q['id']] ?? '');
                $ins->execute([$assignment_id, $q['id'], $rating, $comment !== '' ? $comment : null]);
            }

            $upd = $pdo->prepare(
                'UPDATE evaluation_assignments SET status = "submitted", submitted_at = NOW() WHERE id = ?'
            );
            $upd->execute([$assignment_id]);

            $pdo->commit();
            header('Location: rate_peers.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Something went wrong while saving your evaluation. Please try again.';
        }
    }
}

$form_labels = [
    'peer'       => 'Peer Evaluation',
    'trainee'    => 'Trainee Evaluation',
    'consultant' => 'Consultant Evaluation',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Evaluation Form - Trainee Evaluation System</title>
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
    .main h2 { color: #1a3a6b; margin: 10px 0 2px 0; }
    .main .meta { color: #6b7280; font-size: 13px; margin-bottom: 24px; }

    .error-box { background:#fdecea; color:#b3261e; border:1px solid #f5c2c0; border-radius:5px; padding:10px 14px; font-size:13px; margin-bottom:16px; }

    .question-card {
        background: #fff; border-radius: 8px; padding: 22px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 18px;
    }
    .question-text { font-size: 15px; font-weight: bold; color: #1e1e1e; margin-bottom: 14px; }

    .rating-scale { display: flex; gap: 10px; margin-bottom: 14px; }
    .rating-option { flex: 1; text-align: center; }
    .rating-option input { display: none; }
    .rating-option label {
        display: block; padding: 10px 0; border: 1px solid #d1d5db;
        border-radius: 5px; cursor: pointer; font-size: 13px; color: #4b5563;
        transition: all 0.15s ease;
    }
    .rating-option input:checked + label {
        background: #4a7fd4; border-color: #4a7fd4; color: #fff; font-weight: bold;
    }
    .scale-caption { display:flex; justify-content:space-between; font-size:11px; color:#9ca3af; margin-bottom:16px; }

    textarea {
        width: 100%; padding: 10px 12px; border: 1px solid #d1d5db;
        border-radius: 5px; font-size: 13px; font-family: inherit;
        resize: vertical; min-height: 60px;
    }
    textarea::placeholder { color: #9ca3af; }

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
        <a href="rate_peers.php" class="active">Rate Peers</a>
        <a href="exams.php">Exams</a>
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
        <a class="back-link" href="rate_peers.php">&larr; Back to my evaluations</a>
        <h2><?= htmlspecialchars($form_labels[$assignment['form_type']] ?? $assignment['form_type']) ?></h2>
        <p class="meta">Rating: <strong><?= htmlspecialchars($assignment['ratee_name']) ?></strong></p>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="evaluation_form.php?id=<?= (int)$assignment_id ?>">
            <?php foreach ($questions as $q): ?>
                <div class="question-card">
                    <div class="question-text"><?= htmlspecialchars($q['question_text']) ?></div>

                    <div class="rating-scale">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <div class="rating-option">
                                <input type="radio"
                                       id="q<?= $q['id'] ?>_r<?= $i ?>"
                                       name="rating[<?= $q['id'] ?>]"
                                       value="<?= $i ?>" required>
                                <label for="q<?= $q['id'] ?>_r<?= $i ?>"><?= $i ?></label>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <div class="scale-caption">
                        <span>1 &middot; Needs improvement</span>
                        <span>5 &middot; Excellent</span>
                    </div>

                    <textarea name="comment[<?= $q['id'] ?>]" placeholder="Optional comment..."></textarea>
                </div>
            <?php endforeach; ?>

            <div class="submit-bar">
                <button type="submit">Submit Evaluation</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
