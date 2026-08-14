<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

$form_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $form_id > 0;
$error = '';

// ------------------------------------------------------------
// Load existing form (edit mode) — must belong to this admin
// ------------------------------------------------------------
$form = ['title' => '', 'description' => ''];
$existing_fields = [];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM pds_forms WHERE id = ? AND created_by = ?');
    $stmt->execute([$form_id, $_SESSION['user_id']]);
    $found = $stmt->fetch();

    if (!$found) {
        header('Location: pds_forms.php');
        exit;
    }
    $form = $found;

    $f_stmt = $pdo->prepare('SELECT * FROM pds_fields WHERE form_id = ? ORDER BY sort_order ASC');
    $f_stmt->execute([$form_id]);
    $fields = $f_stmt->fetchAll();

    foreach ($fields as $f) {
        $opts = [];
        if ($f['field_type'] === 'dropdown') {
            $o_stmt = $pdo->prepare('SELECT option_text FROM pds_field_options WHERE field_id = ? ORDER BY sort_order ASC');
            $o_stmt->execute([$f['id']]);
            $opts = array_column($o_stmt->fetchAll(), 'option_text');
        }
        $existing_fields[] = [
            'label'    => $f['field_label'],
            'type'     => $f['field_type'],
            'required' => (bool)$f['is_required'],
            'options'  => $opts,
        ];
    }
}

// ------------------------------------------------------------
// Handle save (create or update)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $action      = $_POST['action'] ?? 'draft'; // draft | publish
    $fields_in   = $_POST['fields'] ?? [];

    $status = ($action === 'publish') ? 'published' : 'draft';

    if ($title === '') {
        $error = 'Please enter a PDS form title.';
    } elseif (empty($fields_in)) {
        $error = 'Please add at least one field to collect.';
    } else {
        foreach ($fields_in as $f) {
            $flabel = trim($f['label'] ?? '');
            $ftype  = $f['type'] ?? 'text';
            if ($flabel === '') {
                $error = 'Every field needs a label.';
                break;
            }
            if ($ftype === 'dropdown') {
                $opts = array_filter(array_map('trim', $f['options'] ?? []));
                if (count($opts) < 2) {
                    $error = 'Dropdown fields need at least 2 options.';
                    break;
                }
            }
        }

        if ($error === '') {
            $pdo->beginTransaction();
            try {
                if ($is_edit) {
                    $stmt = $pdo->prepare('UPDATE pds_forms SET title = ?, description = ?, status = ? WHERE id = ?');
                    $stmt->execute([$title, $description, $status, $form_id]);

                    // wipe old fields (options cascade automatically)
                    $pdo->prepare('DELETE FROM pds_fields WHERE form_id = ?')->execute([$form_id]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO pds_forms (title, description, status, created_by) VALUES (?, ?, ?, ?)'
                    );
                    $stmt->execute([$title, $description, $status, $_SESSION['user_id']]);
                    $form_id = (int) $pdo->lastInsertId();
                }

                $f_ins = $pdo->prepare(
                    'INSERT INTO pds_fields (form_id, field_label, field_type, is_required, sort_order) VALUES (?, ?, ?, ?, ?)'
                );
                $o_ins = $pdo->prepare(
                    'INSERT INTO pds_field_options (field_id, option_text, sort_order) VALUES (?, ?, ?)'
                );

                $order = 0;
                foreach ($fields_in as $f) {
                    $flabel = trim($f['label']);
                    $ftype = in_array($f['type'], ['text', 'textarea', 'date', 'number', 'dropdown', 'file'], true) ? $f['type'] : 'text';
                    $required = !empty($f['required']) ? 1 : 0;

                    $f_ins->execute([$form_id, $flabel, $ftype, $required, $order]);
                    $field_id = (int) $pdo->lastInsertId();

                    if ($ftype === 'dropdown') {
                        $opt_order = 0;
                        foreach (($f['options'] ?? []) as $opt_text) {
                            $opt_text = trim($opt_text);
                            if ($opt_text === '') continue;
                            $o_ins->execute([$field_id, $opt_text, $opt_order]);
                            $opt_order++;
                        }
                    }
                    $order++;
                }

                $pdo->commit();
                header('Location: pds_forms.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Something went wrong while saving the PDS form. Please try again.';
            }
        }
    }

    $form['title'] = $title;
    $form['description'] = $description;
    $existing_fields = [];
    foreach ($fields_in as $f) {
        $existing_fields[] = [
            'label'    => $f['label'] ?? '',
            'type'     => $f['type'] ?? 'text',
            'required' => !empty($f['required']),
            'options'  => $f['options'] ?? [],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $is_edit ? 'Edit PDS Form' : 'Create PDS Form' ?> - Admin</title>
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
    input[type=text], textarea, select {
        width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
        border-radius: 5px; font-size: 14px; font-family: inherit;
    }
    textarea { resize: vertical; min-height: 50px; }

    .field-card {
        background: #fff; border-radius: 8px; padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 16px;
        border-left: 4px solid #4a90d9;
    }
    .field-top { display: flex; gap: 12px; margin-bottom: 12px; align-items: flex-start; }
    .field-top input[type=text] { flex: 1; }
    .type-select { width: 170px; flex-shrink: 0; }

    .required-row { display:flex; align-items:center; gap:8px; margin-bottom: 12px; font-size: 13px; color: #4b5563; }
    .required-row input { width: 16px; height: 16px; }

    .options-list { margin-bottom: 10px; }
    .option-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
    .option-row input[type=text] { flex: 1; }
    .option-row .remove-option {
        color: #b3261e; cursor: pointer; font-size: 13px; background: none; border: none; padding: 4px 8px;
    }
    .add-option-btn {
        background: none; border: 1px dashed #9ca3af; color: #4b5563;
        padding: 8px 14px; border-radius: 5px; font-size: 13px; cursor: pointer;
    }
    .add-option-btn:hover { background: #f8f9fb; }

    .field-footer { display: flex; justify-content: flex-end; margin-top: 10px; }
    .remove-field-btn {
        background: none; border: none; color: #b3261e; font-size: 13px; cursor: pointer;
    }

    .add-field-btn {
        display: block; width: 100%; background: #fff; border: 2px dashed #4a90d9;
        color: #2b5cad; padding: 16px; border-radius: 8px; font-size: 14px; font-weight: bold;
        cursor: pointer; margin-bottom: 20px;
    }
    .add-field-btn:hover { background: #f0f6fd; }

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
        <a href="exams.php">Exams</a>
        <a href="grades.php">Grades</a>
        <a href="class_record.php">Class Record</a>
        <a href="pds_forms.php" class="active">PDS Forms</a>
        <a href="pds_records.php">PDS Records</a>
        <a href="pds_approvals.php">PDS Approvals</a>
        <a href="dtr.php">DTR</a>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <a class="back-link" href="pds_forms.php">&larr; Back to PDS Forms</a>
        <h2><?= $is_edit ? 'Edit PDS Form' : 'Create New PDS Form' ?></h2>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="pds_builder.php<?= $is_edit ? '?id=' . (int)$form_id : '' ?>" id="pdsForm">
            <div class="panel">
                <div class="form-group">
                    <label for="title">PDS Form Title</label>
                    <input type="text" id="title" name="title" required
                           value="<?= htmlspecialchars($form['title']) ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="description">Description (optional)</label>
                    <textarea id="description" name="description"><?= htmlspecialchars($form['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div id="fieldsContainer"></div>

            <button type="button" class="add-field-btn" onclick="addField()">+ Add Field</button>

            <div class="save-bar">
                <button type="submit" name="action" value="draft" class="secondary">Save as Draft</button>
                <button type="submit" name="action" value="publish" class="primary">Publish</button>
            </div>
        </form>
    </div>
</div>

<script>
    let fieldIndex = 0;
    const container = document.getElementById('fieldsContainer');
    const existingFields = <?= json_encode(array_values($existing_fields)) ?>;

    function addField(data) {
        const idx = fieldIndex++;
        const wrapper = document.createElement('div');
        wrapper.className = 'field-card';
        wrapper.dataset.idx = idx;

        const fType = data ? data.type : 'text';
        const isRequired = data ? data.required : true;

        wrapper.innerHTML = `
            <div class="field-top">
                <input type="text" name="fields[${idx}][label]" placeholder="e.g. Full Name, Date of Birth, Civil Status..." required value="${data ? escapeHtml(data.label) : ''}">
                <select class="type-select" name="fields[${idx}][type]" onchange="toggleOptions(this)">
                    <option value="text" ${fType === 'text' ? 'selected' : ''}>Short Text</option>
                    <option value="textarea" ${fType === 'textarea' ? 'selected' : ''}>Long Text</option>
                    <option value="date" ${fType === 'date' ? 'selected' : ''}>Date</option>
                    <option value="number" ${fType === 'number' ? 'selected' : ''}>Number</option>
                    <option value="dropdown" ${fType === 'dropdown' ? 'selected' : ''}>Dropdown (shows as radio buttons if 6 or fewer options)</option>
                    <option value="file" ${fType === 'file' ? 'selected' : ''}>File Attachment</option>
                </select>
            </div>
            <div class="required-row">
                <input type="checkbox" id="req${idx}" name="fields[${idx}][required]" value="1" ${isRequired ? 'checked' : ''}>
                <label for="req${idx}" style="text-transform:none; font-weight:normal; margin:0;">Required field</label>
            </div>
            <div class="options-list" style="display:${fType === 'dropdown' ? 'block' : 'none'};"></div>
            <button type="button" class="add-option-btn" style="display:${fType === 'dropdown' ? 'inline-block' : 'none'};" onclick="addOption(this)">+ Add Option</button>
            <div class="field-footer">
                <button type="button" class="remove-field-btn" onclick="this.closest('.field-card').remove()">Remove Field</button>
            </div>
        `;

        container.appendChild(wrapper);

        if (data && data.options && data.options.length) {
            data.options.forEach(optText => addOption(wrapper.querySelector('.add-option-btn'), optText));
        } else if (fType === 'dropdown') {
            addOption(wrapper.querySelector('.add-option-btn'));
            addOption(wrapper.querySelector('.add-option-btn'));
        }
    }

    function toggleOptions(selectEl) {
        const card = selectEl.closest('.field-card');
        const optsList = card.querySelector('.options-list');
        const addBtn = card.querySelector('.add-option-btn');
        const isDropdown = selectEl.value === 'dropdown';

        optsList.style.display = isDropdown ? 'block' : 'none';
        addBtn.style.display = isDropdown ? 'inline-block' : 'none';

        if (isDropdown && optsList.children.length === 0) {
            addOption(addBtn);
            addOption(addBtn);
        }
    }

    function addOption(addBtnEl, value) {
        const card = addBtnEl.closest('.field-card');
        const idx = card.dataset.idx;
        const optsList = card.querySelector('.options-list');

        const row = document.createElement('div');
        row.className = 'option-row';
        row.innerHTML = `
            <input type="text" name="fields[${idx}][options][]" placeholder="Option text" value="${value ? escapeHtml(value) : ''}">
            <button type="button" class="remove-option" onclick="this.closest('.option-row').remove()">&times;</button>
        `;
        optsList.appendChild(row);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Standard NKTI Personal Data Sheet template — pre-fills a new form
    // so admin only needs to add/remove fields, not build from scratch.
    const defaultTemplate = [
        { label: 'Prefix',                type: 'text',     required: true, options: [] },
        { label: 'Place of Birth',        type: 'text',     required: true, options: [] },
        { label: 'Last Name',             type: 'text',     required: true, options: [] },
        { label: 'Height',                type: 'text',     required: true, options: [] },
        { label: 'First Name',            type: 'text',     required: true, options: [] },
        { label: 'Weight',                type: 'text',     required: true, options: [] },
        { label: 'Middle Name',           type: 'text',     required: true, options: [] },
        { label: 'Blood Type',            type: 'text',     required: true, options: [] },
        { label: 'Suffix / Name Extension', type: 'text',   required: true, options: [] },
        { label: 'Telephone No.',         type: 'text',     required: true, options: [] },
        { label: 'Professional Title',    type: 'text',     required: true, options: [] },
        { label: 'Mobile No.',            type: 'text',     required: true, options: [] },
        { label: 'Date of Birth',         type: 'date',     required: true, options: [] },
        { label: 'Email Address',         type: 'text',     required: true, options: [] },
        { label: 'Sex at Birth',          type: 'dropdown', required: true, options: ['Male', 'Female'] },
        { label: 'Civil Status',          type: 'dropdown', required: true, options: ['Single', 'Married', 'Widowed', 'Separated', 'Other/s'] },
        { label: 'Citizenship',           type: 'dropdown', required: true, options: ['Filipino', 'Dual Citizenship'] },
        { label: 'File Attachment',       type: 'file',     required: false, options: [] },
    ];

    if (existingFields.length > 0) {
        existingFields.forEach(f => addField(f));
    } else if (!<?= $is_edit ? 'true' : 'false' ?>) {
        defaultTemplate.forEach(f => addField(f));
    } else {
        addField();
    }
</script>
</body>
</html>
