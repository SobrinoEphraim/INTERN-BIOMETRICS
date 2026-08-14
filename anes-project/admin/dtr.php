<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

$employee_filter = (int)($_GET['user_id'] ?? 0);
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to'] ?? '');

// basic validation of date inputs
if ($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) { $date_from = ''; }
if ($date_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) { $date_to = ''; }

$sql = "SELECT u.id AS user_id, u.full_name,
               DATE(d.log_time) AS log_date,
               MIN(CASE WHEN d.log_type = 'time_in' THEN d.log_time END) AS time_in,
               MAX(CASE WHEN d.log_type = 'time_out' THEN d.log_time END) AS time_out
        FROM dtr_logs d
        JOIN users u ON u.id = d.user_id
        WHERE u.role NOT IN ('admin', 'super_admin', 'intern')";
$params = [];

if ($employee_filter) {
    $sql .= ' AND u.id = ?';
    $params[] = $employee_filter;
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
}
unset($d);

// Employees for the filter dropdown
$employees = $pdo->query(
    "SELECT id, full_name, role FROM users WHERE role NOT IN ('admin', 'super_admin', 'intern') AND status = 'active' ORDER BY full_name ASC"
)->fetchAll();

// build export URL carrying the same filters
$export_qs = http_build_query(['user_id' => $employee_filter, 'date_from' => $date_from, 'date_to' => $date_to]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DTR - Admin</title>
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

    .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .filter-bar label { font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: bold; margin-right: 4px; }
    .filter-bar select, .filter-bar input[type=date] {
        padding: 11px 14px; border: 1px solid #d1d5db; border-radius: 5px; font-size: 14px; background:#fff;
    }
    .filter-bar select { width: 240px; }
    .filter-bar button, .filter-bar a.btn-link {
        padding: 11px 18px; border: none; border-radius: 5px;
        background: #4a7fd4; color: #fff; font-size: 14px; font-weight: bold; cursor: pointer; text-decoration: none;
        display: inline-block;
    }
    .filter-bar button:hover, .filter-bar a.btn-link:hover { background: #3c6cc0; }
    .filter-bar a.reset-link { font-size: 13px; color: #6b7280; text-decoration: none; }
    .filter-bar a.reset-link:hover { text-decoration: underline; }
    .filter-bar a.export-link {
        padding: 11px 18px; border-radius: 5px; background: #fff; border: 1px solid #d1d5db;
        color: #4b5563; font-size: 14px; font-weight: bold; text-decoration: none;
    }
    .filter-bar a.export-link:hover { background: #f8f9fb; }
    .filter-bar a.kiosk-link {
        margin-left: auto; padding: 11px 18px; border-radius: 5px; font-size: 13px; font-weight: bold;
        background: #fff; border: 1px solid #d1d5db; color: #4b5563; text-decoration: none;
    }

    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-top: 20px; }
    th, td { text-align: left; padding: 14px 16px; font-size: 14px; border-bottom: 1px solid #eef0f3; vertical-align: middle; }
    th { background: #f8f9fb; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    tr:last-child td { border-bottom: none; }

    .thumb-row { display:flex; align-items:center; gap: 10px; }
    .thumb { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 1px solid #e5e7eb; }
    .thumb.placeholder { background:#eef0f3; }
    .time-in { color: #1e7a34; font-weight: bold; }
    .time-out { color: #a06b0a; font-weight: bold; }
    .missing { color: #d1d5db; }

    .view-btn {
        font-size: 12px; font-weight: bold; padding: 6px 12px; border-radius: 4px;
        border: 1px solid #d1d5db; background: #fff; color: #4b5563; cursor: pointer;
    }
    .view-btn:hover { background: #f8f9fb; }

    .empty-state { background:#fff; border-radius:8px; padding: 50px; text-align:center; color:#6b7280; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-top: 20px; }
    .result-count { font-size: 13px; color: #6b7280; margin-top: 10px; }

    /* Photo modal */
    .modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
        align-items: center; justify-content: center; z-index: 1000; padding: 20px;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
        background: #fff; border-radius: 10px; padding: 26px; max-width: 640px; width: 100%;
        max-height: 90vh; overflow-y: auto;
    }
    .modal-box h3 { margin: 0 0 4px 0; color: #1a3a6b; }
    .modal-box .modal-meta { font-size: 13px; color: #6b7280; margin-bottom: 18px; }
    .modal-photos { display: flex; gap: 20px; flex-wrap: wrap; }
    .modal-photo-col { flex: 1; min-width: 220px; text-align: center; }
    .modal-photo-col .label { font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 8px; }
    .modal-photo-col .label.in { color: #1e7a34; }
    .modal-photo-col .label.out { color: #a06b0a; }
    .modal-photo-col img { width: 100%; max-width: 260px; border-radius: 8px; border: 1px solid #eef0f3; }
    .modal-photo-col .no-photo { padding: 40px 10px; color: #b0b6c0; font-size: 13px; background: #f8f9fb; border-radius: 8px; }
    .modal-photo-col .time-label { margin-top: 8px; font-size: 13px; font-weight: bold; }
    .modal-close-btn {
        margin-top: 22px; padding: 10px 20px; border: none; border-radius: 6px;
        background: #4a7fd4; color: #fff; font-size: 14px; font-weight: bold; cursor: pointer;
    }
    .modal-close-btn:hover { background: #3c6cc0; }
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
        <a href="pds_approvals.php">PDS Approvals</a>
        <a href="dtr.php" class="active">DTR</a>
        <div class="bottom">
            <a href="../logout.php">Sign out</a>
        </div>
    </div>

    <div class="main">
        <div class="topbar">
            <h2>Daily Time Record (DTR)</h2>
            <form method="GET" action="dtr.php" class="filter-bar">
                <div>
                    <label>Employee</label><br>
                    <select name="user_id">
                        <option value="0">All Employees</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= (int)$e['id'] ?>" <?= $employee_filter === (int)$e['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars(ucfirst($e['role'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>From</label><br>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div>
                    <label>To</label><br>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div style="align-self:flex-end;">
                    <button type="submit">Filter</button>
                </div>
                <div style="align-self:flex-end;">
                    <a class="reset-link" href="dtr.php">Reset / Show All</a>
                </div>
                <div style="align-self:flex-end;">
                    <a class="export-link" href="dtr_export.php?<?= htmlspecialchars($export_qs) ?>">&#8595; Export CSV</a>
                </div>
                <a class="kiosk-link" href="../biometrics/index.php" target="_blank">Open Biometrics Kiosk &#8599;</a>
            </form>
        </div>

        <?php if ($days): ?>
            <div class="result-count"><?= count($days) ?> day record<?= count($days) == 1 ? '' : 's' ?></div>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Photos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($days as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['full_name']) ?></td>
                        <td><?= date('F j, Y (D)', strtotime($d['log_date'])) ?></td>
                        <td>
                            <?php if ($d['time_in']): ?>
                                <div class="thumb-row">
                                    <?php if ($d['time_in_photo']): ?>
                                        <img class="thumb" src="../uploads/dtr/<?= htmlspecialchars($d['time_in_photo']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="thumb placeholder"></div>
                                    <?php endif; ?>
                                    <span class="time-in"><?= date('h:i A', strtotime($d['time_in'])) ?></span>
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
                                </div>
                            <?php else: ?>
                                <span class="missing">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="view-btn" onclick="openPhotoModal(
                                '<?= htmlspecialchars(addslashes($d['full_name'])) ?>',
                                '<?= date('F j, Y (D)', strtotime($d['log_date'])) ?>',
                                <?= $d['time_in_photo'] ? "'../uploads/dtr/" . htmlspecialchars($d['time_in_photo'], ENT_QUOTES) . "'" : 'null' ?>,
                                <?= $d['time_out_photo'] ? "'../uploads/dtr/" . htmlspecialchars($d['time_out_photo'], ENT_QUOTES) . "'" : 'null' ?>,
                                <?= $d['time_in'] ? "'" . date('h:i A', strtotime($d['time_in'])) . "'" : 'null' ?>,
                                <?= $d['time_out'] ? "'" . date('h:i A', strtotime($d['time_out'])) . "'" : 'null' ?>
                            )">View</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">No DTR records match this filter.</div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="photoModal">
    <div class="modal-box">
        <h3 id="modalName"></h3>
        <div class="modal-meta" id="modalDate"></div>
        <div class="modal-photos">
            <div class="modal-photo-col">
                <div class="label in">Time In</div>
                <div id="modalInWrap"></div>
                <div class="time-label" id="modalInTime" style="color:#1e7a34;"></div>
            </div>
            <div class="modal-photo-col">
                <div class="label out">Time Out</div>
                <div id="modalOutWrap"></div>
                <div class="time-label" id="modalOutTime" style="color:#a06b0a;"></div>
            </div>
        </div>
        <button class="modal-close-btn" onclick="closePhotoModal()">Close</button>
    </div>
</div>

<script>
    function openPhotoModal(name, dateStr, inPhoto, outPhoto, inTime, outTime) {
        document.getElementById('modalName').textContent = name;
        document.getElementById('modalDate').textContent = dateStr;

        document.getElementById('modalInWrap').innerHTML = inPhoto
            ? '<img src="' + inPhoto + '" alt="Time In photo">'
            : '<div class="no-photo">No photo captured</div>';
        document.getElementById('modalInTime').textContent = inTime || '';

        document.getElementById('modalOutWrap').innerHTML = outPhoto
            ? '<img src="' + outPhoto + '" alt="Time Out photo">'
            : '<div class="no-photo">No photo captured</div>';
        document.getElementById('modalOutTime').textContent = outTime || '';

        document.getElementById('photoModal').classList.add('open');
    }
    function closePhotoModal() {
        document.getElementById('photoModal').classList.remove('open');
    }
    document.getElementById('photoModal').addEventListener('click', function (e) {
        if (e.target === this) closePhotoModal();
    });
</script>
</body>
</html>
