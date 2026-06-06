<?php
session_start();
include "db.php";

$error = "";
$email = isset($_GET['email']) ? $_GET['email'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $email = trim($_POST['email']);
    $otp = trim($_POST['otp']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($otp) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) { // Adjust based on your current password policy
        $error = "Password must be at least 6 characters long.";
    } else {
        //check expiration
    $stmt = mysqli_prepare($conn, "SELECT id, expires_at FROM otp_codes WHERE email = ? AND otp = ? AND used = 0 ORDER BY created_at DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ss", $email, $otp);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $otp_row = mysqli_fetch_assoc($result);

        if ($otp_row && strtotime($otp_row['expires_at']) > time()) {
            //Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE email = ?");            
            mysqli_stmt_bind_param($update_stmt, "ss", $hashed_password, $email);
            
            if (mysqli_stmt_execute($update_stmt)) {

                // Mark OTP as used
                $used_stmt = mysqli_prepare($conn, "UPDATE otp_codes SET used = 1 WHERE id = ?");
                mysqli_stmt_bind_param($used_stmt, "i", $otp_row['id']);
                mysqli_stmt_execute($used_stmt);

                header("Location: login.php?reset=success");
                exit();
            } else {
                $error = "Failed to update password. Please try again.";
            }
        } else {
            $error = "Invalid or expired OTP. Please request a new one.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="stars-bg"></div>
  <div class="sunset-bg"></div>

  <div class="auth-page">
    <div class="auth-brand">ScholarSpace</div>

    <div class="auth-card">
      <div class="avatar-circle">🔑</div>
      <h2 class="auth-card-title">Reset Password</h2>

      <?php if ($error): ?>
        <div class="error-msg" style="color: #ff6b6b; margin-bottom: 15px;"><?php echo $error; ?></div>
      <?php endif; ?>

      <form method="POST" action="reset_password.php?email=<?php echo htmlspecialchars($email); ?>">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
        
        <div class="form-group">
          <label>6-Digit OTP</label>
          <input type="text" name="otp" placeholder="Enter the OTP sent to your email" required maxlength="6" pattern="\d{6}">
        </div>

        <div class="form-group">
          <label>New Password</label>
          <div class="password-wrapper">
            <input type="password" name="new_password" id="newPassword" placeholder="Enter new password" required>
            <button type="button" class="toggle-pw" onclick="togglePasswordVisibility('newPassword', this)">👁</button>
          </div>
        </div>

        <div class="form-group">
          <label>Confirm New Password</label>
          <div class="password-wrapper">
            <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm new password" required>
            <button type="button" class="toggle-pw" onclick="togglePasswordVisibility('confirmPassword', this)">👁</button>
          </div>
        </div>

        <button type="submit" name="reset_password" class="btn btn-primary" style="margin-top:8px;">Reset Password</button>
      </form>

      <div class="auth-links" style="margin-top: 15px; text-align: center;">
        Did not receive the OTP? <a href="forgot_password.php">Request again</a>
      </div>
    </div>
  </div>

  <script>
  function togglePasswordVisibility(fieldId, buttonEl) {
    const passwordField = document.getElementById(fieldId);
    if (passwordField.type === 'password') {
      passwordField.type = 'text';
      buttonEl.textContent = '🙈';
    } else {
      passwordField.type = 'password';
      buttonEl.textContent = '👁';
    }
  }
  </script>
</body>
</html>