<?php
// ============================================================
// RUN THIS ONCE in your browser: http://localhost/your-project/generate_admin_hash.php
// It prints the real password hash for "NktiAnes2026".
// Copy the output and paste it into the users table (password_hash column)
// for the admin@nkti.gov.ph row, replacing the placeholder in schema.sql.
// DELETE this file after you're done, for security.
// ============================================================

$plain = 'NktiAnes2026';
$hash  = password_hash($plain, PASSWORD_BCRYPT);

echo "<h3>Copy this hash into the admin user's password_hash column:</h3>";
echo "<code style='font-size:16px;'>" . htmlspecialchars($hash) . "</code>";
echo "<p>Then delete this file (generate_admin_hash.php) from your server.</p>";
