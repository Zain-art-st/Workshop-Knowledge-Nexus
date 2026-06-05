<?php
session_start();
include "db.php";

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_otp'])) {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        //Check if email exists
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_fetch_assoc($result)) {
            //Generate OTP
            $otp = sprintf("%06d", mt_rand(1, 999999));
            
            //expiry 15 minute
            $update_stmt = mysqli_prepare($conn, "UPDATE users SET reset_otp = ?, reset_otp_expiry = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE email = ?");
            mysqli_stmt_bind_param($update_stmt, "ss", $otp, $email);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $subject = "ScholarSpace - Password Reset OTP";
                $message = "Your password reset OTP is: $otp\n\nThis OTP will expire in 15 minutes.";
                $headers = "From: noreply@scholarspace.com";
                
                @mail($email, $subject, $message, $headers); // Suppress errors(can check database for otp)
                
                header("Location: reset_password.php?email=" . urlencode($email));
                exit();
            } else {
                $error = "Failed to generate OTP. Please try again.";
            }
        } else {//message for security
            $error = "If that email exists in our system, an OTP has been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="stars-bg"></div>
  <div class="sunset-bg"></div>

  <div class="auth-page">
    <div class="auth-brand">ScholarSpace</div>

    <div class="auth-card">
      <div class="avatar-circle">🔒</div>
      <h2 class="auth-card-title">Forgot Password</h2>
      <p style="text-align: center; color: #ccc; margin-bottom: 20px; font-size: 14px;">Enter your email to receive a password reset OTP.</p>

      <?php if ($error): ?>
        <div class="error-msg" style="color: #ff6b6b; margin-bottom: 15px;"><?php echo $error; ?></div>
      <?php endif; ?>

      <form method="POST" action="forgot_password.php">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="Enter your registered email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
        </div>
        
        <button type="submit" name="request_otp" class="btn btn-primary" style="margin-top:8px;">Send OTP</button>
      </form>

      <div class="auth-links" style="margin-top: 15px; text-align: center;">
        <a href="login.php">Back to Login</a>
      </div>
    </div>
  </div>
</body>
</html>