<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $exam_id > 0;
$error = '';

// ------------------------------------------------------------
// Load existing exam (edit mode) — must belong to this admin
// ------------------------------------------------------------
$exam = ['title' => '', 'description' => '', 'target_role' => 'trainee', 'quarter' => '', 'week_number' => '', 'category' => 'written'];
$existing_questions = [];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM exams WHERE id = ? AND created_by = ?');
    $stmt->execute([$exam_id, $_SESSION['user_id']]);
    $found = $stmt->fetch();

    if (!$found) {
        header('Location: exams.php');
        exit;
    }
    $exam = $found;

    $q_stmt = $pdo->prepare('SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY sort_order ASC');
    $q_stmt->execute([$exam_id]);
    $questions = $q_stmt->fetchAll();

    foreach ($questions as $q) {
        $opts = [];
        if ($q['question_type'] !== 'essay') {
            $o_stmt = $pdo->prepare('SELECT option_text, is_correct FROM exam_options WHERE question_id = ? ORDER BY sort_order ASC');
            $o_stmt->execute([$q['id']]);
            foreach ($o_stmt->fetchAll() as $o) {
                $opts[] = ['text' => $o['option_text'], 'correct' => (bool)$o['is_correct']];
            }
        }
        $existing_questions[] = [
            'text'    => $q['question_text'],
            'type'    => $q['question_type'],
            'points'  => (int)$q['points'],
            'options' => $opts,
        ];
    }
}

// ------------------------------------------------------------
// Handle save (create or update)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $target_role = $_POST['target_role'] ?? 'trainee';
    $action      = $_POST['action'] ?? 'draft'; // draft | publish
    $questions_in = $_POST['questions'] ?? [];
    $quarter     = $_POST['quarter'] ?? '';
    $week_number = $_POST['week_number'] ?? '';
    $category    = $_POST['category'] ?? 'written';

    $quarter     = in_array($quarter, ['1', '2', '3', '4'], true) ? $quarter : null;
    $week_number = ($quarter && $week_number !== '') ? max(1, min(10, (int)$week_number)) : null;
    $category    = in_array($category, ['written', 'clinical', 'behavioral'], true) ? $category : 'written';

    $allowed_roles = ['trainee', 'consultant', 'rater', 'all'];
    $status = ($action === 'publish') ? 'published' : 'draft';

    if ($title === '') {
        $error = 'Please enter an exam title.';
    } elseif (!in_array($target_role, $allowed_roles, true)) {
        $error = 'Please select who this exam is for.';
    } elseif ($quarter && !$week_number) {
        $error = 'Please select a week number (1-10) for this quarter.';
    } elseif (empty($questions_in)) {
        $error = 'Please add at least one question.';
    } else {
        // Validate each question
        foreach ($questions_in as $q) {
            $qtext = trim($q['text'] ?? '');
            $qtype = $q['type'] ?? 'essay';
            if ($qtext === '') {
                $error = 'Every question needs text.';
                break;
            }
            if (in_array($qtype, ['multiple_choice', 'checkbox'], true)) {
                $opts_raw = $q['options'] ?? [];
                $non_empty = array_filter($opts_raw, function ($o) {
                    return trim($o['text'] ?? '') !== '';
                });
                if (count($non_empty) < 2) {
                    $error = 'Multiple choice / checkbox questions need at least 2 options.';
                    break;
                }
                if ($qtype === 'multiple_choice') {
                    if (!isset($q['correct_option']) || $q['correct_option'] === '') {
                        $error = 'Please mark the correct answer for every multiple choice question.';
                        break;
                    }
                } else { // checkbox
                    $has_correct = false;
                    foreach ($opts_raw as $o) {
                        if (!empty($o['correct'])) { $has_correct = true; break; }
                    }
                    if (!$has_correct) {
                        $error = 'Please mark at least one correct answer for every checkbox question.';
                        break;
                    }
                }
            }
        }

        if ($error === '') {
            $pdo->beginTransaction();
            try {
                if ($is_edit) {
                    $stmt = $pdo->prepare(
                        'UPDATE exams SET title = ?, description = ?, target_role = ?, status = ?, quarter = ?, week_number = ?, category = ? WHERE id = ?'
                    );
                    $stmt->execute([$title, $description, $target_role, $status, $quarter, $week_number, $category, $exam_id]);

                    // wipe old questions (options cascade automatically)
                    $pdo->prepare('DELETE FROM exam_questions WHERE exam_id = ?')->execute([$exam_id]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO exams (title, description, target_role, status, created_by, quarter, week_number, category)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$title, $description, $target_role, $status, $_SESSION['user_id'], $quarter, $week_number, $category]);
                    $exam_id = (int) $pdo->lastInsertId();
                }

                $q_ins = $pdo->prepare(
                    'INSERT INTO exam_questions (exam_id, question_text, question_type, sort_order, points) VALUES (?, ?, ?, ?, ?)'
                );
                $o_ins = $pdo->prepare(
                    'INSERT INTO exam_options (question_id, option_text, sort_order, is_correct) VALUES (?, ?, ?, ?)'
                );

                $order = 0;
                foreach ($questions_in as $q) {
                    $qtext = trim($q['text']);
                    $qtype = in_array($q['type'], ['essay', 'multiple_choice', 'checkbox'], true) ? $q['type'] : 'essay';
                    $points = max(1, (int)($q['points'] ?? 1));

                    $q_ins->execute([$exam_id, $qtext, $qtype, $order, $points]);
                    $question_id = (int) $pdo->lastInsertId();

                    if ($qtype !== 'essay') {
                        $opt_order = 0;
                        $correct_idx = $q['correct_option'] ?? null;

                        foreach (($q['options'] ?? []) as $opt_key => $opt_data) {
                            $opt_text = trim($opt_data['text'] ?? '');
                            if ($opt_text === '') continue;

                            if ($qtype === 'multiple_choice') {
                                $is_correct = ((string)$opt_key === (string)$correct_idx) ? 1 : 0;
                            } else { // checkbox
                                $is_correct = !empty($opt_data['correct']) ? 1 : 0;
                            }

                            $o_ins->execute([$question_id, $opt_text, $opt_order, $is_correct]);
                            $opt_order++;
                        }
                    }
                    $order++;
                }

                $pdo->commit();
                header('Location: exams.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Something went wrong while saving the exam. Please try again.';
            }
        }
    }

    // keep whatever the admin typed if there was an error
    $exam['title'] = $title;
    $exam['description'] = $description;
    $exam['target_role'] = $target_role;
    $exam['quarter'] = $quarter ?? '';
    $exam['week_number'] = $week_number ?? '';
    $exam['category'] = $category ?? 'written';

    // Re-normalize for JS re-render on error
    $existing_questions = [];
    foreach ($questions_in as $q) {
        $opts = [];
        $correct_idx = $q['correct_option'] ?? null;
        foreach (($q['options'] ?? []) as $opt_key => $opt_data) {
            $is_correct = ($q['type'] === 'multiple_choice')
                ? ((string)$opt_key === (string)$correct_idx)
                : !empty($opt_data['correct']);
            $opts[] = ['text' => $opt_data['text'] ?? '', 'correct' => (bool)$is_correct];
        }
        $existing_questions[] = [
            'text'    => $q['text'] ?? '',
            'type'    => $q['type'] ?? 'essay',
            'points'  => (int)($q['points'] ?? 1),
            'options' => $opts,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $is_edit ? 'Edit Exam' : 'Create Exam' ?> - Admin</title>
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
    .main h2 { color: #1a3a6b; margin: 10px 0 20px 0; }

    .error-box { background:#fdecea; color:#b3261e; border:1px solid #f5c2c0; border-radius:5px; padding:10px 14px; font-size:13px; margin-bottom:16px; }

    .panel { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .form-group { margin-bottom: 18px; }
    label { display:block; font-size:12px; font-weight:bold; color:#4b5563; margin-bottom:6px; text-transform:uppercase; }
    input[type=text], input[type=number], textarea, select {
        width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
        border-radius: 5px; font-size: 14px; font-family: inherit;
    }
    textarea { resize: vertical; min-height: 50px; }

    .question-card {
        background: #fff; border-radius: 8px; padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 16px;
        border-left: 4px solid #4a90d9;
    }
    .question-top { display: flex; gap: 12px; margin-bottom: 14px; }
    .question-top textarea { flex: 1; }
    .type-select { width: 190px; flex-shrink: 0; }
    .points-input { width: 90px; flex-shrink: 0; }
    .points-input label { font-size: 10px; margin-bottom: 4px; }

    .options-list { margin-bottom: 10px; }
    .option-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
    .option-row input[type=text] { flex: 1; }
    .option-row .correct-marker { width: 18px; height: 18px; flex-shrink: 0; cursor: pointer; }
    .option-row .remove-option {
        color: #b3261e; cursor: pointer; font-size: 13px; background: none; border: none; padding: 4px 8px;
    }
    .options-hint { font-size: 11px; color: #9ca3af; margin-bottom: 8px; }
    .add-option-btn {
        background: none; border: 1px dashed #9ca3af; color: #4b5563;
        padding: 8px 14px; border-radius: 5px; font-size: 13px; cursor: pointer;
    }
    .add-option-btn:hover { background: #f8f9fb; }

    .question-footer { display: flex; justify-content: flex-end; margin-top: 10px; }
    .remove-question-btn {
        background: none; border: none; color: #b3261e; font-size: 13px; cursor: pointer;
    }

    .add-question-btn {
        display: block; width: 100%; background: #fff; border: 2px dashed #4a90d9;
        color: #2b5cad; padding: 16px; border-radius: 8px; font-size: 14px; font-weight: bold;
        cursor: pointer; margin-bottom: 20px;
    }
    .add-question-btn:hover { background: #f0f6fd; }

    .save-bar { display: flex; gap: 10px; }
    button.primary {
        padding: 14px 24px; border: none; border-radius: 5px;
        background: #4a7fd4; color: #fff; font-size: 15px; font-weight: bold; cursor: pointer;
    }
    button.primary:hover { background: #3c6cc0; }
    button.secondary {
        padding: 14px 24px; border: 1px solid #d1d5db; border-radius: 5px;
        background: #fff; color: #4b5563; font-size: 15px; font-weight: bold; cursor: pointer;
    }
    button.secondary:hover { background: #f8f9fb; }
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
        <a href="exams.php" class="active">Exams</a>
        <a href="grades.php">Grades</a>
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
        <a class="back-link" href="exams.php">&larr; Back to Exams</a>
        <h2><?= $is_edit ? 'Edit Exam' : 'Create New Exam' ?></h2>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="exam_builder.php<?= $is_edit ? '?id=' . (int)$exam_id : '' ?>" id="examForm">
            <div class="panel">
                <div class="form-group">
                    <label for="title">Exam Title</label>
                    <input type="text" id="title" name="title" required
                           value="<?= htmlspecialchars($exam['title']) ?>">
                </div>
                <div class="form-group">
                    <label for="description">Description (optional)</label>
                    <textarea id="description" name="description"><?= htmlspecialchars($exam['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="target_role">Who is this exam for?</label>
                    <select id="target_role" name="target_role">
                        <option value="trainee"    <?= $exam['target_role'] === 'trainee' ? 'selected' : '' ?>>Trainees (Residents/Fellows)</option>
                        <option value="consultant" <?= $exam['target_role'] === 'consultant' ? 'selected' : '' ?>>Consultants</option>
                        <option value="rater"      <?= $exam['target_role'] === 'rater' ? 'selected' : '' ?>>Raters</option>
                        <option value="all"        <?= $exam['target_role'] === 'all' ? 'selected' : '' ?>>All Roles</option>
                    </select>
                </div>
            </div>

            <div class="panel">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Add to Class Record? (optional)</label>
                    <p style="font-size:12px; color:#6b7280; margin: 0 0 12px 0;">
                        If this is a weekly written evaluation / quiz, tag it with a quarter and week
                        so its score automatically appears in the Class Record.
                    </p>
                    <div style="display:flex; gap:14px;">
                        <div style="flex:1;">
                            <label style="font-size:11px;">Quarter</label>
                            <select name="quarter">
                                <option value="">Not part of Class Record</option>
                                <option value="1" <?= ($exam['quarter'] ?? '') === '1' ? 'selected' : '' ?>>1st Quarter</option>
                                <option value="2" <?= ($exam['quarter'] ?? '') === '2' ? 'selected' : '' ?>>2nd Quarter</option>
                                <option value="3" <?= ($exam['quarter'] ?? '') === '3' ? 'selected' : '' ?>>3rd Quarter</option>
                                <option value="4" <?= ($exam['quarter'] ?? '') === '4' ? 'selected' : '' ?>>4th Quarter</option>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:11px;">Week Number (1-10)</label>
                            <input type="number" name="week_number" min="1" max="10" value="<?= htmlspecialchars($exam['week_number'] ?? '') ?>">
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:11px;">Category</label>
                            <select name="category">
                                <option value="written"    <?= ($exam['category'] ?? 'written') === 'written' ? 'selected' : '' ?>>Written Evaluation (40%)</option>
                                <option value="clinical"   <?= ($exam['category'] ?? '') === 'clinical' ? 'selected' : '' ?>>Clinical Performance Task (40%)</option>
                                <option value="behavioral" <?= ($exam['category'] ?? '') === 'behavioral' ? 'selected' : '' ?>>Behavioral Assessment (20%)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div id="questionsContainer"></div>

            <button type="button" class="add-question-btn" onclick="addQuestion()">+ Add Question</button>

            <div class="save-bar">
                <button type="submit" name="action" value="draft" class="secondary">Save as Draft</button>
                <button type="submit" name="action" value="publish" class="primary">Publish</button>
            </div>
        </form>
    </div>
</div>

<script>
    let questionIndex = 0;
    const container = document.getElementById('questionsContainer');
    const existingQuestions = <?= json_encode(array_values($existing_questions)) ?>;

    function addQuestion(data) {
        const idx = questionIndex++;
        const wrapper = document.createElement('div');
        wrapper.className = 'question-card';
        wrapper.dataset.idx = idx;
        wrapper.dataset.optIdx = 0;

        const qType = data ? data.type : 'essay';
        const points = data ? data.points : 1;

        wrapper.innerHTML = `
            <div class="question-top">
                <textarea name="questions[${idx}][text]" placeholder="Type your question here..." required>${data ? escapeHtml(data.text) : ''}</textarea>
                <select class="type-select" name="questions[${idx}][type]" onchange="toggleOptions(this)">
                    <option value="essay" ${qType === 'essay' ? 'selected' : ''}>Essay / Free Text</option>
                    <option value="multiple_choice" ${qType === 'multiple_choice' ? 'selected' : ''}>Multiple Choice</option>
                    <option value="checkbox" ${qType === 'checkbox' ? 'selected' : ''}>Checkboxes</option>
                </select>
                <div class="points-input">
                    <label>Points</label>
                    <input type="number" min="1" name="questions[${idx}][points]" value="${points}">
                </div>
            </div>
            <div class="options-hint" style="display:${qType === 'essay' ? 'none' : 'block'};">Tick the correct answer(s) on the left of each option.</div>
            <div class="options-list" style="display:${qType === 'essay' ? 'none' : 'block'};"></div>
            <button type="button" class="add-option-btn" style="display:${qType === 'essay' ? 'none' : 'inline-block'};" onclick="addOption(this)">+ Add Option</button>
            <div class="question-footer">
                <button type="button" class="remove-question-btn" onclick="this.closest('.question-card').remove()">Remove Question</button>
            </div>
        `;

        container.appendChild(wrapper);

        if (data && data.options && data.options.length) {
            data.options.forEach(opt => addOption(wrapper.querySelector('.add-option-btn'), opt.text, opt.correct));
        } else if (qType !== 'essay') {
            addOption(wrapper.querySelector('.add-option-btn'));
            addOption(wrapper.querySelector('.add-option-btn'));
        }
    }

    function toggleOptions(selectEl) {
        const card = selectEl.closest('.question-card');
        const optsList = card.querySelector('.options-list');
        const hint = card.querySelector('.options-hint');
        const addBtn = card.querySelector('.add-option-btn');
        const isEssay = selectEl.value === 'essay';

        optsList.style.display = isEssay ? 'none' : 'block';
        hint.style.display = isEssay ? 'none' : 'block';
        addBtn.style.display = isEssay ? 'none' : 'inline-block';

        if (!isEssay && optsList.children.length === 0) {
            addOption(addBtn);
            addOption(addBtn);
        }

        // Rebuild every correct-marker input so its type/name matches the new question type
        rebuildMarkers(card);
    }

    function rebuildMarkers(card) {
        const idx = card.dataset.idx;
        const type = card.querySelector('.type-select').value;
        const rows = card.querySelectorAll('.option-row');

        rows.forEach(row => {
            const optKey = row.dataset.optKey;
            const wasChecked = row.querySelector('.correct-marker') ? row.querySelector('.correct-marker').checked : false;
            const oldMarker = row.querySelector('.correct-marker');
            if (oldMarker) oldMarker.remove();

            const marker = document.createElement('input');
            marker.className = 'correct-marker';
            if (type === 'multiple_choice') {
                marker.type = 'radio';
                marker.name = `questions[${idx}][correct_option]`;
                marker.value = optKey;
            } else {
                marker.type = 'checkbox';
                marker.name = `questions[${idx}][options][${optKey}][correct]`;
                marker.value = '1';
            }
            marker.checked = wasChecked;
            row.insertBefore(marker, row.firstChild);
        });
    }

    function addOption(addBtnEl, value, isCorrect) {
        const card = addBtnEl.closest('.question-card');
        const idx = card.dataset.idx;
        const type = card.querySelector('.type-select').value;
        const optsList = card.querySelector('.options-list');

        const optKey = card.dataset.optIdx;
        card.dataset.optIdx = parseInt(card.dataset.optIdx) + 1;

        const row = document.createElement('div');
        row.className = 'option-row';
        row.dataset.optKey = optKey;

        const markerType = type === 'multiple_choice' ? 'radio' : 'checkbox';
        const markerName = type === 'multiple_choice'
            ? `questions[${idx}][correct_option]`
            : `questions[${idx}][options][${optKey}][correct]`;

        row.innerHTML = `
            <input type="${markerType}" class="correct-marker" name="${markerName}" value="${type === 'multiple_choice' ? optKey : '1'}" ${isCorrect ? 'checked' : ''}>
            <input type="text" name="questions[${idx}][options][${optKey}][text]" placeholder="Option text" value="${value ? escapeHtml(value) : ''}">
            <button type="button" class="remove-option" onclick="this.closest('.option-row').remove()">&times;</button>
        `;
        optsList.appendChild(row);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Load existing questions (edit mode) or start with one blank question
    if (existingQuestions.length > 0) {
        existingQuestions.forEach(q => addQuestion(q));
    } else {
        addQuestion();
    }
</script>
</body>
</html>
