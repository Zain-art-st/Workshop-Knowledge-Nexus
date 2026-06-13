<?php
// ================================================================
//  Run this ONCE after importing setup.sql
//  Visit: http://localhost/Workshop ScholarSpace/admin_reset.php
//  Then DELETE this file immediately after!
// ================================================================
include "db.php";

$newPassword = 'Admin@1234';
$hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE username = 'admin'");
mysqli_stmt_bind_param($stmt, "s", $hash);

if (mysqli_stmt_execute($stmt)) {
    echo "<h2 style='font-family:sans-serif;color:green;'>✅ Admin password set successfully!</h2>";
    echo "<p style='font-family:sans-serif;'>Username: <strong>admin</strong><br>Password: <strong>Admin@1234</strong></p>";
    echo "<p style='font-family:sans-serif;color:red;'><strong>⚠️ DELETE this file now!</strong></p>";
} else {
    echo "<h2 style='font-family:sans-serif;color:red;'>❌ Failed: " . mysqli_error($conn) . "</h2>";
}
?>
