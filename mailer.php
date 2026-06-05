<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

//CONFIG
define('MAIL_FROM', 'afiflegend2006@gmail.com'); // swap with ur email 
define('MAIL_PASSWORD', 'yfeq gsnm dqdv wqjr'); // app password in google account make sure 2 step is active
define('MAIL_FROM_NAME', 'ScholarSpace');

function sendOTPEmail(string $toEmail, string $toName, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_FROM;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
//Content
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        
        $mail->Subject = 'Your ScholarSpace Verification Code';
        $mail->Body = '
        <div style="font-family: sans-serif; max-width: 460px; margin: 0 auto; background: #0d0d1a; color: #f0eff5; border-radius: 12px; overflow: hidden;">
          <div style="background: linear-gradient(135deg, #4f8ef7, #c06de8); padding: 25px; text-align: center;">
            <h1 style="margin: 0; font-size: 26px;">ScholarSpace</h1>
            <p style="margin: 5px 0 0; opacity: 0.8; font-size: 13px;">Email Verification</p>
          </div>
          <div style="padding: 30px; text-align: center;">
            <p style="color: #9b9ab0; font-size: 14px;">Hello <b>' . htmlspecialchars($toName) . '</b>, use the OTP below to verify your account:</p>
            <div style="background: #1a1a2e; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 20px; margin: 20px 0; display: inline-block; width: 90%;">
              <span style="font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #4f8ef7;">' . $otp . '</span>
            </div>
            <p style="color: #9b9ab0; font-size: 13px;">This code will expire in <b>10 minutes</b>.</p>
            <p style="color: #666; font-size: 11px; margin-top: 20px;">Didn\'t request this? Just ignore this email.</p>
          </div>
        </div>';
        
        $mail->AltBody = "Your ScholarSpace OTP code is: $otp (Expires in 10 minutes)";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>