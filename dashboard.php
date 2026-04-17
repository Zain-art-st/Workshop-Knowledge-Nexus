<?php
session_start();

// Redirect if neither a user nor a guest is present
if (!isset($_SESSION['user']) && !isset($_SESSION['guest'])) {
    header("Location: login.php");
    exit();
}

$displayName = $_SESSION['user'];
$isGuest = isset($_SESSION['guest']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ScholarSpace - Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="dashboard-body">
    <header class="navbar">
        <div class="nav-left">
            </div>
        <div class="nav-center">
            <a href="dashboard.php" class="main-logo">ScholarSpace</a> </div>
        <div class="nav-right">
            <span style="margin-right: 15px; font-size: 14px;">
                Welcome, <strong><?php echo htmlspecialchars($displayName); ?></strong>
            </span>
            <input type="checkbox" id="profile-toggle" class="profile-toggle-checkbox">
            <label for="profile-toggle" class="profile-avatar"></label>
            <div class="profile-menu">
                <?php if(!$isGuest): ?>
                    <a href="#">My Profile</a>
                    <a href="#">User Settings</a>
                <?php else: ?>
                    <a href="login.php">Login / Register</a>
                <?php endif; ?>
                <hr>
                <a href="logout.php">Log Out</a>
            </div>
        </div>
    </header>

    <div class="layout-container">
        <main class="main-content">
             <div class="card" style="border-left: 5px solid <?php echo $isGuest ? '#ff4f4f' : '#33a8ff'; ?>;">
                <h3>Status: <?php echo $isGuest ? "Guest Mode (Limited Access)" : "Verified Member"; ?></h3>
                <p>Welcome to ScholarSpace, <?php echo htmlspecialchars($displayName); ?>.
                   <?php if($isGuest) echo "As a guest, you can browse content but cannot post comments or save favorites. <a href='login.php'>Login</a> for full features."; ?>
                </p>
             </div>

             <div class="placeholder-grid">
                <div class="card">
                    <h3>Latest Research Article</h3>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation.</p>
                    <a href="#" style="color:#33a8ff; font-size:12px; text-decoration:none;">Read More...</a>
                </div>

                <div class="card">
                    <h3>Upcoming Workshops</h3>
                    <p>Discover our calendar of upcoming academic workshops. Topics include data visualization, advanced Python for research, and grant writing strategies.</p>
                    <a href="#" style="color:#33a8ff; font-size:12px; text-decoration:none;">View Calendar...</a>
                </div>

                <div class="card">
                    <h3>Community Discussions</h3>
                    <p>Join the conversation in our forums. This week's featured topic: "The Impact of AI on Peer Review." Share your insights with the community.</p>
                    <a href="#" style="color:#33a8ff; font-size:12px; text-decoration:none;">Go to Forums...</a>
                </div>
             </div>
        </main>
    </div>
</body>
</html>