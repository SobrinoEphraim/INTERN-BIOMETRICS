<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role(['trainee', 'consultant', 'rater']);
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

// Exams tagged to this quarter, targeted to this user's role (or all)
$exam_stmt = $pdo->prepare(
    'SELECT e.*, (SELECT SUM(points) FROM exam_questions q WHERE q.exam_id = e.id) AS max_points
     FROM exams e
     WHERE e.status = "published" AND e.quarter = ?
       AND (e.target_role = ? OR e.target_role = "all")
     ORDER BY (e.week_number IS NULL), e.week_number ASC, e.title ASC'
);
$exam_stmt->execute([$quarter, $_SESSION['role']]);
$all_exams = $exam_stmt->fetchAll();

$exams_by_cat = ['written' => [], 'clinical' => [], 'behavioral' => []];
foreach ($all_exams as $e) {
    $exams_by_cat[$e['category']][] = $e;
}

// This user's graded submissions for these exams, keyed by exam_id
$my_scores = [];
if ($all_exams) {
    $exam_ids = array_column($all_exams, 'id');
    $placeholders = implode(',', array_fill(0, count($exam_ids), '?'));
    $sub_stmt = $pdo->prepare(
        "SELECT exam_id, total_score, grading_status
         FROM exam_submissions
         WHERE exam_id IN ($placeholders) AND status = 'submitted' AND user_id = ?"
    );
    $sub_stmt->execute(array_merge($exam_ids, [$_SESSION['user_id']]));
    foreach ($sub_stmt->fetchAll() as $row) {
        $my_scores[$row['exam_id']] = $row;
    }
}

function computeCategory($exams, $my_scores, $weight) {
    $total = 0;
    $max_of_graded = 0;
    $cells = [];
    foreach ($exams as $ex) {
        $row = $my_scores[$ex['id']] ?? null;
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

$category_rows = [];
foreach ($category_meta as $cat => $meta) {
    $category_rows[$cat] = computeCategory($exams_by_cat[$cat], $my_scores, $meta['weight']);
}

$w = $category_rows['written']['ws'];
$c = $category_rows['clinical']['ws'];
$b = $category_rows['behavioral']['ws'];
$has_any = ($w !== null || $c !== null || $b !== null);
$initial = $has_any ? round(($w ?? 0) + ($c ?? 0) + ($b ?? 0), 2) : null;
$quarterly = $initial !== null ? round($initial) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Class Record - Trainee Evaluation System</title>
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
        border: 1px solid #e5e7eb; padding: 10px 14px; font-size: 14px; text-align: center;
    }
    table.record th { background: #f8f9fb; color: #4b5563; font-weight: bold; }
    table.record td.pending { color: #a06b0a; font-style: italic; }
    table.record td.blank { color: #d1d5db; }
    table.record td.total, table.record td.ps, table.record td.ws { font-weight: bold; color: #1a3a6b; background: #f8f9fb; }

    .no-exams-note { font-size: 12px; color: #9ca3af; margin-bottom: 20px; }

    .summary-cards { display: flex; gap: 16px; margin: 20px 0 30px 0; flex-wrap: wrap; }
    .summary-card { background: #fff; border-radius: 8px; padding: 18px 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-align: center; min-width: 140px; }
    .summary-card .label { font-size: 12px; color: #6b7280; text-transform: uppercase; margin-bottom: 6px; }
    .summary-card .value { font-size: 24px; font-weight: bold; color: #1a3a6b; }
    .summary-card.grade { background: #4a7fd4; }
    .summary-card.grade .label { color: #dbe9ff; }
    .summary-card.grade .value { color: #fff; }

    .empty-state { background:#fff; border-radius:8px; padding: 50px; text-align:center; color:#6b7280; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .legend { font-size: 12px; color: #6b7280; margin-top: 14px; }
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
        <a href="class_record.php" class="active">Class Record</a>
        <a href="pds.php">PDS</a>
        <a href="dtr.php">My DTR</a>
        <?php if ($_SESSION['role'] === 'consultant'): ?>
        <a href="exam_reviews.php">Review Exams</a>
        <?php endif; ?>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <h2>My Class Record</h2>

        <div class="quarter-tabs">
            <?php foreach ($quarter_labels as $val => $label): ?>
                <a href="class_record.php?quarter=<?= $val ?>" class="<?= $quarter === $val ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (!$all_exams): ?>
            <div class="empty-state">
                No class record exams have been posted for <?= $quarter_labels[$quarter] ?> yet.
            </div>
        <?php else: ?>

            <?php foreach ($category_meta as $cat => $meta): $exams = $exams_by_cat[$cat]; $row = $category_rows[$cat]; ?>
                <h3 class="cat-title"><?= htmlspecialchars($meta['label']) ?> (<?= (int)($meta['weight'] * 100) ?>%)</h3>

                <?php if (!$exams): ?>
                    <p class="no-exams-note">No exams tagged to this category yet.</p>
                <?php else: ?>
                <table class="record">
                    <thead>
                        <tr>
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
                        <tr>
                            <?php foreach ($exams as $ex): $cell = $row['cells'][$ex['id']]; ?>
                                <?php if ($cell === null): ?>
                                    <td class="blank">&mdash;</td>
                                <?php elseif ($cell === 'pending'): ?>
                                    <td class="pending">Pending</td>
                                <?php else: ?>
                                    <td><?= $cell ?> / <?= (int)$ex['max_points'] ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <td class="total"><?= $row['max_of_graded'] > 0 ? $row['total'] : '&mdash;' ?></td>
                            <td class="ps"><?= $row['ps'] !== null ? number_format($row['ps'], 2) : '&mdash;' ?></td>
                            <td class="ws"><?= $row['ws'] !== null ? number_format($row['ws'], 2) : '&mdash;' ?></td>
                        </tr>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="summary-cards">
                <div class="summary-card">
                    <div class="label">Written WS</div>
                    <div class="value"><?= $w !== null ? number_format($w, 2) : '&mdash;' ?></div>
                </div>
                <div class="summary-card">
                    <div class="label">Clinical WS</div>
                    <div class="value"><?= $c !== null ? number_format($c, 2) : '&mdash;' ?></div>
                </div>
                <div class="summary-card">
                    <div class="label">Behavioral WS</div>
                    <div class="value"><?= $b !== null ? number_format($b, 2) : '&mdash;' ?></div>
                </div>
                <div class="summary-card">
                    <div class="label">Initial Grade</div>
                    <div class="value"><?= $initial !== null ? number_format($initial, 2) : '&mdash;' ?></div>
                </div>
                <div class="summary-card grade">
                    <div class="label">Quarterly Grade</div>
                    <div class="value"><?= $quarterly !== null ? $quarterly : '&mdash;' ?></div>
                </div>
            </div>

            <div class="legend">
                "Pending" = submitted, waiting for grading &middot; "&mdash;" = not yet taken / no data &middot;
                This is a view-only record &mdash; scores are set by your admin/consultant.
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
