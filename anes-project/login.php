<?php
session_start();
require_once __DIR__ . '/config/db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'active') {
            $error = 'This account has been disabled. Contact your administrator.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } else {
            // Success — start session
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role'];

            // update last login timestamp
            $upd = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
            $upd->execute([$user['id']]);

            // Force password reset on first login (default access code)
            if ((int)$user['must_reset_password'] === 1) {
                header('Location: reset_password.php');
                exit;
            }

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } elseif ($user['role'] === 'super_admin') {
                header('Location: superadmin/dashboard.php');
            } elseif ($user['role'] === 'intern') {
                header('Location: intern/dashboard.php');
            } else {
                header('Location: user/dashboard.php');
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Trainee Evaluation System</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Segoe UI', Arial, sans-serif;
        background-color: #1a3a6b;
    }
    .login-card {
        width: 460px;
        max-width: 92%;
        background-color: #ffffff;
        border-top: 4px solid #4a90d9;
        border-radius: 6px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        padding: 50px 40px 40px 40px;
        text-align: center;
    }
    .logo { display: block; margin: 0 auto 30px auto; max-width: 220px; }
    .login-card h1 {
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 26px;
        color: #1a1a1a;
        margin: 0 0 6px 0;
        text-align: left;
    }
    .login-card .subtitle {
        text-align: left;
        font-size: 12px;
        letter-spacing: 0.5px;
        color: #6b7280;
        margin: 0 0 30px 0;
        text-transform: uppercase;
    }
    .form-group { text-align: left; margin-bottom: 20px; }
    .form-group label {
        display: block;
        font-size: 12px;
        font-weight: bold;
        letter-spacing: 0.5px;
        color: #4b5563;
        margin-bottom: 8px;
        text-transform: uppercase;
    }
    .form-group input {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #d1d5db;
        border-radius: 5px;
        font-size: 14px;
        color: #333;
    }
    .form-group input:focus {
        outline: none;
        border-color: #4a90d9;
        box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.15);
    }
    .sign-in-btn {
        width: 100%;
        padding: 14px;
        border: none;
        border-radius: 5px;
        background-color: #4a7fd4;
        color: #fff;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        margin-top: 6px;
        transition: background-color 0.2s ease;
    }
    .sign-in-btn:hover { background-color: #3c6cc0; }
    .forgot-link {
        display: block;
        text-align: center;
        margin-top: 18px;
        font-size: 14px;
        font-weight: bold;
        color: #3b6fd6;
        text-decoration: underline;
    }
    .forgot-link:hover { color: #2c56ab; }
    .helper-text {
        text-align: left;
        font-size: 13px;
        color: #6b7280;
        line-height: 1.5;
        margin-top: 22px;
    }
    .error-box {
        background-color: #fdecea;
        color: #b3261e;
        border: 1px solid #f5c2c0;
        border-radius: 5px;
        padding: 10px 14px;
        font-size: 13px;
        text-align: left;
        margin-bottom: 18px;
    }
</style>
</head>
<body>

    <div class="login-card">
        <img class="logo" src="images/anesthlogo.png" alt="Anesthesiology NKTI Logo">

        <h1>Trainee Evaluation System</h1>
        <p class="subtitle">NKTI &middot; Department of Anesthesiology</p>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                       value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
            </div>

            <div class="form-group">
                <label for="password">Password or Default Access Code</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="sign-in-btn">Sign in</button>
            <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
        </form>

        <p class="helper-text">
            First time here? Sign in with the default access code
            NktiAnes2026. You set your own password right after.
        </p>
    </div>

</body>
</html>
