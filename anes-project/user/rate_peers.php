<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role(['trainee', 'consultant', 'rater']);
require_once __DIR__ . '/../config/db_connect.php';

$stmt = $pdo->prepare(
    'SELECT a.id, a.form_type, a.status, a.submitted_at,
            u.full_name AS ratee_name
     FROM evaluation_assignments a
     JOIN users u ON u.id = a.ratee_id
     WHERE a.rater_id = ?
     ORDER BY a.status ASC, a.created_at DESC'
);
$stmt->execute([$_SESSION['user_id']]);
$assignments = $stmt->fetchAll();

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
<title>Rate Peers - Trainee Evaluation System</title>
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
    .main { flex: 1; padding: 30px 40px; }
    .main h2 { color: #1a3a6b; margin-top:0; }

    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    th, td { text-align: left; padding: 14px 16px; font-size: 14px; border-bottom: 1px solid #eef0f3; }
    th { background: #f8f9fb; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    tr:last-child td { border-bottom: none; }

    .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
    .badge.pending { background:#fdf3e3; color:#a06b0a; }
    .badge.submitted { background:#eaf7ee; color:#1e7a34; }

    .btn {
        display: inline-block; background: #4a7fd4; color: #fff; text-decoration: none;
        padding: 8px 16px; border-radius: 5px; font-size: 13px; font-weight: bold;
    }
    .btn:hover { background: #3c6cc0; }
    .btn.done { background: #9ca3af; pointer-events: none; }

    .empty-state { background:#fff; border-radius:8px; padding: 40px; text-align:center; color:#6b7280; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
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
        <h2>My Evaluations To Complete</h2>

        <?php if ($assignments): ?>
        <table>
            <thead>
                <tr>
                    <th>Person to Rate</th>
                    <th>Form</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['ratee_name']) ?></td>
                    <td><?= htmlspecialchars($form_labels[$a['form_type']] ?? $a['form_type']) ?></td>
                    <td><span class="badge <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                    <td><?= $a['submitted_at'] ? htmlspecialchars($a['submitted_at']) : '&mdash;' ?></td>
                    <td>
                        <?php if ($a['status'] === 'pending'): ?>
                            <a class="btn" href="evaluation_form.php?id=<?= (int)$a['id'] ?>">Rate Now</a>
                        <?php else: ?>
                            <a class="btn done" href="#">Submitted</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state">
                You don't have any evaluations assigned to you yet.
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
