<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

$exam_filter = (int)($_GET['exam_id'] ?? 0);

$sql = 'SELECT s.id AS submission_id, s.status, s.grading_status, s.total_score, s.max_score, s.submitted_at,
               u.full_name AS student_name, e.id AS exam_id, e.title AS exam_title
        FROM exam_submissions s
        JOIN users u ON u.id = s.user_id
        JOIN exams e ON e.id = s.exam_id
        WHERE e.created_by = ? AND s.status = "submitted"';
$params = [$_SESSION['user_id']];

if ($exam_filter) {
    $sql .= ' AND e.id = ?';
    $params[] = $exam_filter;
}

$sql .= ' ORDER BY s.submitted_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$submissions = $stmt->fetchAll();

// For the filter dropdown
$exams = $pdo->prepare('SELECT id, title FROM exams WHERE created_by = ? ORDER BY title ASC');
$exams->execute([$_SESSION['user_id']]);
$exams = $exams->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Grades - Admin</title>
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
    .topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom: 22px; }
    .topbar h2 { color: #1a3a6b; margin: 0; }

    select.filter {
        padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 5px; font-size: 14px; background:#fff;
    }

    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    th, td { text-align: left; padding: 14px 16px; font-size: 14px; border-bottom: 1px solid #eef0f3; }
    th { background: #f8f9fb; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    tr:last-child td { border-bottom: none; }

    .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
    .badge.graded { background:#eaf7ee; color:#1e7a34; }
    .badge.pending { background:#fdf3e3; color:#a06b0a; }

    .score { font-weight: bold; color: #1a3a6b; }

    .action-link {
        font-size: 13px; text-decoration: none; padding: 6px 12px;
        border-radius: 4px; border: 1px solid #d1d5db; color: #4b5563;
    }
    .action-link:hover { background: #f8f9fb; }

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
        <a href="grades.php" class="active">Grades</a>
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
            <h2>Grades</h2>
            <form method="GET" action="grades.php">
                <select class="filter" name="exam_id" onchange="this.form.submit()">
                    <option value="0">All Exams</option>
                    <?php foreach ($exams as $e): ?>
                        <option value="<?= (int)$e['id'] ?>" <?= $exam_filter === (int)$e['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($submissions): ?>
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Exam</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['student_name']) ?></td>
                    <td><?= htmlspecialchars($s['exam_title']) ?></td>
                    <td>
                        <?php if ($s['grading_status'] === 'graded'): ?>
                            <span class="score"><?= (float)$s['total_score'] ?> / <?= (float)$s['max_score'] ?></span>
                        <?php else: ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $s['grading_status'] ?>"><?= $s['grading_status'] === 'graded' ? 'Graded' : 'Pending Grading' ?></span></td>
                    <td><?= htmlspecialchars($s['submitted_at']) ?></td>
                    <td>
                        <a class="action-link" href="grade_submission.php?id=<?= (int)$s['submission_id'] ?>">
                            <?= $s['grading_status'] === 'graded' ? 'Review' : 'Grade Now' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state">No exam submissions yet.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
