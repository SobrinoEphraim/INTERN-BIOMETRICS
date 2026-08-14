<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('super_admin');
require_once __DIR__ . '/../config/db_connect.php';

$intern_filter = (int)($_GET['user_id'] ?? 0);
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to'] ?? '');
if ($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) { $date_from = ''; }
if ($date_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) { $date_to = ''; }

$sql = "SELECT u.full_name,
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

$sql .= ' GROUP BY u.id, DATE(d.log_time) ORDER BY log_date ASC, u.full_name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$name_part = 'all-interns';
if ($intern_filter) {
    $emp_stmt = $pdo->prepare('SELECT full_name FROM users WHERE id = ?');
    $emp_stmt->execute([$intern_filter]);
    $emp_name = $emp_stmt->fetchColumn();
    if ($emp_name) {
        $name_part = preg_replace('/[^A-Za-z0-9]+/', '_', $emp_name);
    }
}
$range_part = ($date_from || $date_to)
    ? '_' . ($date_from ?: 'start') . '_to_' . ($date_to ?: 'now')
    : '';
$filename = 'Intern_DTR_' . $name_part . $range_part . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Intern', 'Date', 'Time In', 'Time Out', 'Hours Worked']);

foreach ($rows as $r) {
    $hours = '';
    if ($r['time_in'] && $r['time_out']) {
        $hours = round((strtotime($r['time_out']) - strtotime($r['time_in'])) / 3600, 2);
    }
    fputcsv($out, [
        $r['full_name'],
        date('F j, Y (D)', strtotime($r['log_date'])),
        $r['time_in'] ? date('h:i A', strtotime($r['time_in'])) : '',
        $r['time_out'] ? date('h:i A', strtotime($r['time_out'])) : '',
        $hours,
    ]);
}

fclose($out);
exit;
