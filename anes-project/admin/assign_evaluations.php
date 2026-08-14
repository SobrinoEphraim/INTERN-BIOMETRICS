<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rater_id  = (int)($_POST['rater_id'] ?? 0);
    $ratee_id  = (int)($_POST['ratee_id'] ?? 0);
    $form_type = $_POST['form_type'] ?? '';

    $allowed_types = ['peer', 'trainee', 'consultant'];

    if (!$rater_id || !$ratee_id || !in_array($form_type, $allowed_types, true)) {
        $error = 'Please select a rater, a ratee, and a form type.';
    } elseif ($rater_id === $ratee_id) {
        $error = 'A user cannot be assigned to rate themselves.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO evaluation_assignments (rater_id, ratee_id, form_type, status)
             VALUES (?, ?, ?, "pending")'
        );
        $stmt->execute([$rater_id, $ratee_id, $form_type]);
        $success = true;
    }
}

$users = $pdo->query('SELECT id, full_name, role FROM users WHERE status = "active" ORDER BY full_name')->fetchAll();

$assignments = $pdo->query(
    'SELECT a.id, a.form_type, a.status, a.created_at,
            r.full_name AS rater_name, e.full_name AS ratee_name
     FROM evaluation_assignments a
     JOIN users r ON r.id = a.rater_id
     JOIN users e ON e.id = a.ratee_id
     ORDER BY a.created_at DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assign Evaluations - Admin</title>
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

    .main { flex: 1; padding: 30px 40px; }
    .main h2 { color: #1a3a6b; margin-top: 0; }

    .panel { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 24px; max-width: 700px; }
    .form-row { display: flex; gap: 14px; margin-bottom: 18px; }
    .form-group { flex: 1; }
    label { display:block; font-size:12px; font-weight:bold; color:#4b5563; margin-bottom:6px; text-transform:uppercase; }
    select {
        width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
        border-radius: 5px; font-size: 14px; background: #fff;
    }
    button {
        padding: 12px 24px; border: none; border-radius: 5px;
        background: #4a7fd4; color: #fff; font-size: 14px; font-weight: bold; cursor: pointer;
    }
    button:hover { background: #3c6cc0; }
    .error-box { background:#fdecea; color:#b3261e; border:1px solid #f5c2c0; border-radius:5px; padding:10px 14px; font-size:13px; margin-bottom:16px; }
    .success-box { background:#eaf7ee; color:#1e7a34; border:1px solid #b9e6c4; border-radius:5px; padding:14px; font-size:14px; margin-bottom:16px; }

    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    th, td { text-align: left; padding: 14px 16px; font-size: 14px; border-bottom: 1px solid #eef0f3; }
    th { background: #f8f9fb; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    tr:last-child td { border-bottom: none; }
    .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
    .badge.pending { background:#fdf3e3; color:#a06b0a; }
    .badge.submitted { background:#eaf7ee; color:#1e7a34; }
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
        <a href="assign_evaluations.php" class="active">Assign Evaluations</a>
        <a href="exams.php">Exams</a>
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
        <h2>Assign Evaluations</h2>

        <div class="panel">
            <?php if ($success): ?>
                <div class="success-box">Evaluation assignment created.</div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-box"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="assign_evaluations.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="rater_id">Rater (who fills out the form)</label>
                        <select id="rater_id" name="rater_id" required>
                            <option value="">-- Select user --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>">
                                    <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars(ucfirst($u['role'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="ratee_id">Ratee (person being evaluated)</label>
                        <select id="ratee_id" name="ratee_id" required>
                            <option value="">-- Select user --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>">
                                    <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars(ucfirst($u['role'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="form_type">Evaluation Form</label>
                    <select id="form_type" name="form_type" required>
                        <option value="">-- Select form type --</option>
                        <option value="peer">Peer Evaluation</option>
                        <option value="trainee">Trainee Evaluation (Resident/Fellow)</option>
                        <option value="consultant">Consultant Evaluation</option>
                    </select>
                </div>

                <button type="submit">Create Assignment</button>
            </form>
        </div>

        <h3 style="color:#1a3a6b;">All Assignments</h3>
        <table>
            <thead>
                <tr>
                    <th>Rater</th>
                    <th>Ratee</th>
                    <th>Form</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['rater_name']) ?></td>
                    <td><?= htmlspecialchars($a['ratee_name']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($a['form_type'])) ?></td>
                    <td><span class="badge <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                    <td><?= htmlspecialchars($a['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$assignments): ?>
                <tr><td colspan="5">No assignments yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
