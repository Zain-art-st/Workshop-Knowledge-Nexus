<?php
session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div>

<header class="navbar">
  <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-right" style="margin-left:auto;">
    <a href="dashboard.php" style="font-size:13px; color:var(--text-muted); text-decoration:none;">← Back</a>
  </div>
</header>

<div class="page-wrapper">
  <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:60vh; padding:40px 20px; text-align:center;">
    <div style="font-family:var(--font-display); font-size:32px; font-weight:800; background:linear-gradient(135deg,#fff,var(--accent)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; margin-bottom:16px;">
      ScholarSpace
    </div>
    <div style="font-size:15px; color:var(--text-muted); letter-spacing:.2px;">
      Not very social, eh? No notifications yet.
    </div>
  </div>
</div>
</body>
</html>