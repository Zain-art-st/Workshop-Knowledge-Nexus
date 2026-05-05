<?php
session_start();
include "db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $credential = trim($_POST['credential']); // username or email
    $pass       = $_POST['password'];

    if (empty($credential) || empty($pass)) {
        $error = "Please fill in all fields.";
    } else {
        // Accept either email or username
        $stmt = mysqli_prepare($conn,
            "SELECT id, username, password, user_type, is_verified, is_suspended, is_banned
             FROM users WHERE email = ? OR username = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "ss", $credential, $credential);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            if ($row['is_banned']) {
                $error = "This account has been permanently banned.";
            } elseif ($row['is_suspended']) {
                $error = "This account is currently suspended.";
            } elseif (!$row['is_verified']) {
                $error = "Please verify your email first. <a href='verify_otp.php?email=" . urlencode($credential) . "' style='color:#4f8ef7;'>Resend OTP</a>";
            } elseif (password_verify($pass, $row['password'])) {
                $_SESSION['user_id']   = $row['id'];
                $_SESSION['username']  = $row['username'];
                $_SESSION['user_type'] = $row['user_type'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid credentials. Please try again.";
            }
        } else {
            $error = "Invalid credentials. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="stars-bg"></div>
  <div class="sunset-bg"></div>

  <div class="auth-page">
    <div class="auth-brand">ScholarSpace</div>

    <div class="auth-card">
      <div class="avatar-circle">👤</div>
      <h2 class="auth-card-title">Login</h2>

      <?php if ($error): ?>
        <div class="error-msg"><?php echo $error; ?></div>
      <?php endif; ?>

      <?php if (isset($_GET['registered'])): ?>
        <div class="success-msg">✅ Account verified! You can now log in.</div>
      <?php endif; ?>

      <?php if (isset($_GET['logout'])): ?>
        <div class="success-msg">You have been logged out.</div>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <div class="form-group">
          <label>Username or Email</label>
          <input type="text" name="credential"
                 placeholder="Enter username or email"
                 value="<?php echo htmlspecialchars($_POST['credential'] ?? ''); ?>"
                 required autocomplete="username">
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="password-wrapper">
            <input type="password" name="password" id="loginPassword"
                   placeholder="Enter your password" required autocomplete="current-password">
            <button type="button" class="toggle-pw" onclick="togglePw('loginPassword', this)">👁</button>
          </div>
        </div>
        <button type="submit" name="login" class="btn btn-primary" style="margin-top:8px;">Login</button>
      </form>

      <div class="auth-links">
        Don't have an account? <a href="register.php">Sign up</a>
      </div>
    </div>
  </div>

  <script>
  function togglePw(id, btn) {
    const input = document.getElementById(id);
    if (input.type === 'password') {
      input.type = 'text';
      btn.textContent = '🙈';
    } else {
      input.type = 'password';
      btn.textContent = '👁';
    }
  }
  </script>
</body>
</html>
