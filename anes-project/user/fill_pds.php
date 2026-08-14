<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role(['trainee', 'consultant', 'rater']);
require_once __DIR__ . '/../config/db_connect.php';

$form_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM pds_forms WHERE id = ? AND status = "published"');
$stmt->execute([$form_id]);
$form = $stmt->fetch();

if (!$form) {
    header('Location: pds.php');
    exit;
}

// Load fields + options
$f_stmt = $pdo->prepare('SELECT * FROM pds_fields WHERE form_id = ? ORDER BY sort_order ASC');
$f_stmt->execute([$form_id]);
$fields = $f_stmt->fetchAll();

foreach ($fields as &$f) {
    if ($f['field_type'] === 'dropdown') {
        $o_stmt = $pdo->prepare('SELECT * FROM pds_field_options WHERE field_id = ? ORDER BY sort_order ASC');
        $o_stmt->execute([$f['id']]);
        $f['options'] = $o_stmt->fetchAll();
    } else {
        $f['options'] = [];
    }
}
unset($f);

// Check for an existing submission (to pre-fill / update)
$sub_stmt = $pdo->prepare('SELECT * FROM pds_submissions WHERE form_id = ? AND user_id = ?');
$sub_stmt->execute([$form_id, $_SESSION['user_id']]);
$existing_submission = $sub_stmt->fetch();

$existing_answers = [];
if ($existing_submission) {
    $a_stmt = $pdo->prepare('SELECT field_id, answer_value FROM pds_answers WHERE submission_id = ?');
    $a_stmt->execute([$existing_submission['id']]);
    foreach ($a_stmt->fetchAll() as $row) {
        $existing_answers[$row['field_id']] = $row['answer_value'];
    }
}

$upload_dir = __DIR__ . '/../uploads/pds/';
if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = $_POST['answer'] ?? [];

    // Validate required fields (file fields count as filled if a file was
    // already uploaded before, or a new one is attached now)
    $missing = false;
    foreach ($fields as $f) {
        if (!$f['is_required']) continue;

        if ($f['field_type'] === 'file') {
            $has_new_file = isset($_FILES['answer_file']['name'][$f['id']]) && $_FILES['answer_file']['name'][$f['id']] !== '';
            $has_old_file = !empty($existing_answers[$f['id']]);
            if (!$has_new_file && !$has_old_file) { $missing = true; break; }
        } else {
            if (trim($answers[$f['id']] ?? '') === '') { $missing = true; break; }
        }
    }

    if ($missing) {
        $error = 'Please fill out all required fields before submitting.';
    } else {
        $pdo->beginTransaction();
        try {
            if ($existing_submission) {
                $submission_id = $existing_submission['id'];
                $pdo->prepare(
                    'UPDATE pds_submissions
                     SET submitted_at = NOW(), approval_status = "pending",
                         reviewed_by = NULL, reviewed_at = NULL, admin_remarks = NULL
                     WHERE id = ?'
                )->execute([$submission_id]);
            } else {
                $ins = $pdo->prepare('INSERT INTO pds_submissions (form_id, user_id) VALUES (?, ?)');
                $ins->execute([$form_id, $_SESSION['user_id']]);
                $submission_id = (int) $pdo->lastInsertId();
            }

            $del = $pdo->prepare('DELETE FROM pds_answers WHERE submission_id = ? AND field_id = ?');
            $a_ins = $pdo->prepare('INSERT INTO pds_answers (submission_id, field_id, answer_value) VALUES (?, ?, ?)');

            foreach ($fields as $f) {
                $del->execute([$submission_id, $f['id']]);

                if ($f['field_type'] === 'file') {
                    $val = $existing_answers[$f['id']] ?? null; // keep old file unless replaced
                    if (isset($_FILES['answer_file']['name'][$f['id']]) && $_FILES['answer_file']['name'][$f['id']] !== '' &&
                        $_FILES['answer_file']['error'][$f['id']] === UPLOAD_ERR_OK) {
                        $orig = basename($_FILES['answer_file']['name'][$f['id']]);
                        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $orig);
                        $stored_name = 'pds_' . $submission_id . '_' . $f['id'] . '_' . time() . '_' . $safe;
                        if (move_uploaded_file($_FILES['answer_file']['tmp_name'][$f['id']], $upload_dir . $stored_name)) {
                            $val = $stored_name;
                        }
                    }
                    $a_ins->execute([$submission_id, $f['id'], $val]);
                } else {
                    $val = trim($answers[$f['id']] ?? '');
                    $a_ins->execute([$submission_id, $f['id'], $val !== '' ? $val : null]);
                }
            }

            $pdo->commit();
            header('Location: pds.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Something went wrong while saving your PDS. Please try again.';
        }
    }
}

// Split fields into: grid fields (short answer types) vs breakout fields (dropdown / file)
$grid_fields = [];
$breakout_fields = [];
foreach ($fields as $f) {
    if ($f['field_type'] === 'dropdown' || $f['field_type'] === 'file') {
        $breakout_fields[] = $f;
    } else {
        $grid_fields[] = $f;
    }
}

function isEmailField($label) {
    return stripos($label, 'email') !== false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($form['title']) ?> - Trainee Evaluation System</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f2f4f7; }
    .layout { display: flex; min-height: 100vh; }
    .sidebar { width: 260px; background: #1a3a6b; color: #fff; display: flex; flex-direction: column; padding: 24px 0; flex-shrink: 0; }
    .sidebar .brand { padding: 0 24px 24px 24px; }
    .sidebar .brand strong { display:block; font-size: 16px; }
    .sidebar .brand span { display:block; font-size: 12px; color: #b8c6e0; margin-top:4px; }
    .sidebar a { color: #d7e0f2; text-decoration: none; padding: 12px 24px; font-size: 14px; display: block; }
    .sidebar a.active { background: rgba(255,255,255,0.12); color: #fff; font-weight: bold; }
    .sidebar a:hover { background: rgba(255,255,255,0.08); }
    .sidebar .bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px; }

    .main { flex: 1; padding: 30px 40px; max-width: 1000px; }
    .back-link { color:#3b6fd6; font-size: 13px; text-decoration:none; display:flex; align-items:center; gap:6px; }
    .main h2 { color: #1e1e1e; margin: 10px 0 20px 0; }

    .error-box { background:#fdecea; color:#b3261e; border:1px solid #f5c2c0; border-radius:5px; padding:10px 14px; font-size:13px; margin-bottom:16px; }

    .notice-bar {
        display:flex; align-items:flex-start; gap: 12px;
        background:#fff; border:1px solid #e5e7eb; border-left: 4px solid #e8a33d;
        padding: 14px 18px; margin-bottom: 12px; font-size: 14px; color: #333;
    }
    .notice-bar .bang { color:#e8a33d; font-weight:bold; font-size: 16px; line-height:1; }
    .notice-bar b { font-weight: bold; }

    .field-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 18px 24px; margin: 24px 0; }
    .field-group { position: relative; }
    .field-group.full { grid-column: 1 / -1; }

    label {
        display:block; font-size:12px; color:#6b7280; margin-bottom:6px;
    }
    label .required-mark { color: #b3261e; }
    input[type=text], input[type=date], input[type=number], input[type=email], textarea, select {
        width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
        border-radius: 6px; font-size: 14px; font-family: inherit; color:#1e1e1e;
    }
    textarea { resize: vertical; min-height: 70px; }

    .email-row { display:flex; align-items:center; gap: 14px; }
    .email-row input { flex: 1; }
    .email-confirm { display:flex; align-items:center; gap:8px; white-space:nowrap; font-size: 13px; color:#6b7280; }
    .confirm-btn {
        padding: 6px 14px; border-radius: 5px; font-size: 13px; font-weight: bold; cursor: pointer;
        border: 1px solid #d1d5db; background: #fff; color: #6b7280;
    }
    .confirm-btn.yes.selected { border-color: #1e7a34; color: #1e7a34; background:#eaf7ee; }
    .confirm-btn.no.selected { border-color: #b3261e; color: #b3261e; background:#fdecea; }

    .radio-section { margin: 22px 0; }
    .radio-section .section-label { font-weight: bold; font-size: 14px; color: #1e1e1e; margin-bottom: 10px; }
    .radio-options { display:flex; gap: 26px; flex-wrap: wrap; }
    .radio-option { display:flex; align-items:center; gap: 8px; }
    .radio-option input { width: 18px; height: 18px; cursor:pointer; }
    .radio-option label { margin: 0; font-size: 14px; color: #333; cursor:pointer; }

    .file-section { margin: 22px 0; }
    .file-section .section-label { font-weight: bold; font-size: 14px; color: #1e1e1e; margin-bottom: 8px; }
    .file-current { font-size: 13px; color: #3b6fd6; margin-bottom: 8px; }
    .file-none { font-size: 13px; color: #9ca3af; margin-bottom: 8px; }

    .submit-bar { margin-top: 30px; text-align: right; }
    button {
        padding: 12px 28px; border: none; border-radius: 6px;
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
        <a href="pds.php" class="active">PDS</a>
        <a href="dtr.php">My DTR</a>
        <?php if ($_SESSION['role'] === 'consultant'): ?>
        <a href="exam_reviews.php">Review Exams</a>
        <?php endif; ?>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <a class="back-link" href="pds.php">&larr; Back</a>
        <h2><?= $existing_submission ? 'Update ' : '' ?><?= htmlspecialchars($form['title']) ?></h2>

        <?php if ($existing_submission): ?>
            <?php if ($existing_submission['approval_status'] === 'pending'): ?>
                <div class="notice-bar" style="border-left-color:#a06b0a;">
                    <span class="bang" style="color:#a06b0a;">&#8987;</span>
                    <span>Your PDS is <b>pending admin approval</b>. Any further changes you make will also need to be re-approved.</span>
                </div>
            <?php elseif ($existing_submission['approval_status'] === 'approved'): ?>
                <div class="notice-bar" style="border-left-color:#1e7a34;">
                    <span class="bang" style="color:#1e7a34;">&#10003;</span>
                    <span>Your PDS has been <b>approved</b>. Making changes below will send it back for admin approval.</span>
                </div>
            <?php elseif ($existing_submission['approval_status'] === 'rejected'): ?>
                <div class="notice-bar" style="border-left-color:#b3261e;">
                    <span class="bang" style="color:#b3261e;">&#10007;</span>
                    <span><b>Your PDS was returned by the admin.</b><?= $existing_submission['admin_remarks'] ? ' Remarks: ' . htmlspecialchars($existing_submission['admin_remarks']) : '' ?> Please update and resubmit.</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="notice-bar">
            <span class="bang">!</span>
            <span>Please complete all fields<?= $form['description'] ? '' : ' in the <b>Personal Information</b> section' ?>. If any item does not apply, enter <b>"N/A"</b>. Do not leave any fields blank.<?= $form['description'] ? ' ' . htmlspecialchars($form['description']) : '' ?></span>
        </div>
        <div class="notice-bar">
            <span class="bang">!</span>
            <span>Double-check that all information is accurate, as incorrect details may prevent account recovery.</span>
        </div>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="fill_pds.php?id=<?= (int)$form_id ?>" enctype="multipart/form-data">
            <div class="field-grid">
                <?php foreach ($grid_fields as $f):
                    $val = $existing_answers[$f['id']] ?? '';
                    $is_email = isEmailField($f['field_label']);
                ?>
                    <div class="field-group">
                        <label for="field<?= $f['id'] ?>">
                            <?= htmlspecialchars($f['field_label']) ?>
                            <?php if ($f['is_required']): ?><span class="required-mark">*</span><?php endif; ?>
                        </label>

                        <?php if ($f['field_type'] === 'textarea'): ?>
                            <textarea id="field<?= $f['id'] ?>" name="answer[<?= $f['id'] ?>]" <?= $f['is_required'] ? 'required' : '' ?>><?= htmlspecialchars($val) ?></textarea>

                        <?php elseif ($f['field_type'] === 'date'): ?>
                            <input type="date" id="field<?= $f['id'] ?>" name="answer[<?= $f['id'] ?>]" value="<?= htmlspecialchars($val) ?>" <?= $f['is_required'] ? 'required' : '' ?>>

                        <?php elseif ($f['field_type'] === 'number'): ?>
                            <input type="number" id="field<?= $f['id'] ?>" name="answer[<?= $f['id'] ?>]" value="<?= htmlspecialchars($val) ?>" <?= $f['is_required'] ? 'required' : '' ?>>

                        <?php elseif ($is_email): ?>
                            <div class="email-row">
                                <input type="email" id="field<?= $f['id'] ?>" name="answer[<?= $f['id'] ?>]" value="<?= htmlspecialchars($val) ?>" <?= $f['is_required'] ? 'required' : '' ?> <?= $val !== '' ? 'readonly' : '' ?>>
                                <?php if ($val !== ''): ?>
                                <div class="email-confirm">
                                    <span>Still Your Active Email Address?</span>
                                    <span class="confirm-btn yes selected" onclick="lockEmail(<?= $f['id'] ?>, this)">YES</span>
                                    <span class="confirm-btn no" onclick="unlockEmail(<?= $f['id'] ?>, this)">NO</span>
                                </div>
                                <?php endif; ?>
                            </div>

                        <?php else: ?>
                            <input type="text" id="field<?= $f['id'] ?>" name="answer[<?= $f['id'] ?>]" value="<?= htmlspecialchars($val) ?>" <?= $f['is_required'] ? 'required' : '' ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php foreach ($breakout_fields as $f):
                $val = $existing_answers[$f['id']] ?? '';
            ?>
                <?php if ($f['field_type'] === 'dropdown' && count($f['options']) <= 6): ?>
                    <div class="radio-section">
                        <div class="section-label">
                            <?= htmlspecialchars($f['field_label']) ?>
                            <?php if ($f['is_required']): ?><span class="required-mark">*</span><?php endif; ?>
                        </div>
                        <div class="radio-options">
                            <?php foreach ($f['options'] as $opt): ?>
                                <div class="radio-option">
                                    <input type="radio" id="opt<?= $f['id'] ?>_<?= $opt['id'] ?>"
                                           name="answer[<?= $f['id'] ?>]" value="<?= htmlspecialchars($opt['option_text']) ?>"
                                           <?= $val === $opt['option_text'] ? 'checked' : '' ?>
                                           <?= $f['is_required'] ? 'required' : '' ?>>
                                    <label for="opt<?= $f['id'] ?>_<?= $opt['id'] ?>"><?= htmlspecialchars($opt['option_text']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php elseif ($f['field_type'] === 'dropdown'): ?>
                    <div class="field-group full" style="margin: 18px 0;">
                        <label for="field<?= $f['id'] ?>">
                            <?= htmlspecialchars($f['field_label']) ?>
                            <?php if ($f['is_required']): ?><span class="required-mark">*</span><?php endif; ?>
                        </label>
                        <select id="field<?= $f['id'] ?>" name="answer[<?= $f['id'] ?>]" <?= $f['is_required'] ? 'required' : '' ?>>
                            <option value="">-- Select --</option>
                            <?php foreach ($f['options'] as $opt): ?>
                                <option value="<?= htmlspecialchars($opt['option_text']) ?>" <?= $val === $opt['option_text'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($opt['option_text']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                <?php elseif ($f['field_type'] === 'file'): ?>
                    <div class="file-section">
                        <div class="section-label">
                            <?= htmlspecialchars($f['field_label']) ?>
                            <?php if ($f['is_required']): ?><span class="required-mark">*</span><?php endif; ?>
                        </div>
                        <?php if ($val): ?>
                            <div class="file-current">Current file: <a href="../uploads/pds/<?= htmlspecialchars($val) ?>" target="_blank"><?= htmlspecialchars($val) ?></a> (upload a new file below to replace it)</div>
                        <?php else: ?>
                            <div class="file-none">No File Attachments</div>
                        <?php endif; ?>
                        <input type="file" name="answer_file[<?= $f['id'] ?>]">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="submit-bar">
                <button type="submit"><?= $existing_submission ? 'Update' : 'Submit' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    function unlockEmail(fieldId, btn) {
        document.getElementById('field' + fieldId).readOnly = false;
        document.getElementById('field' + fieldId).focus();
        btn.classList.add('selected');
        btn.parentElement.querySelector('.confirm-btn.yes').classList.remove('selected');
    }
    function lockEmail(fieldId, btn) {
        btn.classList.add('selected');
        btn.parentElement.querySelector('.confirm-btn.no').classList.remove('selected');
    }
</script>
</body>
</html>
