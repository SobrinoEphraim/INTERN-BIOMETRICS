<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role(['trainee', 'consultant', 'rater']);
require_once __DIR__ . '/../config/db_connect.php';

$stmt = $pdo->prepare(
    'SELECT f.*,
        (SELECT COUNT(*) FROM pds_fields fl WHERE fl.form_id = f.id) AS field_count,
        s.id AS my_submission_id,
        s.approval_status AS my_approval_status
     FROM pds_forms f
     LEFT JOIN pds_submissions s ON s.form_id = f.id AND s.user_id = ?
     WHERE f.status = "published"
     ORDER BY f.created_at DESC'
);
$stmt->execute([$_SESSION['user_id']]);
$forms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PDS - Trainee Evaluation System</title>
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
    .main h2 { color: #1a3a6b; margin-top:0; margin-bottom: 24px; }

    .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; }
    .form-card {
        background: #fff; border-radius: 8px; padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-top: 4px solid #4a90d9;
    }
    .form-card h3 { margin: 0 0 6px 0; color: #1a1a1a; font-size: 17px; }
    .form-card .desc { color: #6b7280; font-size: 13px; margin-bottom: 14px; min-height: 18px; }
    .form-stats { font-size: 13px; color: #6b7280; margin-bottom: 16px; }

    .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
    .badge.submitted { background:#eaf7ee; color:#1e7a34; }
    .badge.pending { background:#fdf3e3; color:#a06b0a; }
    .badge.rejected { background:#fdecea; color:#b3261e; }

    .btn {
        display: inline-block; background: #4a7fd4; color: #fff; text-decoration: none;
        padding: 10px 18px; border-radius: 5px; font-size: 14px; font-weight: bold;
    }
    .btn:hover { background: #3c6cc0; }
    .btn.edit { background: #6b7280; }

    .empty-state { background:#fff; border-radius:8px; padding: 50px; text-align:center; color:#6b7280; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
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
        <a href="#">My Feedback</a>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <h2>Personal Data Sheet (PDS)</h2>

        <?php if ($forms): ?>
        <div class="form-grid">
            <?php foreach ($forms as $f): ?>
            <div class="form-card">
                <h3><?= htmlspecialchars($f['title']) ?></h3>
                <div class="desc"><?= htmlspecialchars($f['description'] ?: '') ?></div>
                <div class="form-stats">
                    <?= (int)$f['field_count'] ?> field<?= $f['field_count'] == 1 ? '' : 's' ?>
                    &nbsp;
                    <?php if ($f['my_submission_id']): ?>
                        <?php if ($f['my_approval_status'] === 'approved'): ?>
                            <span class="badge submitted">Approved</span>
                        <?php elseif ($f['my_approval_status'] === 'rejected'): ?>
                            <span class="badge rejected">Returned &mdash; Needs Update</span>
                        <?php else: ?>
                            <span class="badge pending">Pending Approval</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge pending">Not yet filled out</span>
                    <?php endif; ?>
                </div>
                <?php if ($f['my_submission_id']): ?>
                    <a class="btn edit" href="fill_pds.php?id=<?= (int)$f['id'] ?>">Update My Answers</a>
                <?php else: ?>
                    <a class="btn" href="fill_pds.php?id=<?= (int)$f['id'] ?>">Fill Out Now</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state">
                No PDS forms available right now.
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
