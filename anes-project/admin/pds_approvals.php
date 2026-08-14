<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

// Quick-approve straight from the list
if (isset($_GET['approve'])) {
    $id = (int) $_GET['approve'];
    $pdo->prepare(
        'UPDATE pds_submissions s
         JOIN pds_forms f ON f.id = s.form_id
         SET s.approval_status = "approved", s.reviewed_by = ?, s.reviewed_at = NOW(), s.admin_remarks = NULL
         WHERE s.id = ? AND f.created_by = ?'
    )->execute([$_SESSION['user_id'], $id, $_SESSION['user_id']]);
    header('Location: pds_approvals.php');
    exit;
}

$pending = $pdo->prepare(
    'SELECT s.id AS submission_id, s.submitted_at,
            u.full_name AS student_name, u.email AS student_email,
            f.title AS form_title
     FROM pds_submissions s
     JOIN users u ON u.id = s.user_id
     JOIN pds_forms f ON f.id = s.form_id
     WHERE f.created_by = ? AND s.approval_status = "pending"
     ORDER BY s.submitted_at ASC'
);
$pending->execute([$_SESSION['user_id']]);
$pending = $pending->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PDS Approvals - Admin</title>
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
    .main h2 { color: #1a3a6b; margin: 0 0 22px 0; }

    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    th, td { text-align: left; padding: 14px 16px; font-size: 14px; border-bottom: 1px solid #eef0f3; }
    th { background: #f8f9fb; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    tr:last-child td { border-bottom: none; }

    .action-link {
        font-size: 13px; text-decoration: none; padding: 6px 12px;
        border-radius: 4px; border: 1px solid #d1d5db; color: #4b5563; margin-right: 6px;
    }
    .action-link:hover { background: #f8f9fb; }
    .action-link.approve { color: #1e7a34; border-color: #b9e6c4; }
    .action-link.review { color: #2b5cad; border-color: #c7d9f5; }

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
        <a href="pds_forms.php">PDS Forms</a>
        <a href="pds_records.php">PDS Records</a>
        <a href="pds_approvals.php" class="active">PDS Approvals<?= count($pending) ? ' (' . count($pending) . ')' : '' ?></a>
        <a href="dtr.php">DTR</a>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <h2>Pending PDS Approvals</h2>

        <?php if ($pending): ?>
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>PDS Form</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['student_name']) ?><br><span style="color:#9ca3af; font-size:12px;"><?= htmlspecialchars($p['student_email']) ?></span></td>
                    <td><?= htmlspecialchars($p['form_title']) ?></td>
                    <td><?= htmlspecialchars($p['submitted_at']) ?></td>
                    <td>
                        <a class="action-link review" href="pds_view.php?id=<?= (int)$p['submission_id'] ?>">Review</a>
                        <a class="action-link approve" href="pds_approvals.php?approve=<?= (int)$p['submission_id'] ?>"
                           onclick="return confirm('Approve this PDS without reviewing each field?');">Quick Approve</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state">No PDS submissions waiting for approval right now.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
