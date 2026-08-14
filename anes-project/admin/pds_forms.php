<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

// Handle publish / unpublish toggle
if (isset($_GET['toggle_status'])) {
    $id = (int) $_GET['toggle_status'];
    $stmt = $pdo->prepare('SELECT status FROM pds_forms WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    $current = $stmt->fetchColumn();
    if ($current) {
        $new = $current === 'published' ? 'draft' : 'published';
        $pdo->prepare('UPDATE pds_forms SET status = ? WHERE id = ?')->execute([$new, $id]);
    }
    header('Location: pds_forms.php');
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $pdo->prepare('DELETE FROM pds_forms WHERE id = ? AND created_by = ?')->execute([$id, $_SESSION['user_id']]);
    header('Location: pds_forms.php');
    exit;
}

$forms = $pdo->prepare(
    'SELECT f.*,
        (SELECT COUNT(*) FROM pds_fields fl WHERE fl.form_id = f.id) AS field_count,
        (SELECT COUNT(*) FROM pds_submissions s WHERE s.form_id = f.id) AS submission_count
     FROM pds_forms f
     WHERE f.created_by = ?
     ORDER BY f.created_at DESC'
);
$forms->execute([$_SESSION['user_id']]);
$forms = $forms->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PDS Forms - Admin</title>
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
    .topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom: 24px; }
    .topbar h2 { color: #1a3a6b; margin: 0; }

    .btn {
        display: inline-block; background: #4a7fd4; color: #fff; text-decoration: none;
        padding: 10px 18px; border-radius: 5px; font-size: 14px; font-weight: bold; border: none; cursor: pointer;
    }
    .btn:hover { background: #3c6cc0; }

    .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; }
    .form-card {
        background: #fff; border-radius: 8px; padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-top: 4px solid #4a90d9;
    }
    .form-card h3 { margin: 0 0 6px 0; color: #1a1a1a; font-size: 17px; }
    .form-card .desc { color: #6b7280; font-size: 13px; margin-bottom: 14px; min-height: 18px; }
    .form-meta { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
    .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
    .badge.published { background:#eaf7ee; color:#1e7a34; }
    .badge.draft { background:#f3f4f6; color:#6b7280; }

    .form-stats { font-size: 13px; color: #6b7280; margin-bottom: 16px; }
    .form-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .action-link {
        font-size: 13px; text-decoration: none; padding: 6px 12px;
        border-radius: 4px; border: 1px solid #d1d5db; color: #4b5563;
    }
    .action-link:hover { background: #f8f9fb; }
    .action-link.publish { color: #1e7a34; border-color: #b9e6c4; }
    .action-link.unpublish { color: #a06b0a; border-color: #f0dba8; }
    .action-link.delete { color: #b3261e; border-color: #f5c2c0; }

    .empty-state { background:#fff; border-radius:8px; padding: 50px; text-align:center; color:#6b7280; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
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
        <div class="topbar">
            <h2>PDS Forms</h2>
            <a class="btn" href="pds_builder.php">+ Create New PDS Form</a>
        </div>

        <?php if ($forms): ?>
        <div class="form-grid">
            <?php foreach ($forms as $f): ?>
            <div class="form-card">
                <h3><?= htmlspecialchars($f['title']) ?></h3>
                <div class="desc"><?= htmlspecialchars($f['description'] ?: '') ?></div>

                <div class="form-meta">
                    <span class="badge <?= $f['status'] ?>"><?= ucfirst($f['status']) ?></span>
                </div>

                <div class="form-stats">
                    <?= (int)$f['field_count'] ?> field<?= $f['field_count'] == 1 ? '' : 's' ?>
                    &middot; <?= (int)$f['submission_count'] ?> submission<?= $f['submission_count'] == 1 ? '' : 's' ?>
                </div>

                <div class="form-actions">
                    <a class="action-link" href="pds_builder.php?id=<?= (int)$f['id'] ?>">Edit</a>
                    <?php if ($f['status'] === 'published'): ?>
                        <a class="action-link unpublish" href="pds_forms.php?toggle_status=<?= (int)$f['id'] ?>">Unpublish</a>
                    <?php else: ?>
                        <a class="action-link publish" href="pds_forms.php?toggle_status=<?= (int)$f['id'] ?>">Publish</a>
                    <?php endif; ?>
                    <a class="action-link delete" href="pds_forms.php?delete=<?= (int)$f['id'] ?>"
                       onclick="return confirm('Delete this PDS form and all its submissions? This cannot be undone.');">Delete</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state">
                You haven't created any PDS forms yet. Click "Create New PDS Form" to get started.
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
