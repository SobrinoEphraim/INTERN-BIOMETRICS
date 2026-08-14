<?php
require_once __DIR__ . '/../config/auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db_connect.php';

$DEFAULT_ACCESS_CODE = 'NktiAnes2026'; // same code shown on the login page

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $role      = $_POST['role'] ?? 'trainee';

    $required_hours = trim($_POST['required_hours'] ?? '');
    $school_name    = trim($_POST['school_name'] ?? '');
    $course         = trim($_POST['course'] ?? '');

    $allowed_roles = ['admin', 'trainee', 'consultant', 'rater', 'super_admin', 'intern'];

    if ($full_name === '' || $email === '') {
        $error = 'Full name and email are required.';
    } elseif (!in_array($role, $allowed_roles, true)) {
        $error = 'Invalid role selected.';
    } elseif ($role === 'intern' && ($required_hours === '' || $school_name === '' || $course === '')) {
        $error = 'Please fill out required hours, school, and course for an intern account.';
    } else {
        // check duplicate email
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = 'A user with that email already exists.';
        } else {
            $hash = password_hash($DEFAULT_ACCESS_CODE, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash, role, status, must_reset_password, required_hours, school_name, course)
                 VALUES (?, ?, ?, ?, "active", 1, ?, ?, ?)'
            );
            $stmt->execute([
                $full_name, $email, $hash, $role,
                $role === 'intern' ? (float)$required_hours : null,
                $role === 'intern' ? $school_name : null,
                $role === 'intern' ? $course : null,
            ]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add New User - Admin</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f2f4f7; }
    .layout { display: flex; min-height: 100vh; }
    .sidebar {
        width: 260px; background: #1a3a6b; color: #fff;
        display: flex; flex-direction: column; padding: 24px 0;
    }
    .sidebar .brand { padding: 0 24px 24px 24px; }
    .sidebar .brand strong { display:block; font-size: 16px; }
    .sidebar .brand span { display:block; font-size: 12px; color: #b8c6e0; margin-top:4px; }
    .sidebar a { color: #d7e0f2; text-decoration: none; padding: 12px 24px; font-size: 14px; display: block; }
    .sidebar a.active { background: rgba(255,255,255,0.12); color: #fff; font-weight: bold; }
    .sidebar a:hover { background: rgba(255,255,255,0.08); }
    .sidebar .section-label { padding: 18px 24px 6px 24px; font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase; color: #8fa3c8; }
    .sidebar .bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px; }

    .main { flex: 1; padding: 30px 40px; max-width: 640px; }
    .main h2 { color: #1a3a6b; margin-top: 0; }

    .panel { background: #fff; border-radius: 8px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .form-group { margin-bottom: 18px; }
    label { display:block; font-size:12px; font-weight:bold; color:#4b5563; margin-bottom:6px; text-transform:uppercase; }
    input, select {
        width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
        border-radius: 5px; font-size: 14px;
    }
    button {
        padding: 12px 24px; border: none; border-radius: 5px;
        background: #4a7fd4; color: #fff; font-size: 14px; font-weight: bold; cursor: pointer;
    }
    button:hover { background: #3c6cc0; }
    .error-box { background:#fdecea; color:#b3261e; border:1px solid #f5c2c0; border-radius:5px; padding:10px 14px; font-size:13px; margin-bottom:16px; }
    .success-box { background:#eaf7ee; color:#1e7a34; border:1px solid #b9e6c4; border-radius:5px; padding:14px; font-size:14px; margin-bottom:16px; }
    .note { font-size: 12px; color: #6b7280; margin-top: 16px; }
    a.back { color:#3b6fd6; font-size: 13px; text-decoration:none; }
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
        <a href="create_user.php" class="active">Add New User</a>
        <a href="assign_evaluations.php">Assign Evaluations</a>
        <a href="exams.php">Exams</a>
        <a href="grades.php">Grades</a>
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
        <p><a class="back" href="dashboard.php">&larr; Back to Dashboard</a></p>
        <h2>Add New User</h2>

        <div class="panel">
            <?php if ($success): ?>
                <div class="success-box">
                    User created successfully. They can now sign in using the
                    default access code <strong>NktiAnes2026</strong> and will be asked
                    to set their own password on first login.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-box"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="create_user.php">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required onchange="toggleInternFields(this)">
                        <option value="trainee">Trainee (Resident/Fellow)</option>
                        <option value="consultant">Consultant</option>
                        <option value="rater">Rater (Peer)</option>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                        <option value="intern">Intern</option>
                    </select>
                </div>

                <div id="internFields" style="display:none;">
                    <div class="form-group">
                        <label for="required_hours">Required Internship Hours</label>
                        <input type="number" id="required_hours" name="required_hours" min="1" step="1" placeholder="e.g. 200">
                    </div>
                    <div class="form-group">
                        <label for="school_name">School</label>
                        <input type="text" id="school_name" name="school_name" placeholder="e.g. University of the Philippines">
                    </div>
                    <div class="form-group">
                        <label for="course">Course</label>
                        <input type="text" id="course" name="course" placeholder="e.g. BS Nursing">
                    </div>
                </div>

                <button type="submit">Create User</button>
            </form>

            <p class="note">
                New accounts are created with the default access code as their
                initial password. Users are required to set a personal password
                the first time they sign in.
            </p>
        </div>
    </div>
</div>

<script>
    function toggleInternFields(selectEl) {
        const box = document.getElementById('internFields');
        const isIntern = selectEl.value === 'intern';
        box.style.display = isIntern ? 'block' : 'none';
        document.getElementById('required_hours').required = isIntern;
        document.getElementById('school_name').required = isIntern;
        document.getElementById('course').required = isIntern;
    }
</script>
</body>
</html>
