<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('super_admin');
require_once __DIR__ . '/../config/db_connect.php';

$intern_filter = (int)($_GET['user_id'] ?? 0);
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to'] ?? '');
if ($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) { $date_from = ''; }
if ($date_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) { $date_to = ''; }

// --------------------------------------------------------------
// Overview: every intern's required / completed / remaining hours
// --------------------------------------------------------------
$overview = $pdo->query(
    "SELECT u.id, u.full_name, u.school_name, u.course, u.required_hours,
            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sub.time_in, sub.time_out)), 0) / 60 AS completed_hours
     FROM users u
     LEFT JOIN (
        SELECT user_id,
               MIN(CASE WHEN log_type = 'time_in' THEN log_time END) AS time_in,
               MAX(CASE WHEN log_type = 'time_out' THEN log_time END) AS time_out
        FROM dtr_logs
        GROUP BY user_id, DATE(log_time)
     ) sub ON sub.user_id = u.id
     WHERE u.role = 'intern' AND u.status = 'active'
     GROUP BY u.id
     ORDER BY u.full_name ASC"
)->fetchAll();

// --------------------------------------------------------------
// Daily log table (filterable)
// --------------------------------------------------------------
$sql = "SELECT u.id AS user_id, u.full_name,
               DATE(d.log_time) AS log_date,
               MIN(CASE WHEN d.log_type = 'time_in' THEN d.log_time END) AS time_in,
               MAX(CASE WHEN d.log_type = 'time_out' THEN d.log_time END) AS time_out
        FROM dtr_logs d
        JOIN users u ON u.id = d.user_id
        WHERE u.role = 'intern'";
$params = [];

if ($intern_filter) {
    $sql .= ' AND u.id = ?';
    $params[] = $intern_filter;
}
if ($date_from) {
    $sql .= ' AND DATE(d.log_time) >= ?';
    $params[] = $date_from;
}
if ($date_to) {
    $sql .= ' AND DATE(d.log_time) <= ?';
    $params[] = $date_to;
}

$sql .= ' GROUP BY u.id, DATE(d.log_time) ORDER BY log_date DESC, u.full_name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$days = $stmt->fetchAll();

foreach ($days as &$d) {
    $in_photo = $pdo->prepare(
        "SELECT photo_path FROM dtr_logs WHERE user_id = ? AND DATE(log_time) = ? AND log_type = 'time_in' ORDER BY log_time ASC LIMIT 1"
    );
    $in_photo->execute([$d['user_id'], $d['log_date']]);
    $d['time_in_photo'] = $in_photo->fetchColumn();

    $out_photo = $pdo->prepare(
        "SELECT photo_path FROM dtr_logs WHERE user_id = ? AND DATE(log_time) = ? AND log_type = 'time_out' ORDER BY log_time DESC LIMIT 1"
    );
    $out_photo->execute([$d['user_id'], $d['log_date']]);
    $d['time_out_photo'] = $out_photo->fetchColumn();

    if ($d['time_in'] && $d['time_out']) {
        $d['hours_worked'] = round((strtotime($d['time_out']) - strtotime($d['time_in'])) / 3600, 2);
    } else {
        $d['hours_worked'] = null;
    }
}
unset($d);

$interns = $pdo->query("SELECT id, full_name FROM users WHERE role = 'intern' AND status = 'active' ORDER BY full_name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Biometrics Device - Super Admin</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f2f4f7; }
    .layout { display: flex; min-height: 100vh; }
    .sidebar { width: 260px; background: #0d2d4e; color: #fff; display: flex; flex-direction: column; padding: 24px 0; }
    .sidebar .brand { padding: 0 24px 24px 24px; }
    .sidebar .brand strong { display:block; font-size: 16px; }
    .sidebar .brand span { display:block; font-size: 12px; color: #b8c6e0; margin-top:4px; }
    .sidebar a { color: #d7e0f2; text-decoration: none; padding: 12px 24px; font-size: 14px; display: block; }
    .sidebar a.active { background: rgba(255,255,255,0.12); color: #fff; font-weight: bold; }
    .sidebar a:hover { background: rgba(255,255,255,0.08); }
    .sidebar .bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px; }

    .main { flex: 1; padding: 30px 40px; }
    .topbar { margin-bottom: 22px; }
    .topbar h2 { color: #0d2d4e; margin: 0 0 16px 0; }

    .section-title { color: #0d2d4e; font-size: 16px; margin: 30px 0 14px 0; }

    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    th, td { text-align: left; padding: 12px 16px; font-size: 13px; border-bottom: 1px solid #eef0f3; vertical-align: middle; }
    th { background: #f8f9fb; color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
    tr:last-child td { border-bottom: none; }

    .progress-bar { width: 140px; height: 8px; background: #eef0f3; border-radius: 4px; overflow: hidden; }
    .progress-bar .fill { height: 100%; background: #0ea5e9; }
    .progress-bar .fill.done { background: #1e7a34; }
    .hrs-remaining { font-weight: bold; color: #0d2d4e; }
    .hrs-remaining.done { color: #1e7a34; }
    .badge-done { background:#eaf7ee; color:#1e7a34; padding:3px 9px; border-radius:10px; font-size:11px; font-weight:bold; }

    .filter-bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .filter-bar label { font-size: 12px; color: #6b7280; margin-right: -4px; }
    .filter-bar select, .filter-bar input[type=date] {
        padding: 11px 12px; border: 1px solid #d1d5db; border-radius: 5px; font-size: 13px; background:#fff;
    }
    .filter-bar button, .filter-bar a.action-btn {
        padding: 11px 18px; border: none; border-radius: 5px;
        background: #0ea5e9; color: #fff; font-size: 13px; font-weight: bold; cursor: pointer; text-decoration:none;
    }
    .filter-bar button:hover, .filter-bar a.action-btn:hover { background: #0c8bc4; }
    .filter-bar a.reset-link { font-size: 13px; color: #6b7280; text-decoration:none; }
    .filter-bar a.kiosk-link {
        margin-left: auto; padding: 11px 16px; border-radius: 5px; font-size: 13px; font-weight: bold;
        background: #fff; border: 1px solid #d1d5db; color: #4b5563; text-decoration: none;
    }

    .thumb-row { display:flex; align-items:center; gap: 8px; }
    .thumb { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid #e5e7eb; cursor:pointer; }
    .thumb.placeholder { background:#eef0f3; }
    .time-in { color: #1e7a34; font-weight: bold; }
    .time-out { color: #a06b0a; font-weight: bold; }
    .missing { color: #d1d5db; }
    .view-btn {
        font-size: 12px; color: #0ea5e9; text-decoration: none; font-weight: bold; margin-left: 4px; cursor:pointer;
    }
    .empty-state { background:#fff; border-radius:8px; padding: 40px; text-align:center; color:#6b7280; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .result-count { font-size: 13px; color: #6b7280; margin: 10px 0; }

    /* Modal */
    .modal-overlay {
        display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
        align-items:center; justify-content:center; z-index: 999;
    }
    .modal-overlay.open { display:flex; }
    .modal-box { background:#fff; border-radius: 10px; padding: 26px; width: 420px; max-width: 92%; text-align:center; }
    .modal-box h4 { margin: 0 0 4px 0; color:#0d2d4e; }
    .modal-box .modal-meta { font-size: 13px; color:#6b7280; margin-bottom: 18px; }
    .modal-photos { display:flex; gap: 16px; justify-content:center; margin-bottom: 18px; }
    .modal-photo-block { flex:1; }
    .modal-photo-block img { width: 100%; aspect-ratio:1; object-fit:cover; border-radius: 8px; border:1px solid #eef0f3; }
    .modal-photo-block .no-photo { width:100%; aspect-ratio:1; background:#f8f9fb; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:12px; }
    .modal-photo-block .lbl { font-size:11px; color:#6b7280; margin-top:6px; text-transform:uppercase; font-weight:bold; }
    .modal-close { padding: 10px 24px; border:none; border-radius:5px; background:#4b5563; color:#fff; font-size:13px; font-weight:bold; cursor:pointer; }
</style>
</head>
<body>
<div class="layout">
    <div class="sidebar">
        <div class="brand">
            <strong>Super Admin</strong>
            <span>NKTI Anesthesiology &middot; Intern Tracking</span>
        </div>
        <a href="dashboard.php">Dashboard</a>
        <a href="biometrics_device.php" class="active">Biometrics Device</a>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <div class="topbar">
            <h2>Biometrics Device</h2>
        </div>

        <h3 class="section-title">Intern Hours Overview</h3>
        <?php if ($overview): ?>
        <table>
            <thead>
                <tr>
                    <th>Intern</th>
                    <th>School</th>
                    <th>Course</th>
                    <th>Required Hrs</th>
                    <th>Completed Hrs</th>
                    <th>Progress</th>
                    <th>Remaining</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($overview as $o):
                    $required = $o['required_hours'] !== null ? (float)$o['required_hours'] : null;
                    $completed = round((float)$o['completed_hours'], 2);
                    $remaining = $required !== null ? round($required - $completed, 2) : null;
                    $pct = ($required && $required > 0) ? min(100, round(($completed / $required) * 100)) : 0;
                    $is_done = $remaining !== null && $remaining <= 0;
                ?>
                <tr>
                    <td><a href="biometrics_device.php?user_id=<?= (int)$o['id'] ?>" style="color:#0d2d4e; font-weight:bold; text-decoration:none;"><?= htmlspecialchars($o['full_name']) ?></a></td>
                    <td><?= htmlspecialchars($o['school_name'] ?: '&mdash;') ?></td>
                    <td><?= htmlspecialchars($o['course'] ?: '&mdash;') ?></td>
                    <td><?= $required !== null ? number_format($required, 1) : '&mdash;' ?></td>
                    <td><?= number_format($completed, 1) ?></td>
                    <td>
                        <div class="progress-bar"><div class="fill <?= $is_done ? 'done' : '' ?>" style="width:<?= $pct ?>%;"></div></div>
                        <span style="font-size:11px; color:#9ca3af;"><?= $pct ?>%</span>
                    </td>
                    <td>
                        <?php if ($is_done): ?>
                            <span class="badge-done">Completed</span>
                        <?php else: ?>
                            <span class="hrs-remaining"><?= $remaining !== null ? number_format($remaining, 1) . ' hrs' : '&mdash;' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state">No interns yet. Create one from Admin &gt; Add New User.</div>
        <?php endif; ?>

        <h3 class="section-title">Daily Time Record</h3>
        <form method="GET" action="biometrics_device.php" class="filter-bar">
            <select name="user_id">
                <option value="0">All Interns</option>
                <?php foreach ($interns as $i): ?>
                    <option value="<?= (int)$i['id'] ?>" <?= $intern_filter === (int)$i['id'] ? 'selected' : '' ?>><?= htmlspecialchars($i['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>From</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            <label>To</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            <button type="submit">Filter</button>
            <a class="reset-link" href="biometrics_device.php">Reset / Show All</a>
            <a class="action-btn" href="dtr_export.php?user_id=<?= $intern_filter ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">&#8595; Export CSV</a>
            <a class="kiosk-link" href="../biometrics/intern_kiosk.php" target="_blank">Open Intern Kiosk &#8599;</a>
        </form>

        <?php if ($days): ?>
            <div class="result-count"><?= count($days) ?> day record<?= count($days) == 1 ? '' : 's' ?></div>
            <table>
                <thead>
                    <tr>
                        <th>Intern</th>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($days as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['full_name']) ?></td>
                        <td><?= date('M j, Y (D)', strtotime($d['log_date'])) ?></td>
                        <td>
                            <?php if ($d['time_in']): ?>
                                <div class="thumb-row">
                                    <?php if ($d['time_in_photo']): ?>
                                        <img class="thumb" src="../uploads/dtr/<?= htmlspecialchars($d['time_in_photo']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="thumb placeholder"></div>
                                    <?php endif; ?>
                                    <span class="time-in"><?= date('h:i A', strtotime($d['time_in'])) ?></span>
                                    <span class="view-btn"
                                        onclick='openDtrModal(<?= json_encode($d['full_name']) ?>, <?= json_encode(date('M j, Y', strtotime($d['log_date']))) ?>, "Time In", <?= json_encode(date('h:i A', strtotime($d['time_in']))) ?>, <?= json_encode($d['time_in_photo'] ? '../uploads/dtr/' . $d['time_in_photo'] : null) ?>)'>View</span>
                                </div>
                            <?php else: ?>
                                <span class="missing">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($d['time_out']): ?>
                                <div class="thumb-row">
                                    <?php if ($d['time_out_photo']): ?>
                                        <img class="thumb" src="../uploads/dtr/<?= htmlspecialchars($d['time_out_photo']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="thumb placeholder"></div>
                                    <?php endif; ?>
                                    <span class="time-out"><?= date('h:i A', strtotime($d['time_out'])) ?></span>
                                    <span class="view-btn"
                                        onclick='openDtrModal(<?= json_encode($d['full_name']) ?>, <?= json_encode(date('M j, Y', strtotime($d['log_date']))) ?>, "Time Out", <?= json_encode(date('h:i A', strtotime($d['time_out']))) ?>, <?= json_encode($d['time_out_photo'] ? '../uploads/dtr/' . $d['time_out_photo'] : null) ?>)'>View</span>
                                </div>
                            <?php else: ?>
                                <span class="missing">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $d['hours_worked'] !== null ? number_format($d['hours_worked'], 2) . ' hrs' : '&mdash;' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">No DTR records yet for this filter.</div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="dtrModal">
    <div class="modal-box">
        <h4 id="modalName"></h4>
        <div class="modal-meta" id="modalMeta"></div>
        <div class="modal-photos">
            <div class="modal-photo-block">
                <div id="modalPhotoWrap"></div>
            </div>
        </div>
        <button class="modal-close" onclick="document.getElementById('dtrModal').classList.remove('open')">Close</button>
    </div>
</div>

<script>
function openDtrModal(name, date, type, time, photoUrl) {
    document.getElementById('modalName').textContent = name;
    document.getElementById('modalMeta').textContent = type + ' — ' + date + ' at ' + time;
    const wrap = document.getElementById('modalPhotoWrap');
    if (photoUrl) {
        wrap.innerHTML = '<img src="' + photoUrl + '" alt="Captured photo">';
    } else {
        wrap.innerHTML = '<div class="no-photo">No photo captured</div>';
    }
    document.getElementById('dtrModal').classList.add('open');
}
document.getElementById('dtrModal').addEventListener('click', function (e) {
    if (e.target === this) this.classList.remove('open');
});
</script>
</body>
</html>
