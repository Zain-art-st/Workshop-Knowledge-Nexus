<?php
session_start();
include "db.php";

// Handle Guest Login
if (isset($_POST['guest_login'])) {
    $_SESSION['guest'] = true;
    $_SESSION['user'] = "Guest";
    header("Location: dashboard.php");
    exit();
}

// Handle Secure User Login
if (isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // PREPARED STATEMENT to prevent SQL Injection
    $stmt = mysqli_prepare($conn, "SELECT username FROM users WHERE username=? AND password=?");
    mysqli_stmt_bind_param($stmt, "ss", $user, $pass);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        $_SESSION['user'] = $row['username'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid login credentials";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - ScholarSpace</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-body"> <div class="container">
    <div class="login-box">
        <div class="logo">ScholarSpace</div> <div class="profile-circle">👤</div> <?php if(isset($error)) echo "<p style='color:#ff4f4f; font-size:12px; margin-bottom: 10px;'>$error</p>"; ?>

        <form method="POST" action="login.php">
            <input type="email" name="username" placeholder="Email (Username)" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
            <button type="submit" name="guest_login">Browse as Guest</button>
        </form>

        <p class="register-text">
            Don't have an account? <a href="register.php">Register here</a>
        </p>
    </div>
</div>
</body>
</html>