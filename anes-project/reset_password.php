<?php
require_once __DIR__ . '/config/auth_check.php';
require_login();
require_once __DIR__ . '/config/db_connect.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (strlen($new_pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new_pass !== $confirm_pass) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($new_pass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, must_reset_password = 0 WHERE id = ?');
        $stmt->execute([$hash, $_SESSION['user_id']]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Set Your Password</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; display: flex;
        align-items: center; justify-content: center;
        font-family: 'Segoe UI', Arial, sans-serif;
        background-color: #1a3a6b;
    }
    .card {
        width: 420px; max-width: 92%; background: #fff;
        border-top: 4px solid #4a90d9; border-radius: 6px;
        box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        padding: 40px; text-align: left;
    }
    h1 { font-family: Georgia, serif; font-size: 22px; margin: 0 0 6px 0; }
    p.subtitle { color: #6b7280; font-size: 13px; margin: 0 0 26px 0; }
    .form-group { margin-bottom: 18px; }
    label { display:block; font-size:12px; font-weight:bold; color:#4b5563; margin-bottom:6px; text-transform:uppercase; }
    input {
        width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
        border-radius: 5px; font-size: 14px;
    }
    button {
        width: 100%; padding: 14px; border: none; border-radius: 5px;
        background: #4a7fd4; color: #fff; font-size: 15px; font-weight: bold;
        cursor: pointer;
    }
    button:hover { background: #3c6cc0; }
    .error-box { background:#fdecea; color:#b3261e; border:1px solid #f5c2c0;
        border-radius:5px; padding:10px 14px; font-size:13px; margin-bottom:16px; }
    .success-box { background:#eaf7ee; color:#1e7a34; border:1px solid #b9e6c4;
        border-radius:5px; padding:14px; font-size:14px; }
    a { color:#3b6fd6; font-weight:bold; }
</style>
</head>
<body>
    <div class="card">
        <h1>Set Your Password</h1>
        <p class="subtitle">You signed in with the default access code. Please set a personal password to continue.</p>

        <?php if ($success): ?>
            <div class="success-box">
                Password updated! You can now
                <a href="<?php
                    if ($_SESSION['role'] === 'admin') echo 'admin/dashboard.php';
                    elseif ($_SESSION['role'] === 'super_admin') echo 'superadmin/dashboard.php';
                    elseif ($_SESSION['role'] === 'intern') echo 'intern/dashboard.php';
                    else echo 'user/dashboard.php';
                ?>">
                    continue to your dashboard</a>.
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error-box"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" action="reset_password.php">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                <button type="submit">Save Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
