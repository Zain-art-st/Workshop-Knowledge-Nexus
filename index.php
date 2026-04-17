<?php
session_start();

// If already logged in (User or Guest), go to dashboard
if (isset($_SESSION['user']) || isset($_SESSION['guest'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Welcome - ScholarSpace</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body style="display:flex; justify-content:center; align-items:center; height:100vh; background: #030303;">
    <div class="card" style="padding: 40px; text-align: center; width: 300px;">
        <h1 class="logo" style="margin-bottom: 20px;">ScholarSpace</h1>
        <a href="login.php" class="join-btn" style="display:block; text-decoration:none; margin-bottom:10px; background: #33a8ff; color: white; border:none;">Login</a>
        <form method="POST" action="login.php">
             <button type="submit" name="guest_login" class="join-btn">Continue as Guest</button>
        </form>
    </div>
</body>
</html>