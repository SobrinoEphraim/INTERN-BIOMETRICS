<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id !== (int) $_SESSION['user_id']) { // can't delete yourself
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
    header('Location: manage_users.php');
    exit;
}

// Handle enable/disable toggle
if (isset($_GET['toggle_status'])) {
    $id = (int) $_GET['toggle_status'];
    $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();
    if ($current) {
        $new = $current === 'active' ? 'disabled' : 'active';
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $id]);
    }
    header('Location: manage_users.php');
    exit;
}

$users = $pdo->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Users - Admin</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f2f4f7; }
    .layout { display: flex; min-height: 100vh; }
    .sidebar {
        width: 260px; background: #1a3a6b; color: #fff;
        display: flex; flex-direction: column; padding: 24px 0;
    }
    .sidebar .brand { padding: 0 24px 24px 24px; }
    .sidebar .brand strong { display:block; font-size: 16px; }
    .sidebar .brand span { display:block; font-size: 12px; color: #b8c6e0; margin-top:4px; }
    .sidebar a { color: #d7e0f2; text-decoration: none; padding: 12px 24px; font-size: 14px; display: block; }
    .sidebar a.active { background: rgba(255,255,255,0.12); color: #fff; font-weight: bold; }
    .sidebar a:hover { background: rgba(255,255,255,0.08); }
    .sidebar .section-label { padding: 18px 24px 6px 24px; font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase; color: #8fa3c8; }
    .sidebar .bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px; }

    .main { flex: 1; padding: 30px 40px; }
    .topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom: 22px; }
    .topbar h2 { color: #1a3a6b; margin: 0; }

    .btn {
        display: inline-block; background: #4a7fd4; color: #fff; text-decoration: none;
        padding: 10px 18px; border-radius: 5px; font-size: 14px; font-weight: bold;
    }
    .btn:hover { background: #3c6cc0; }

    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    th, td { text-align: left; padding: 14px 16px; font-size: 14px; border-bottom: 1px solid #eef0f3; }
    th { background: #f8f9fb; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    tr:last-child td { border-bottom: none; }

    .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
    .badge.admin { background:#e5edfb; color:#2b5cad; }
    .badge.trainee { background:#eaf7ee; color:#1e7a34; }
    .badge.consultant { background:#fdf3e3; color:#a06b0a; }
    .badge.rater { background:#f2eafd; color:#6a2ca0; }
    .badge.super_admin { background:#fde8ec; color:#9d174d; }
    .badge.intern { background:#e0f2fe; color:#0369a1; }

    .status-active { color:#1e7a34; font-weight:bold; }
    .status-disabled { color:#b3261e; font-weight:bold; }

    .action-link { font-size: 13px; text-decoration: none; margin-right: 12px; }
    .action-link.disable { color:#a06b0a; }
    .action-link.delete { color:#b3261e; }
    .action-link.enable { color:#1e7a34; }
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
        <a href="manage_users.php" class="active">Users / Roster</a>
        <a href="create_user.php">Add New User</a>
        <a href="assign_evaluations.php">Assign Evaluations</a>
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
        <div class="topbar">
            <h2>Users / Roster</h2>
            <a class="btn" href="create_user.php">+ Add New User</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge <?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $u['role']))) ?></span></td>
                    <td class="status-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></td>
                    <td><?= $u['last_login_at'] ? htmlspecialchars($u['last_login_at']) : 'Never signed in' ?></td>
                    <td>
                        <a class="action-link <?= $u['status'] === 'active' ? 'disable' : 'enable' ?>"
                           href="manage_users.php?toggle_status=<?= (int)$u['id'] ?>">
                            <?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?>
                        </a>
                        <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                        <a class="action-link delete"
                           href="manage_users.php?delete=<?= (int)$u['id'] ?>"
                           onclick="return confirm('Delete this user? This cannot be undone.');">
                            Delete
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
