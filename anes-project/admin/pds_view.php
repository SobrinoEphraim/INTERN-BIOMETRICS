<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

$submission_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT s.*, u.full_name AS student_name, u.email AS student_email,
            f.title AS form_title, f.created_by,
            r.full_name AS reviewer_name
     FROM pds_submissions s
     JOIN users u ON u.id = s.user_id
     JOIN pds_forms f ON f.id = s.form_id
     LEFT JOIN users r ON r.id = s.reviewed_by
     WHERE s.id = ?'
);
$stmt->execute([$submission_id]);
$submission = $stmt->fetch();

if (!$submission || (int)$submission['created_by'] !== (int)$_SESSION['user_id']) {
    header('Location: pds_records.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decision'])) {
    $decision = $_POST['decision'] === 'approve' ? 'approved' : 'rejected';
    $remarks  = trim($_POST['remarks'] ?? '');

    $pdo->prepare(
        'UPDATE pds_submissions
         SET approval_status = ?, reviewed_by = ?, reviewed_at = NOW(), admin_remarks = ?
         WHERE id = ?'
    )->execute([$decision, $_SESSION['user_id'], $remarks !== '' ? $remarks : null, $submission_id]);

    header('Location: pds_view.php?id=' . $submission_id);
    exit;
}

$f_stmt = $pdo->prepare('SELECT * FROM pds_fields WHERE form_id = (SELECT form_id FROM pds_submissions WHERE id = ?) ORDER BY sort_order ASC');
$f_stmt->execute([$submission_id]);
$fields = $f_stmt->fetchAll();

foreach ($fields as &$f) {
    $a_stmt = $pdo->prepare('SELECT answer_value FROM pds_answers WHERE submission_id = ? AND field_id = ?');
    $a_stmt->execute([$submission_id, $f['id']]);
    $f['answer'] = $a_stmt->fetchColumn();

    if ($f['field_type'] === 'dropdown') {
        $o_stmt = $pdo->prepare('SELECT * FROM pds_field_options WHERE field_id = ? ORDER BY sort_order ASC');
        $o_stmt->execute([$f['id']]);
        $f['options'] = $o_stmt->fetchAll();
    } else {
        $f['options'] = [];
    }
}
unset($f);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PDS - <?= htmlspecialchars($submission['student_name']) ?></title>
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
    .sidebar .section-label { padding: 18px 24px 6px 24px; font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase; color: #8fa3c8; }
    .sidebar .bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px; }

    .main { flex: 1; padding: 30px 40px; max-width: 1000px; }
    .back-link { color:#3b6fd6; font-size: 13px; text-decoration:none; }
    .main h2 { color: #1e1e1e; margin: 10px 0 2px 0; }
    .main .meta { color: #6b7280; font-size: 14px; margin-bottom: 24px; }

    .field-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; margin: 20px 0; }
    .field-group label {
        display:block; font-size:12px; color:#6b7280; margin-bottom:6px;
    }
    .field-value {
        width: 100%; padding: 12px 14px; border: 1px solid #e5e7eb; border-radius: 6px;
        font-size: 14px; color:#1e1e1e; background:#f8f9fb; min-height: 20px;
    }
    .field-value.empty { color: #b0b6c0; font-style: italic; }

    .radio-section { margin: 22px 0; }
    .radio-section .section-label { font-weight: bold; font-size: 14px; color: #1e1e1e; margin-bottom: 10px; }
    .radio-options { display:flex; gap: 26px; flex-wrap: wrap; }
    .radio-option { display:flex; align-items:center; gap: 8px; font-size: 14px; color: #333; }
    .radio-option .dot {
        width: 16px; height: 16px; border-radius: 50%; border: 2px solid #9ca3af; flex-shrink:0;
        display: flex; align-items:center; justify-content:center;
    }
    .radio-option.selected .dot { border-color: #1a3a6b; }
    .radio-option.selected .dot::after { content:''; width:8px; height:8px; border-radius:50%; background:#1a3a6b; }
    .radio-option.selected label { font-weight: bold; color: #1a3a6b; }

    .file-section { margin: 22px 0; }
    .file-section .section-label { font-weight: bold; font-size: 14px; color: #1e1e1e; margin-bottom: 8px; }
    .file-current a { font-size: 13px; color: #3b6fd6; }
    .file-none { font-size: 13px; color: #9ca3af; }

    .print-btn {
        display: inline-block; margin-top: 26px; padding: 10px 18px;
        background: #4a7fd4; color: #fff; text-decoration: none; border-radius: 6px;
        font-size: 14px; font-weight: bold; border: none; cursor: pointer;
    }
    .print-btn:hover { background: #3c6cc0; }

    .approval-banner {
        padding: 12px 16px; border-radius: 6px; font-size: 14px; font-weight: bold; margin-bottom: 20px;
    }
    .approval-banner.pending { background:#fdf3e3; color:#a06b0a; }
    .approval-banner.approved { background:#eaf7ee; color:#1e7a34; }
    .approval-banner.rejected { background:#fdecea; color:#b3261e; }

    .approval-actions {
        background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-top: 24px;
    }
    .approval-actions textarea {
        width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 5px;
        font-size: 14px; font-family: inherit; resize: vertical; min-height: 60px; margin-bottom: 12px;
    }
    .approval-actions .btn-row { display: flex; gap: 10px; }
    .approval-actions button {
        padding: 12px 24px; border: none; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer;
    }
    .approval-actions button.approve-btn { background: #1e7a34; color: #fff; }
    .approval-actions button.approve-btn:hover { background: #17612a; }
    .approval-actions button.reject-btn { background: #fff; color: #b3261e; border: 1px solid #f5c2c0; }
    .approval-actions button.reject-btn:hover { background: #fdecea; }

    @media print {
        .sidebar, .back-link, .print-btn, .approval-actions { display: none; }
        .main { max-width: 100%; padding: 0; }
    }
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
        <a href="grades.php">Grades</a>
        <a href="class_record.php">Class Record</a>
        <a href="pds_forms.php">PDS Forms</a>
        <a href="pds_records.php" class="active">PDS Records</a>
        <a href="pds_approvals.php">PDS Approvals</a>
        <a href="dtr.php">DTR</a>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <a class="back-link" href="pds_records.php">&larr; Back to PDS Records</a>
        <h2><?= htmlspecialchars($submission['student_name']) ?></h2>
        <p class="meta">
            <?= htmlspecialchars($submission['student_email']) ?> &middot;
            <?= htmlspecialchars($submission['form_title']) ?> &middot;
            Submitted <?= htmlspecialchars($submission['submitted_at']) ?>
        </p>

        <?php if ($submission['approval_status'] === 'pending'): ?>
            <div class="approval-banner pending">&#8987; Pending Approval &mdash; awaiting your review.</div>
        <?php elseif ($submission['approval_status'] === 'approved'): ?>
            <div class="approval-banner approved">&#10003; Approved by <?= htmlspecialchars($submission['reviewer_name'] ?? 'admin') ?> on <?= htmlspecialchars($submission['reviewed_at']) ?></div>
        <?php elseif ($submission['approval_status'] === 'rejected'): ?>
            <div class="approval-banner rejected">&#10007; Returned by <?= htmlspecialchars($submission['reviewer_name'] ?? 'admin') ?> on <?= htmlspecialchars($submission['reviewed_at']) ?><?= $submission['admin_remarks'] ? ' &mdash; Remarks: ' . htmlspecialchars($submission['admin_remarks']) : '' ?></div>
        <?php endif; ?>

        <div class="field-grid">
            <?php foreach ($grid_fields as $f): ?>
                <div class="field-group">
                    <label><?= htmlspecialchars($f['field_label']) ?></label>
                    <div class="field-value <?= ($f['answer'] === null || $f['answer'] === '') ? 'empty' : '' ?>">
                        <?= ($f['answer'] !== null && $f['answer'] !== '') ? nl2br(htmlspecialchars($f['answer'])) : '(no answer)' ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($breakout_fields as $f): ?>
            <?php if ($f['field_type'] === 'dropdown' && count($f['options']) <= 6): ?>
                <div class="radio-section">
                    <div class="section-label"><?= htmlspecialchars($f['field_label']) ?></div>
                    <div class="radio-options">
                        <?php foreach ($f['options'] as $opt):
                            $selected = ($f['answer'] === $opt['option_text']);
                        ?>
                            <div class="radio-option <?= $selected ? 'selected' : '' ?>">
                                <span class="dot"></span>
                                <label><?= htmlspecialchars($opt['option_text']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($f['field_type'] === 'dropdown'): ?>
                <div class="field-group" style="margin: 18px 0;">
                    <label><?= htmlspecialchars($f['field_label']) ?></label>
                    <div class="field-value <?= ($f['answer'] === null || $f['answer'] === '') ? 'empty' : '' ?>">
                        <?= $f['answer'] ?: '(no answer)' ?>
                    </div>
                </div>

            <?php elseif ($f['field_type'] === 'file'): ?>
                <div class="file-section">
                    <div class="section-label"><?= htmlspecialchars($f['field_label']) ?></div>
                    <?php if ($f['answer']): ?>
                        <div class="file-current">
                            <a href="../uploads/pds/<?= htmlspecialchars($f['answer']) ?>" target="_blank">
                                &#128206; <?= htmlspecialchars($f['answer']) ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="file-none">No File Attachments</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="approval-actions">
            <form method="POST" action="pds_view.php?id=<?= (int)$submission_id ?>">
                <label style="display:block; font-size:12px; color:#6b7280; margin-bottom:6px;">Remarks (required if returning for correction)</label>
                <textarea name="remarks" placeholder="e.g. Please correct your civil status entry..."><?= htmlspecialchars($submission['admin_remarks'] ?? '') ?></textarea>
                <div class="btn-row">
                    <button type="submit" name="decision" value="approve" class="approve-btn">Approve PDS</button>
                    <button type="submit" name="decision" value="reject" class="reject-btn">Return for Correction</button>
                </div>
            </form>
        </div>

        <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
    </div>
</div>
</body>
</html>
