<?php
session_start();
include "db.php";
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$username  = $_SESSION['username'];
$user_type = $_SESSION['user_type'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>ScholarSpace – Dashboard</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="stars-bg"></div>
  <div class="sunset-bg"></div>
  <header class="navbar">
    <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
    <div class="nav-search"><input type="text" placeholder="Search anything…"></div>
    <div class="nav-right">
      <span class="nav-welcome">Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
      <input type="checkbox" id="profile-toggle" class="profile-toggle-checkbox">
      <label for="profile-toggle" class="profile-avatar"><?php echo strtoupper(substr($username,0,1)); ?></label>
      <div class="profile-menu">
        <a href="#">My Profile</a>
        <a href="#">Settings</a>
        <?php if($user_type === 'admin'): ?><a href="#">Admin Dashboard</a><?php endif; ?>
        <hr>
        <a href="logout.php">Log Out</a>
      </div>
    </div>
  </header>
  <div class="page-wrapper">
    <div style="max-width:700px;margin:0 auto;padding:0 20px;">
      <div class="welcome-banner card">
        <div>
          <h2>Welcome back, <?php echo htmlspecialchars($username); ?>! 👋</h2>
          <p>Dashboard coming soon — Batch 2 is next!</p>
        </div>
        <span class="status-badge <?php echo $user_type; ?>">
          <?php echo $user_type === 'student' ? '🎓 Student' : ($user_type === 'admin' ? '⚙️ Admin' : '💼 Graduate'); ?>
        </span>
      </div>
    </div>
  </div>
</body>
</html>
