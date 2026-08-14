<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

$quarter = $_GET['quarter'] ?? '1';
if (!in_array($quarter, ['1', '2', '3', '4'], true)) {
    $quarter = '1';
}

$quarter_labels = ['1' => '1st Quarter', '2' => '2nd Quarter', '3' => '3rd Quarter', '4' => '4th Quarter'];

$category_meta = [
    'written'    => ['label' => 'Written Evaluation',        'weight' => 0.40, 'col_prefix' => 'W'],
    'clinical'   => ['label' => 'Clinical Performance Tasks', 'weight' => 0.40, 'col_prefix' => 'C'],
    'behavioral' => ['label' => 'Behavioral Assessment',      'weight' => 0.20, 'col_prefix' => 'B'],
];

// Exams tagged to this quarter, created by this admin, grouped by category
$exam_stmt = $pdo->prepare(
    'SELECT e.*, (SELECT SUM(points) FROM exam_questions q WHERE q.exam_id = e.id) AS max_points
     FROM exams e
     WHERE e.created_by = ? AND e.quarter = ?
     ORDER BY (e.week_number IS NULL), e.week_number ASC, e.title ASC'
);
$exam_stmt->execute([$_SESSION['user_id'], $quarter]);
$all_exams = $exam_stmt->fetchAll();

$exams_by_cat = ['written' => [], 'clinical' => [], 'behavioral' => []];
foreach ($all_exams as $e) {
    $exams_by_cat[$e['category']][] = $e;
}

// All active trainees
$students = $pdo->query(
    'SELECT id, full_name FROM users WHERE role = "trainee" AND status = "active" ORDER BY full_name ASC'
)->fetchAll();

// Pull every graded submission for all exams in this quarter, keyed by [exam_id][user_id]
$scores = [];
if ($all_exams) {
    $exam_ids = array_column($all_exams, 'id');
    $placeholders = implode(',', array_fill(0, count($exam_ids), '?'));
    $sub_stmt = $pdo->prepare(
        "SELECT exam_id, user_id, total_score, grading_status
         FROM exam_submissions
         WHERE exam_id IN ($placeholders) AND status = 'submitted'"
    );
    $sub_stmt->execute($exam_ids);
    foreach ($sub_stmt->fetchAll() as $row) {
        $scores[$row['exam_id']][$row['user_id']] = $row;
    }
}

function computeCategory($exams, $scores, $user_id, $weight) {
    $total = 0;
    $max_of_graded = 0;
    $cells = [];
    foreach ($exams as $ex) {
        $row = $scores[$ex['id']][$user_id] ?? null;
        if ($row && $row['grading_status'] === 'graded') {
            $cells[$ex['id']] = (float)$row['total_score'];
            $total += (float)$row['total_score'];
            $max_of_graded += (int)$ex['max_points'];
        } elseif ($row) {
            $cells[$ex['id']] = 'pending';
        } else {
            $cells[$ex['id']] = null;
        }
    }
    $ps = $max_of_graded > 0 ? round(($total / $max_of_graded) * 100, 2) : null;
    $ws = $ps !== null ? round($ps * $weight, 2) : null;
    return ['cells' => $cells, 'total' => $total, 'max_of_graded' => $max_of_graded, 'ps' => $ps, 'ws' => $ws];
}

function highestPossible($exams) {
    $t = 0;
    foreach ($exams as $ex) { $t += (int)$ex['max_points']; }
    return $t;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Class Record - Admin</title>
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

    .main { flex: 1; padding: 30px 40px; overflow-x: auto; }
    .main h2 { color: #1a3a6b; margin: 0 0 16px 0; }

    .quarter-tabs { display: flex; gap: 6px; margin-bottom: 20px; }
    .quarter-tabs a {
        padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: bold;
        text-decoration: none; color: #4b5563; background: #fff; border: 1px solid #d1d5db;
    }
    .quarter-tabs a.active { background: #1a3a6b; color: #fff; border-color: #1a3a6b; }

    .cat-title { color: #1a3a6b; font-size: 15px; margin: 26px 0 10px 0; }
    .cat-title:first-of-type { margin-top: 0; }

    table.record { border-collapse: collapse; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.06); min-width: 100%; margin-bottom: 8px; }
    table.record th, table.record td {
        border: 1px solid #e5e7eb; padding: 8px 10px; font-size: 13px; text-align: center; white-space: nowrap;
    }
    table.record th { background: #f8f9fb; color: #4b5563; font-weight: bold; }
    table.record td.name { text-align: left; font-weight: bold; color: #1e1e1e; background: #fff; position: sticky; left: 0; }
    table.record th.name-col { text-align: left; position: sticky; left: 0; z-index: 2; }
    table.record tr.highest td { background: #eef4fb; font-weight: bold; }
    table.record td.pending { color: #a06b0a; font-style: italic; }
    table.record td.blank { color: #d1d5db; }
    table.record td.total, table.record td.ps, table.record td.ws { font-weight: bold; color: #1a3a6b; background: #f8f9fb; }
    table.record td.grade { font-weight: bold; color: #fff; background: #4a7fd4; }

    .no-exams-note { font-size: 12px; color: #9ca3af; margin-bottom: 20px; }
    .empty-state { background:#fff; border-radius:8px; padding: 50px; text-align:center; color:#6b7280; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .legend { font-size: 12px; color: #6b7280; margin-top: 14px; margin-bottom: 30px; }
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
        <a href="class_record.php" class="active">Class Record</a>
        <a href="pds_forms.php">PDS Forms</a>
        <a href="pds_records.php">PDS Records</a>
        <a href="pds_approvals.php">PDS Approvals</a>
        <a href="dtr.php">DTR</a>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <h2>Class Record</h2>

        <div class="quarter-tabs">
            <?php foreach ($quarter_labels as $val => $label): ?>
                <a href="class_record.php?quarter=<?= $val ?>" class="<?= $quarter === $val ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (!$all_exams): ?>
            <div class="empty-state">
                No exams have been tagged to <?= $quarter_labels[$quarter] ?> yet.
                Go to <a href="exams.php">Exams</a> and set a Quarter + Category when creating or editing an exam.
            </div>
        <?php else: ?>

            <?php
            // Pre-compute every student's row for every category, once
            $student_rows = [];
            foreach ($students as $s) {
                foreach ($category_meta as $cat => $meta) {
                    $student_rows[$s['id']][$cat] = computeCategory($exams_by_cat[$cat], $scores, $s['id'], $meta['weight']);
                }
            }
            ?>

            <?php foreach ($category_meta as $cat => $meta): $exams = $exams_by_cat[$cat]; ?>
                <h3 class="cat-title"><?= htmlspecialchars($meta['label']) ?> (<?= (int)($meta['weight'] * 100) ?>%)</h3>

                <?php if (!$exams): ?>
                    <p class="no-exams-note">No exams tagged to this category yet.</p>
                <?php else: ?>
                <table class="record">
                    <thead>
                        <tr>
                            <th class="name-col">Resident's Name</th>
                            <?php foreach ($exams as $i => $ex): ?>
                                <th title="<?= htmlspecialchars($ex['title']) ?>">
                                    <?= $ex['week_number'] ? $meta['col_prefix'] . (int)$ex['week_number'] : ($i + 1) ?>
                                </th>
                            <?php endforeach; ?>
                            <th>Total</th>
                            <th>PS</th>
                            <th>WS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="highest">
                            <td class="name">HIGHEST POSSIBLE SCORE</td>
                            <?php foreach ($exams as $ex): ?>
                                <td><?= (int)$ex['max_points'] ?></td>
                            <?php endforeach; ?>
                            <td><?= highestPossible($exams) ?></td>
                            <td>100.00</td>
                            <td><?= number_format($meta['weight'] * 100, 2) ?></td>
                        </tr>

                        <?php foreach ($students as $s): $row = $student_rows[$s['id']][$cat]; ?>
                        <tr>
                            <td class="name"><?= htmlspecialchars($s['full_name']) ?></td>
                            <?php foreach ($exams as $ex): $cell = $row['cells'][$ex['id']]; ?>
                                <?php if ($cell === null): ?>
                                    <td class="blank">&mdash;</td>
                                <?php elseif ($cell === 'pending'): ?>
                                    <td class="pending">Pending</td>
                                <?php else: ?>
                                    <td><?= $cell ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <td class="total"><?= $row['max_of_graded'] > 0 ? $row['total'] : '&mdash;' ?></td>
                            <td class="ps"><?= $row['ps'] !== null ? number_format($row['ps'], 2) : '&mdash;' ?></td>
                            <td class="ws"><?= $row['ws'] !== null ? number_format($row['ws'], 2) : '&mdash;' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php endforeach; ?>

            <h3 class="cat-title">Summary &mdash; Initial &amp; Quarterly Grade</h3>
            <table class="record">
                <thead>
                    <tr>
                        <th class="name-col">Resident's Name</th>
                        <th>Written WS</th>
                        <th>Clinical WS</th>
                        <th>Behavioral WS</th>
                        <th>Initial Grade</th>
                        <th>Quarterly Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s):
                        $w = $student_rows[$s['id']]['written']['ws'];
                        $c = $student_rows[$s['id']]['clinical']['ws'];
                        $b = $student_rows[$s['id']]['behavioral']['ws'];
                        $has_any = ($w !== null || $c !== null || $b !== null);
                        $initial = $has_any ? round(($w ?? 0) + ($c ?? 0) + ($b ?? 0), 2) : null;
                        $quarterly = $initial !== null ? round($initial) : null;
                    ?>
                    <tr>
                        <td class="name"><?= htmlspecialchars($s['full_name']) ?></td>
                        <td><?= $w !== null ? number_format($w, 2) : '&mdash;' ?></td>
                        <td><?= $c !== null ? number_format($c, 2) : '&mdash;' ?></td>
                        <td><?= $b !== null ? number_format($b, 2) : '&mdash;' ?></td>
                        <td class="total"><?= $initial !== null ? number_format($initial, 2) : '&mdash;' ?></td>
                        <td class="grade"><?= $quarterly !== null ? $quarterly : '&mdash;' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="legend">
                PS = Percentage Score (based on graded items only) &middot; WS = Weighted Score (PS &times; category weight) &middot;
                Initial Grade = sum of the 3 WS columns &middot; Quarterly Grade = Initial Grade rounded to the nearest whole number &middot;
                "Pending" = submitted but not yet graded &middot; "&mdash;" = not yet taken / no data
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
