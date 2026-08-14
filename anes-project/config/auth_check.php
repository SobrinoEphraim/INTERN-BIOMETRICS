<?php
// ============================================================
// Include this at the TOP of any protected page.
// Usage:
//   require_once __DIR__ . '/../config/auth_check.php';
//   require_role('admin');           // only admins allowed
//   require_role(['trainee','consultant','rater']); // any of these
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function require_role($allowed_roles) {
    require_login();

    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    if (!in_array($_SESSION['role'], $allowed_roles, true)) {
        // Logged in, but wrong role — send them to their own dashboard
        if ($_SESSION['role'] === 'admin') {
            header('Location: /admin/dashboard.php');
        } elseif ($_SESSION['role'] === 'super_admin') {
            header('Location: /superadmin/dashboard.php');
        } elseif ($_SESSION['role'] === 'intern') {
            header('Location: /intern/dashboard.php');
        } else {
            header('Location: /user/dashboard.php');
        }
        exit;
    }
}
