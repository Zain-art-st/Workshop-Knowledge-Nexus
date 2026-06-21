<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/PHPMailer/src/Exception.php";
require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";


// ─── CONFIGURE YOUR GMAIL HERE ───────────────────────────────────────────────
define('MAIL_FROM',     'yeohjiapoh@gmail.com');  
define('MAIL_PASSWORD', 'uirk mxkf tktw ptek');        
define('MAIL_FROM_NAME','ScholarSpace');
// ─────────────────────────────────────────────────────────────────────────────


//CONFIG
define('MAIL_FROM', 'yeohjiapoh@gmail.com'); // swap with ur email 
define('MAIL_PASSWORD', 'uqpe mxct siek iier'); // app password in google account make sure 2 step is active
define('MAIL_FROM_NAME', 'ScholarSpace');

// ─── CONFIGURE YOUR GMAIL HERE ───────────────────────────────────────────────
define("MAIL_FROM", "ashleesia@gmail.com");
define("MAIL_PASSWORD", "jtrq ugpq exyw lmre");
define("MAIL_FROM_NAME", "Scholarsssspace");
// ─────────────────────────────────────────────────────────────────────────────


function sendOTPEmail(string $toEmail, string $toName, string $otp): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();

        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_FROM;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;



        $mail->Host = 'smtp.gmail.com';

        $mail->Host = "smtp.gmail.com";

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
        $mail->Body    = '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;background:#0d0d1a;color:#f0eff5;border-radius:16px;overflow:hidden;">
          <div style="background:linear-gradient(135deg,#4f8ef7,#c06de8);padding:32px;text-align:center;">
            <h1 style="margin:0;font-size:28px;letter-spacing:-1px;">ScholarSpace</h1>
            <p style="margin:8px 0 0;opacity:0.85;font-size:14px;">Email Verification</p>
          </div>

          <div style="padding:32px;text-align:center;">
            <p style="color:#9b9ab0;font-size:14px;margin-bottom:8px;">Hello <strong style="color:#f0eff5;">' . htmlspecialchars($toName) . '</strong>, here\'s your one-time verification code:</p>
            <div style="background:#1a1a2e;border:1px solid rgba(255,255,255,0.12);border-radius:12px;padding:24px;margin:24px 0;display:inline-block;width:100%;">
              <span style="font-size:42px;font-weight:800;letter-spacing:12px;color:#4f8ef7;">' . $otp . '</span>

          <div style="padding: 30px; text-align: center;">
            <p style="color: #9b9ab0; font-size: 14px;">Hello <b>' . htmlspecialchars($toName) . '</b>, use the OTP below to verify your account:</p>
            <div style="background: #1a1a2e; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 20px; margin: 20px 0; display: inline-block; width: 90%;">
              <span style="font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #4f8ef7;">' . $otp . '</span>


        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = "Your ScholarSpace Verification Code";
        $mail->Body =
            '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;background:#0d0d1a;color:#f0eff5;border-radius:16px;overflow:hidden;">
          <div style="background:linear-gradient(135deg,#4f8ef7,#c06de8);padding:32px;text-align:center;">
            <h1 style="margin:0;font-size:28px;letter-spacing:-1px;">ScholarSpace</h1>
            <p style="margin:8px 0 0;opacity:0.85;font-size:14px;">Email Verification</p>
          </div>
          <div style="padding:32px;text-align:center;">
            <p style="color:#9b9ab0;font-size:14px;margin-bottom:8px;">Hello <strong style="color:#f0eff5;">' .
            htmlspecialchars($toName) .
            '</strong>, here\'s your one-time verification code:</p>
            <div style="background:#1a1a2e;border:1px solid rgba(255,255,255,0.12);border-radius:12px;padding:24px;margin:24px 0;display:inline-block;width:100%;">
              <span style="font-size:42px;font-weight:800;letter-spacing:12px;color:#4f8ef7;">' .
            $otp .
            '</span>


            </div>
            <p style="color:#9b9ab0;font-size:13px;">This code expires in <strong style="color:#f0eff5;">10 minutes</strong>.</p>
            <p style="color:#9b9ab0;font-size:12px;margin-top:24px;">If you did not request this, please ignore this email.</p>
          </div>
        </div>';
        $mail->AltBody = "Your ScholarSpace OTP code is: $otp — expires in 10 minutes.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer error: " . $mail->ErrorInfo);
        return false;
    }
}
?>
