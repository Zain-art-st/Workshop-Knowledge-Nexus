<?php
session_start();
include "db.php";

if (isset($_SESSION['user_id'])) { header("Location: dashboard.php"); exit(); }

$error   = "";
$success = "";
$email   = $_SESSION['otp_email'] ?? $_GET['email'] ?? '';
if (empty($email)) { header("Location: register.php"); exit(); }

//Resend OTP
if (isset($_POST['resend_otp'])) {
    $chk = mysqli_prepare($conn,"SELECT username FROM pending_registrations WHERE email=?");
    mysqli_stmt_bind_param($chk,"s",$email); mysqli_stmt_execute($chk);
    $res = mysqli_stmt_get_result($chk);
    if ($row = mysqli_fetch_assoc($res)) {
        $otp = str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
        $del = mysqli_prepare($conn,"DELETE FROM otp_codes WHERE email=?");
        mysqli_stmt_bind_param($del,"s",$email); mysqli_stmt_execute($del);
        $ins = mysqli_prepare($conn,
            "INSERT INTO otp_codes (email,otp,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))");
        mysqli_stmt_bind_param($ins,"ss",$email,$otp); mysqli_stmt_execute($ins);
        include_once "mailer.php";
        $sent = sendOTPEmail($email,$row['username'],$otp);
        $success = $sent ? "✅ New OTP sent!" : "⚠️ Could not send email. Check mailer.php.";
    } else { $error="No pending registration found."; }
}

//Verify OTP
  if (isset($_POST['verify_otp'])) {
    $digits = [];
    for ($i=1;$i<=6;$i++) $digits[]=trim($_POST["d$i"]??'');
    $entered_otp = implode('',$digits);
      if (strlen($entered_otp)!==6||!ctype_digit($entered_otp)) {
        $error = "Please enter all 6 digits.";
    
        } else {
        $stmt = mysqli_prepare($conn,
            "SELECT id, expires_at FROM otp_codes WHERE email=? AND otp=? AND used=0 ORDER BY created_at DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt,"ss",$email,$entered_otp);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $otp_row = mysqli_fetch_assoc($res);
          if ($otp_row && strtotime($otp_row['expires_at']) < time()) {
            $error = "OTP has expired. Please resend."; $otp_row = null;
        }

        if ($otp_row) {// Mark OTP used
            $upd = mysqli_prepare($conn,"UPDATE otp_codes SET used=1 WHERE id=?");
            mysqli_stmt_bind_param($upd,"i",$otp_row['id']); mysqli_stmt_execute($upd);

            //pending registration
            $preg = mysqli_prepare($conn,"SELECT * FROM pending_registrations WHERE email=? LIMIT 1");
            mysqli_stmt_bind_param($preg,"s",$email); mysqli_stmt_execute($preg);
            $pres = mysqli_stmt_get_result($preg);

            if ($pdata = mysqli_fetch_assoc($pres)) {
                $extra = json_decode($pdata['extra_data'],true) ?? [];

                //KYC status
                $kyc_image  = $extra['kyc_image'] ?? null;
                $kyc_status = $kyc_image ? 'pending' : 'none';

                $ins = mysqli_prepare($conn,
                    "INSERT INTO users (username,email,password,user_type,profile_photo,is_verified,kyc_status,kyc_image)
                     VALUES (?,?,?,?,?,1,?,?)");
                mysqli_stmt_bind_param($ins,"sssssss",
                    $pdata['username'],$pdata['email'],$pdata['password_hash'],
                    $pdata['user_type'],$pdata['profile_photo'],
                    $kyc_status,$kyc_image);

                if (mysqli_stmt_execute($ins)) {
                    $new_user_id = mysqli_insert_id($conn);

                    if ($pdata['user_type']==='student') {
                        $s=mysqli_prepare($conn,"INSERT INTO student_profiles (user_id,matric_number) VALUES (?,?)");
                        $matric = $extra['matric_number'] ?? ''; // Assign to variable first
                        mysqli_stmt_bind_param($s,"is",$new_user_id,$matric);
                        mysqli_stmt_execute($s);
                    } elseif ($pdata['user_type']==='graduate') {
                        $g=mysqli_prepare($conn,
                            "INSERT INTO graduate_profiles
                             (user_id,job_status,company,job_title,salary_range,education_level,field_of_study,graduation_year,linkedin_url,bio,skills)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                        
                        // Assign all fallback expressions to variables first to prevent pass-by-reference fatal errors
                        $job_status = $extra['job_status'] ?? 'unemployed';
                        $company = $extra['company'] ?? '';
                        $job_title = $extra['job_title'] ?? '';
                        $salary_range = $extra['salary_range'] ?? '';
                        $education_level = $extra['education_level'] ?? 'bachelor';
                        $field_of_study = $extra['field_of_study'] ?? '';
                        $gy = !empty($extra['graduation_year']) ? $extra['graduation_year'] : null;
                        $linkedin_url = $extra['linkedin_url'] ?? '';
                        $bio = $extra['bio'] ?? '';
                        $skills = $extra['skills'] ?? '';

                        mysqli_stmt_bind_param($g,"issssssssss",
                            $new_user_id,
                            $job_status,
                            $company,
                            $job_title,
                            $salary_range,
                            $education_level,
                            $field_of_study,
                            $gy,
                            $linkedin_url,
                            $bio,
                            $skills
                        );
                        mysqli_stmt_execute($g);
                    }

                    //auto join some subs for demo
                    $subs=mysqli_query($conn,"SELECT id FROM subcommunities LIMIT 3");
                    while($sub=mysqli_fetch_assoc($subs)){
                        $jn=mysqli_prepare($conn,"INSERT IGNORE INTO sub_memberships (user_id,sub_id) VALUES (?,?)");
                        mysqli_stmt_bind_param($jn,"ii",$new_user_id,$sub['id']);
                        mysqli_stmt_execute($jn);
                    }

                    //clear pending
                    $del=mysqli_prepare($conn,"DELETE FROM pending_registrations WHERE email=?");
                    mysqli_stmt_bind_param($del,"s",$email); mysqli_stmt_execute($del);

                    unset($_SESSION['otp_email'],$_SESSION['reg_username'],
                          $_SESSION['reg_email'],$_SESSION['reg_pass'],$_SESSION['reg_auto_type'],
                          $_SESSION['reg_kyc_pending']);

                  //redirectKYC status
                    if ($kyc_status === 'pending') {
                        header("Location: login.php?registered=1&kyc=pending");
                    } else {
                        header("Location: login.php?registered=1");
                    }
                    exit();
                } else { $error="Account creation failed: ".mysqli_error($conn); }
            } else { $error="Registration data not found. Please register again."; }
        } else {
            if (!$error) $error="Invalid or expired OTP. Please try again or resend.";
        }
    }
}

$masked = preg_replace('/(?<=.{2}).(?=.*@)/','*',$email);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify OTP ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .otp-inputs { display:flex;gap:10px;justify-content:center;margin:24px 0; }
    .otp-inputs input {
      width:48px;height:56px;text-align:center;font-size:22px;font-weight:700;
      background:rgba(255,255,255,.08);border:2px solid var(--card-border);
      border-radius:10px;color:var(--text-main);outline:none;transition:border-color .2s;
    }
    .otp-inputs input:focus { border-color:var(--accent);background:rgba(79,142,247,.1); }
    .otp-inputs input.filled { border-color:var(--success); }
    .countdown { font-size:12px;color:var(--text-muted);text-align:center;margin-bottom:16px; }
    .countdown span { color:var(--accent);font-weight:700; }
    .resend-link { background:none;border:none;color:var(--accent);cursor:pointer;font-size:13px;text-decoration:underline;font-family:var(--font-body); }
    .resend-link:disabled { color:var(--text-muted);cursor:default;text-decoration:none; }
  </style>
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div>
<div class="auth-page">
  <div class="auth-brand">ScholarSpace</div>
  <div class="auth-card" style="max-width:400px;text-align:center;">
    <div style="font-size:48px;margin-bottom:12px;">📬</div>
    <h2 class="auth-card-title">Check Your Email</h2>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:8px;">
      We sent a 6-digit code to<br>
      <strong style="color:var(--text-main);"><?php echo htmlspecialchars($masked); ?></strong>
    </p>

    <?php if ($error):   ?><div class="error-msg"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success-msg"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if (isset($_GET['mail_error'])): ?>
    <div class="error-msg" style="font-size:12px;">
      Email not sent. Fill in <strong>mailer.php</strong> with your Gmail credentials, then resend.
    </div>
    <?php endif; ?>

    <form method="POST" action="verify_otp.php" id="otpForm">
      <input type="hidden" name="verify_otp" value="1">
      <div class="otp-inputs">
        <?php for($i=1;$i<=6;$i++): ?>
        <input type="text" name="d<?php echo $i; ?>" id="d<?php echo $i; ?>"
               maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
        <?php endfor; ?>
      </div>
      <div class="countdown" id="countdown">Code expires in <span id="timer">10:00</span></div>
      <button type="submit" class="btn btn-primary">Verify →</button>
    </form>

    <div style="margin-top:16px;">
      <form method="POST" action="verify_otp.php" style="display:inline;">
        <input type="hidden" name="resend_otp" value="1">
        <button type="submit" class="resend-link" id="resendBtn" disabled>Resend code</button>
      </form>
    </div>
    <div class="auth-links" style="margin-top:16px;">
      <a href="register.php">← Back to registration</a>
    </div>
  </div>
</div>
<script>
const inputs=document.querySelectorAll('.otp-inputs input');
inputs[0]?.focus();
inputs.forEach((inp,idx)=>{
  inp.addEventListener('input',e=>{
    const val=e.target.value.replace(/\D/g,'');
    e.target.value=val.slice(0,1);
    if(val&&idx<inputs.length-1)inputs[idx+1].focus();
    e.target.classList.toggle('filled',!!val);
    if([...inputs].every(i=>i.value))setTimeout(()=>document.getElementById('otpForm').submit(),200);
  });
  inp.addEventListener('keydown',e=>{
    if(e.key==='Backspace'&&!inp.value&&idx>0){inputs[idx-1].focus();inputs[idx-1].value='';inputs[idx-1].classList.remove('filled');}
  });
  inp.addEventListener('paste',e=>{
    e.preventDefault();
    const p=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
    p.split('').slice(0,6).forEach((ch,i)=>{if(inputs[i]){inputs[i].value=ch;inputs[i].classList.add('filled');}});
    inputs[Math.min(p.length,5)]?.focus();
  });
});
let seconds=600;
const timerEl=document.getElementById('timer');
const resendBtn=document.getElementById('resendBtn');
const tick=setInterval(()=>{
  seconds--;
  if(seconds<=0){clearInterval(tick);timerEl.parentElement.textContent='Code expired. Please resend.';resendBtn.disabled=false;return;}
  const m=String(Math.floor(seconds/60)).padStart(2,'0');
  const s=String(seconds%60).padStart(2,'0');
  timerEl.textContent=`${m}:${s}`;
  if(seconds<=570)resendBtn.disabled=false;
},1000);
</script>
</body>
</html>