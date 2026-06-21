<?php
session_start();
include "db.php";
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$username = $_SESSION['username'];
$user_type = $_SESSION['user_type'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ScholarSpace - Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="stars-bg"></div>
    <div class="sunset-bg"></div>
    
    <header class="navbar">
        <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
        <div class="nav-search"><input type="text" placeholder="Search anything..."></div>
        <div class="nav-right">
            <!-- Clickable text to trigger sidebar panel -->
            <span class="nav-welcome" style="cursor: pointer;" onclick="toggleSidebarPanel()">Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
            <input type="checkbox" id="profile-toggle" class="profile-toggle-checkbox">
            
            <a href="#">Settings</a>
            <?php if($user_type === 'admin'): ?><a href="#">Admin Dashboard</a><?php endif; ?>
            <hr>
            <a href="logout.php">Log Out</a>
        </div>
    </header>

    <!-- SIDEBAR PANEL OVERLAY -->
    <div id="custom-sidebar" class="sidebar-panel">
        <button class="sidebar-close-btn" onclick="toggleSidebarPanel()">&times;</button>
        
        <div class="sidebar-user-header">
            <div class="sidebar-avatar-placeholder">
                <?php echo strtoupper(substr($username, 0, 1)); ?>
            </div>
            <div class="sidebar-user-info">
                <h3><?php echo htmlspecialchars($username); ?></h3>
                <p>
                    <?php 
                        if ($user_type === 'student') echo '🎓 Student Account';
                        else if ($user_type === 'admin') echo '⚙️ System Administrator';
                        else echo '💼 Graduate Account';
                    ?>
                </p>
            </div>
        </div>
        
        <nav class="sidebar-links-wrapper">
            <a href="edit-profile.php" class="sidebar-link-item">
                <span class="sidebar-icon">✏️</span>
                <span class="sidebar-text">Edit profile</span>
            </a>
            <a href="your-posts.php" class="sidebar-link-item">
                <span class="sidebar-icon">📝</span>
                <span class="sidebar-text">Your post</span>
            </a>
            <a href="notifications.php" class="sidebar-link-item">
                <span class="sidebar-icon">🔔</span>
                <span class="sidebar-text">Notifications</span>
            </a>
            <a href="about.php" class="sidebar-link-item">
                <span class="sidebar-icon">ℹ️</span>
                <span class="sidebar-text">About</span>
            </a>
            <a href="add-account.php" class="sidebar-link-item">
                <span class="sidebar-icon">➕</span>
                <span class="sidebar-text">Add account</span>
            </a>
            <a href="create-sub.php" class="sidebar-link-item">
                <span class="sidebar-icon">🚀</span>
                <span class="sidebar-text">Create your own sub!</span>
            </a>
            <?php if($user_type === 'admin'): ?>
            <a href="admin.php" class="sidebar-link-item admin-highlight">
                <span class="sidebar-icon">🛡️</span>
                <span class="sidebar-text">Administrator Dashboard</span>
            </a>
            <?php endif; ?>
        </nav>
    </div>

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

    <!-- Toggle Action Script -->
    <script>
        function toggleSidebarPanel() {
            const panel = document.getElementById('custom-sidebar');
            panel.classList.toggle('panel-open');
        }
    </script>
</body>
</html>
