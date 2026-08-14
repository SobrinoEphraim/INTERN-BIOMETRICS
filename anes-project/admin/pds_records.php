<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

$search = trim($_GET['q'] ?? '');
$form_filter = (int)($_GET['form_id'] ?? 0);

$sql = 'SELECT s.id AS submission_id, s.submitted_at, s.approval_status,
               u.full_name AS student_name, u.email AS student_email,
               f.id AS form_id, f.title AS form_title
        FROM pds_submissions s
        JOIN users u ON u.id = s.user_id
        JOIN pds_forms f ON f.id = s.form_id
        WHERE f.created_by = ?';
$params = [$_SESSION['user_id']];

if ($search !== '') {
    $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($form_filter) {
    $sql .= ' AND f.id = ?';
    $params[] = $form_filter;
}

$sql .= ' ORDER BY s.submitted_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$submissions = $stmt->fetchAll();

// For the filter dropdown
$forms = $pdo->prepare('SELECT id, title FROM pds_forms WHERE created_by = ? ORDER BY title ASC');
$forms->execute([$_SESSION['user_id']]);
$forms = $forms->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PDS Records - Admin</title>
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
    .topbar { margin-bottom: 22px; }
    .topbar h2 { color: #1a3a6b; margin: 0 0 16px 0; }

    .search-bar { display: flex; gap: 10px; }
    .search-bar input[type=text] {
        flex: 1; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 5px; font-size: 14px;
    }
    .search-bar select {
        padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 5px; font-size: 14px; background:#fff; width: 220px;
    }
    .search-bar button {
        padding: 12px 20px; border: none; border-radius: 5px;
        background: #4a7fd4; color: #fff; font-size: 14px; font-weight: bold; cursor: pointer;
    }
    .search-bar button:hover { background: #3c6cc0; }

    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-top: 20px; }
    th, td { text-align: left; padding: 14px 16px; font-size: 14px; border-bottom: 1px solid #eef0f3; }
    th { background: #f8f9fb; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    tr:last-child td { border-bottom: none; }

    .action-link {
        font-size: 13px; text-decoration: none; padding: 6px 12px;
        border-radius: 4px; border: 1px solid #d1d5db; color: #4b5563;
    }
    .action-link:hover { background: #f8f9fb; }

    .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
    .badge.approved { background:#eaf7ee; color:#1e7a34; }
    .badge.pending { background:#fdf3e3; color:#a06b0a; }
    .badge.rejected { background:#fdecea; color:#b3261e; }

    .empty-state { background:#fff; border-radius:8px; padding: 50px; text-align:center; color:#6b7280; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-top: 20px; }
    .result-count { font-size: 13px; color: #6b7280; margin-top: 10px; }
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
        <div class="topbar">
            <h2>PDS Records</h2>
            <form method="GET" action="pds_records.php" class="search-bar">
                <input type="text" name="q" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
                <select name="form_id">
                    <option value="0">All PDS Forms</option>
                    <?php foreach ($forms as $f): ?>
                        <option value="<?= (int)$f['id'] ?>" <?= $form_filter === (int)$f['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Search</button>
            </form>
        </div>

        <?php if ($submissions): ?>
            <div class="result-count"><?= count($submissions) ?> record<?= count($submissions) == 1 ? '' : 's' ?> found</div>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>PDS Form</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['student_name']) ?></td>
                        <td><?= htmlspecialchars($s['student_email']) ?></td>
                        <td><?= htmlspecialchars($s['form_title']) ?></td>
                        <td>
                            <?php if ($s['approval_status'] === 'approved'): ?>
                                <span class="badge approved">Approved</span>
                            <?php elseif ($s['approval_status'] === 'rejected'): ?>
                                <span class="badge rejected">Returned</span>
                            <?php else: ?>
                                <span class="badge pending">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($s['submitted_at']) ?></td>
                        <td>
                            <a class="action-link" href="pds_view.php?id=<?= (int)$s['submission_id'] ?>">View PDS</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <?= $search !== '' ? 'No records match your search.' : 'No PDS submissions yet.' ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
