<?php
// ============================================================
// Database connection (PDO + MySQL)
// Edit these 4 values to match your XAMPP / MySQL setup.
// Default XAMPP MySQL: host=localhost, user=root, password="" (blank)
// ============================================================

$DB_HOST = 'localhost';
$DB_NAME = 'trainee_eval_system';
$DB_USER = 'root';
$DB_PASS = ''; // blank by default on XAMPP

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
