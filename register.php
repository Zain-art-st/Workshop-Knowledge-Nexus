<?php
session_start();
include "db.php";

if (isset($_SESSION['user_id'])) { header("Location: dashboard.php"); exit(); }

$error = "";
$step  = 1;

// Password validator
function validatePassword(string $pw): string {
    if (strlen($pw) < 8)           return "Password must be at least 8 characters.";
    if (!preg_match('/[A-Z]/', $pw)) return "Password must contain at least one uppercase letter.";
    if (!preg_match('/[a-z]/', $pw)) return "Password must contain at least one lowercase letter.";
    if (!preg_match('/[0-9]/', $pw)) return "Password must contain at least one number.";
    if (!preg_match('/[\W_]/', $pw)) return "Password must contain at least one special character.";
    return "";
}

// Matric parser
function parseMatric(string $matric): array {
    $matric = strtoupper(trim($matric));
    if (!preg_match('/^D03(\d{2})\d+$/', $matric, $m))
        return ['valid'=>false,'msg'=>'Matric number must start with "D03" followed by digits (e.g. D03231234).'];
    $year = (int)$m[1];
    return ['valid'=>true,'year'=>$year,'type'=>$year>=23?'student':'graduate','matric'=>$matric];
}

// Photo upload
function handlePhoto(array $file, string $prefix): string {
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) return 'default.png';
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'],$allowed)||$file['size']>5*1024*1024) return 'default.png';
    $ext=$pathinfo=$ext=pathinfo($file['name'],PATHINFO_EXTENSION);
    $name=$prefix.'_'.time().'.'.$ext;
    $dir=__DIR__.'/uploads/profiles/';
    if(!is_dir($dir))mkdir($dir,0755,true);
    if(move_uploaded_file($file['tmp_name'],$dir.$name)) return 'uploads/profiles/'.$name;
    return 'default.png';
}

// KYC card upload
function handleKycUpload(array $file, string $username): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) return null;
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'],$allowed)||$file['size']>5*1024*1024) return null;
    $ext=pathinfo($file['name'],PATHINFO_EXTENSION);
    $name='kyc_'.preg_replace('/[^a-z0-9]/i','_',$username).'_'.time().'.'.$ext;
    $dir=__DIR__.'/uploads/kyc/';
    if(!is_dir($dir))mkdir($dir,0755,true);
    if(move_uploaded_file($file['tmp_name'],$dir.$name)) return 'uploads/kyc/'.$name;
    return null;
}

//  validate basics
if (isset($_POST['next_step1'])) {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $pass     = $_POST['password'];
    $pass2    = $_POST['password2'];

    if (empty($username)||empty($email)||empty($pass)) { $error="Please fill in all fields."; $step=1; }
    elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) { $error="Invalid email address."; $step=1; }
    elseif ($pass !== $pass2)                          { $error="Passwords do not match."; $step=1; }
    else {
        $pwErr = validatePassword($pass);
        if ($pwErr) { $error=$pwErr; $step=1; }
        else {
            $chk = mysqli_prepare($conn,"SELECT id FROM users WHERE email=? OR username=?");
            mysqli_stmt_bind_param($chk,"ss",$email,$username);
            mysqli_stmt_execute($chk); mysqli_stmt_store_result($chk);
            $chk2 = mysqli_prepare($conn,"SELECT id FROM pending_registrations WHERE email=? OR username=?");
            mysqli_stmt_bind_param($chk2,"ss",$email,$username);
            mysqli_stmt_execute($chk2); mysqli_stmt_store_result($chk2);
            if (mysqli_stmt_num_rows($chk)>0||mysqli_stmt_num_rows($chk2)>0) {
                $error="That email or username is already taken."; $step=1;
            } else {
                $_SESSION['reg_username'] = $username;
                $_SESSION['reg_email']    = $email;
                $_SESSION['reg_pass']     = $pass;
                $step = 2;
            }
        }
    }
}

//  validate profile
if (isset($_POST['submit_profile'])) {
    $username = $_SESSION['reg_username'] ?? '';
    $email    = $_SESSION['reg_email']    ?? '';
    $pass     = $_SESSION['reg_pass']     ?? '';

    if (empty($username)||empty($email)) { $error="Session expired. Please start again."; $step=1; }
    else {
        $matric_raw = strtoupper(trim($_POST['matric_number'] ?? ''));
        $parsed     = parseMatric($matric_raw);

        if (!$parsed['valid']) { $error=$parsed['msg']; $step=2; }
        else {
            $matric    = $parsed['matric'];
            $auto_type = $parsed['type'];

            //Check student_profiles
            $mc1 = mysqli_prepare($conn,"SELECT id FROM student_profiles WHERE matric_number=?");
            mysqli_stmt_bind_param($mc1,"s",$matric);
            mysqli_stmt_execute($mc1); mysqli_stmt_store_result($mc1);

            //check pending_registrations
            $mc2 = mysqli_prepare($conn,"SELECT id FROM pending_registrations WHERE extra_data LIKE ?");
            $matric_like = '%"matric_number":"'.$matric.'"%';
            mysqli_stmt_bind_param($mc2,"s",$matric_like);
            mysqli_stmt_execute($mc2); mysqli_stmt_store_result($mc2);

            if (mysqli_stmt_num_rows($mc1)>0 || mysqli_stmt_num_rows($mc2)>0) {
                $error = "This matric number is already registered."; $step=2;
            } else {
                $extra_data = [];
                if ($auto_type === 'student') {
                    $extra_data = ['matric_number'=>$matric];
                } else {
                    $extra_data = [
                        'matric_number'   => $matric,
                        'job_status'      => $_POST['job_status']      ?? 'unemployed',
                        'company'         => trim($_POST['company']     ?? ''),
                        'job_title'       => trim($_POST['job_title']   ?? ''),
                        'salary_range'    => $_POST['salary_range']     ?? '',
                        'education_level' => $_POST['education_level']  ?? 'bachelor',
                        'field_of_study'  => trim($_POST['field_of_study'] ?? ''),
                        'graduation_year' => $_POST['graduation_year']  ?? '',
                        'linkedin_url'    => trim($_POST['linkedin_url'] ?? ''),
                        'bio'             => trim($_POST['bio']          ?? ''),
                        'skills'          => trim($_POST['skills']       ?? ''),
                    ];
                }

                $photo = 'default.png';
                if (!empty($_FILES['profile_photo']['name']))
                    $photo = handlePhoto($_FILES['profile_photo'], 'profile_'.$username);

                // Handle KYC upload
                $kyc_image = null;
                if (!empty($_FILES['kyc_card']['name']))
                    $kyc_image = handleKycUpload($_FILES['kyc_card'], $username);

                $hash       = password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12]);
                $extra_json = json_encode($extra_data);

                // Save KYC image path in extra_data too
                if ($kyc_image) $extra_data['kyc_image'] = $kyc_image;
                $extra_json = json_encode($extra_data);

                $del = mysqli_prepare($conn,"DELETE FROM pending_registrations WHERE email=?");
                mysqli_stmt_bind_param($del,"s",$email); mysqli_stmt_execute($del);

                $ins = mysqli_prepare($conn,
                    "INSERT INTO pending_registrations (username,email,password_hash,user_type,profile_photo,extra_data)
                     VALUES (?,?,?,?,?,?)");
                mysqli_stmt_bind_param($ins,"ssssss",$username,$email,$hash,$auto_type,$photo,$extra_json);

                if (mysqli_stmt_execute($ins)) {
                    // Generate OTP
                    $otp = str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
                    $del2 = mysqli_prepare($conn,"DELETE FROM otp_codes WHERE email=?");
                    mysqli_stmt_bind_param($del2,"s",$email); mysqli_stmt_execute($del2);
                    $insOtp = mysqli_prepare($conn,
                        "INSERT INTO otp_codes (email,otp,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))");
                    mysqli_stmt_bind_param($insOtp,"ss",$email,$otp); mysqli_stmt_execute($insOtp);

                    include_once "mailer.php";
                    $sent = sendOTPEmail($email, $username, $otp);

                    $_SESSION['otp_email']      = $email;
                    $_SESSION['reg_auto_type']  = $auto_type;
                    $_SESSION['reg_kyc_pending'] = ($kyc_image !== null);

                    header("Location: verify_otp.php".($sent?"":"?mail_error=1"));
                    exit();
                } else {
                    $error="Registration failed. Please try again."; $step=2;
                }
            }
        }
    }
}

// detect step on page load
if (isset($_SESSION['reg_username']) && !isset($_POST['next_step1']) && !isset($_POST['submit_profile']))
    $step = 2;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .pw-strength     { height:4px; border-radius:2px; margin-top:6px; background:#333; }
    .pw-strength-bar { height:100%; border-radius:2px; transition:width .3s,background .3s; width:0; }
    .pw-hint         { font-size:11px; color:var(--text-muted); margin-top:4px; }
    .matric-hint     { font-size:11px; margin-top:5px; min-height:16px; }
    .matric-hint.ok  { color:var(--success); }
    .matric-hint.warn{ color:var(--warning); }
    .matric-hint.err { color:var(--danger);  }
    .password-wrapper            { position:relative; }
    .password-wrapper input      { padding-right:44px; }
    .toggle-pw                   { position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--text-muted); }
    .form-row                    { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
    @media(max-width:480px){.form-row{grid-template-columns:1fr;}}
    .photo-preview-wrap          { display:flex;flex-direction:column;align-items:center;gap:10px;margin-bottom:20px; }
    .photo-preview               { width:90px;height:90px;border-radius:50%;object-fit:cover;border:2px solid var(--card-border);background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:36px;overflow:hidden; }
    .photo-preview img           { width:100%;height:100%;object-fit:cover; }

    /* eKYC */
    .kyc-box {
      background:rgba(79,142,247,.06); border:1px solid rgba(79,142,247,.2);
      border-radius:14px; padding:20px; margin-bottom:20px;
    }
    .kyc-box h4  { font-family:var(--font-display);font-size:15px;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:8px; }
    .kyc-box p   { font-size:13px;color:var(--text-muted);line-height:1.7;margin-bottom:14px; }
    .kyc-options { display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px; }
    .kyc-option  {
      padding:14px 12px; border:2px solid var(--card-border); border-radius:12px;
      background:rgba(255,255,255,.04); cursor:pointer; text-align:center;
      transition:all .2s; font-size:13px; font-weight:600; color:var(--text-muted);
    }
    .kyc-option:hover  { border-color:var(--accent); color:var(--accent); background:rgba(79,142,247,.08); }
    .kyc-option.active { border-color:var(--accent); color:var(--accent); background:rgba(79,142,247,.12); }
    .kyc-option-icon   { font-size:24px; display:block; margin-bottom:6px; }
    .kyc-upload-zone   { display:none; }
    .kyc-upload-zone.show { display:block; }
    .kyc-preview       { max-width:100%; border-radius:10px; margin-top:10px; display:none; }
    .kyc-status        { font-size:12px; margin-top:8px; }
    .kyc-status.ok     { color:var(--success); }
    .kyc-status.warn   { color:var(--warning); }

    /* camera */
    #kyc-camera-wrap   { display:none; margin-top:10px; }
    #kyc-video         { width:100%; border-radius:10px; background:#000; }
    .camera-btns       { display:flex;gap:8px;margin-top:8px; }
  </style>
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div>

<div class="auth-page" style="padding:30px 20px;">
  <div class="auth-brand">ScholarSpace</div>

  <div class="auth-card" style="max-width:500px;">
    <div class="step-indicator">
      <div class="step-dot <?php echo $step>=1?($step>1?'done':'active'):''; ?>"></div>
      <div class="step-dot <?php echo $step>=2?'active':''; ?>"></div>
    </div>

    <h2 class="auth-card-title"><?php echo $step===1?'Create Account':'Complete Your Profile'; ?></h2>

    <?php if ($error): ?>
    <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($step===1): ?>
    <form method="POST" action="register.php">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" placeholder="e.g. ali_azri" required
               value="<?php echo htmlspecialchars($_POST['username']??''); ?>"
               pattern="[A-Za-z0-9_]+" title="Letters, numbers and underscores only">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" placeholder="your@email.com" required
               value="<?php echo htmlspecialchars($_POST['email']??''); ?>">
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
          Use a personal email (Gmail, Outlook) — university emails may block OTP codes.
        </div>
      </div>
      <div class="form-group">
        <label>Password</label>
        <div class="password-wrapper">
          <input type="password" name="password" id="regPassword"
                 placeholder="Min 8 chars, A-Z, a-z, 0-9, symbol" required
                 oninput="checkStrength(this.value)">
          <button type="button" class="toggle-pw" onclick="togglePw('regPassword',this)">👁</button>
        </div>
        <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
        <div class="pw-hint" id="pwHint">Enter a password</div>
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <div class="password-wrapper">
          <input type="password" name="password2" id="regPassword2" placeholder="Repeat password" required>
          <button type="button" class="toggle-pw" onclick="togglePw('regPassword2',this)">👁</button>
        </div>
      </div>
      <button type="submit" name="next_step1" class="btn btn-primary">Continue →</button>
    </form>

    <?php elseif ($step===2): ?>
    <form method="POST" action="register.php" enctype="multipart/form-data" id="step2Form">

      <!--pfp-->
      <div class="photo-preview-wrap">
        <div class="photo-preview" id="photoPreview">😊</div>
        <label class="photo-upload-btn" for="profilePhotoInput" style="color:var(--accent);font-size:13px;cursor:pointer;text-decoration:underline;">
          Upload profile picture
        </label>
        <input type="file" name="profile_photo" id="profilePhotoInput"
               accept="image/*" style="display:none;" onchange="previewPhoto(this)">
      </div>

      <!--matric number -->
      <div class="form-group">
        <label>Matric Number</label>
        <input type="text" name="matric_number" id="matricInput"
               placeholder="e.g. D032312345" required
               oninput="validateMatric(this.value)"
               value="<?php echo htmlspecialchars($_POST['matric_number']??''); ?>">
        <div class="matric-hint" id="matricHint"></div>
      </div>

      <!--graduate fields-->
      <div id="graduateFields" style="display:none;">
        <div class="section-divider">Professional Details</div>
        <div class="form-row">
          <div class="form-group">
            <label>Education Level</label>
            <select name="education_level">
              <option value="diploma">Diploma</option>
              <option value="bachelor" selected>Bachelor's</option>
              <option value="master">Master's</option>
              <option value="phd">PhD</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Graduation Year</label>
            <input type="number" name="graduation_year" placeholder="e.g. 2022" min="1980" max="2030">
          </div>
        </div>
        <div class="form-group">
          <label>Field of Study</label>
          <input type="text" name="field_of_study" placeholder="e.g. Computer Science">
        </div>
        <div class="form-group">
          <label>Current Job Status</label>
          <select name="job_status" id="jobStatusSel" onchange="toggleCompany()">
            <option value="employed">Employed</option>
            <option value="self-employed">Self-Employed</option>
            <option value="freelance">Freelance</option>
            <option value="further_study">Further Study</option>
            <option value="unemployed">Unemployed / Seeking</option>
          </select>
        </div>
        <div id="companyFields">
          <div class="form-row">
            <div class="form-group">
              <label>Company</label>
              <input type="text" name="company" placeholder="e.g. Google Malaysia">
            </div>
            <div class="form-group">
              <label>Job Title</label>
              <input type="text" name="job_title" placeholder="e.g. Software Engineer">
            </div>
          </div>
          <div class="form-group">
            <label>Salary Range <span class="optional-badge">optional</span></label>
            <select name="salary_range">
              <option value="">Prefer not to say</option>
              <option value="Below RM2,000">Below RM 2,000</option>
              <option value="RM2,000-RM4,000">RM 2,000 – RM 4,000</option>
              <option value="RM4,000-RM7,000">RM 4,000 – RM 7,000</option>
              <option value="RM7,000-RM12,000">RM 7,000 – RM 12,000</option>
              <option value="Above RM12,000">Above RM 12,000</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Skills <span class="optional-badge">separate by comma</span></label>
          <input type="text" name="skills" placeholder="e.g. Python, UI/UX">
        </div>
        <div class="form-group">
          <label>LinkedIn URL <span class="optional-badge">optional</span></label>
          <input type="url" name="linkedin_url" placeholder="https://linkedin.com/in/yourname">
        </div>
        <div class="form-group">
          <label>Short Bio <span class="optional-badge">optional</span></label>
          <textarea name="bio" placeholder="Tell the community about yourself..."></textarea>
        </div>
      </div>

      <!--eKYC section-->
      <div class="kyc-box" id="kycBox" style="display:none;">
        <h4>🪪 Matric Card Verification (eKYC)</h4>
        <p>Upload a photo of your matric card so an admin can verify your identity. 
           Your account will have limited access until approved. You can upload from your 
           device or take a photo directly with your camera.</p>

        <div class="kyc-options">
          <div class="kyc-option" id="optUpload" onclick="selectKycMethod('upload')">
            <span class="kyc-option-icon">📁</span>
            Upload Image
          </div>
          <div class="kyc-option" id="optCamera" onclick="selectKycMethod('camera')">
            <span class="kyc-option-icon">📷</span>
            Use Camera
          </div>
        </div>

        <!--upload method-->
        <div class="kyc-upload-zone" id="kycUploadZone">
          <label for="kycFileInput" style="cursor:pointer;">
            <div style="border:2px dashed var(--card-border);border-radius:10px;padding:20px;text-align:center;transition:border-color .2s;"
                 onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--card-border)'">
              <div style="font-size:28px;margin-bottom:6px;">🪪</div>
              <div style="font-size:13px;color:var(--text-muted);">Click to select matric card image</div>
            </div>
          </label>
          <input type="file" name="kyc_card" id="kycFileInput" accept="image/*"
                 style="display:none;" onchange="previewKyc(this)">
          <img id="kycPreview" class="kyc-preview" src="" alt="Matric card preview">
          <div id="kycStatus" class="kyc-status"></div>
        </div>

        <!--Cam-->
        <div id="kyc-camera-wrap">
          <video id="kyc-video" autoplay playsinline></video>
          <canvas id="kyc-canvas" style="display:none;"></canvas>
          <div class="camera-btns">
            <button type="button" class="btn btn-primary" style="flex:1;padding:8px;"
                    onclick="capturePhoto()">📸 Capture</button>
            <button type="button" class="btn btn-secondary" style="flex:1;padding:8px;margin-top:0;"
                    onclick="stopCamera()">✕ Cancel</button>
          </div>
          <img id="kycCapturePreview" class="kyc-preview" src="" alt="Captured photo">
          <input type="hidden" name="kyc_card_base64" id="kycBase64Input">
        </div>
      </div>

      <button type="submit" name="submit_profile" class="btn btn-primary" style="margin-top:8px;">
        Send Verification Code →
      </button>
      <a href="register.php?reset=1" class="btn btn-secondary"
         style="text-decoration:none;text-align:center;display:block;margin-top:10px;">← Back</a>
    </form>
    <?php endif; ?>

    <div class="auth-links">Already have an account? <a href="login.php">Login</a></div>
  </div>
</div>

<?php if (isset($_GET['reset'])): 
  unset($_SESSION['reg_username'],$_SESSION['reg_email'],$_SESSION['reg_pass']);
  header("Location: register.php"); exit();
endif; ?>

<script>
//Password strength
function checkStrength(pw) {
  const bar=document.getElementById('pwBar'),hint=document.getElementById('pwHint');
  if(!bar)return;
  let score=0,tips=[];
  if(pw.length>=8)score++;else tips.push('8+ chars');
  if(/[A-Z]/.test(pw))score++;else tips.push('uppercase');
  if(/[a-z]/.test(pw))score++;else tips.push('lowercase');
  if(/[0-9]/.test(pw))score++;else tips.push('number');
  if(/[\W_]/.test(pw))score++;else tips.push('symbol');
  const colors=['#ff4f6a','#ff4f6a','#f59e0b','#4f8ef7','#3ecf8e'];
  const labels=['Too weak','Weak','Fair','Good','Strong'];
  bar.style.width=(score*20)+'%';
  bar.style.background=colors[score-1]||'#333';
  hint.textContent=score===5?'✅ '+labels[score-1]:labels[score-1]+' — needs: '+tips.join(', ');
  hint.style.color=colors[score-1]||'var(--text-muted)';
}

function togglePw(id,btn){
  const el=document.getElementById(id);
  el.type=el.type==='password'?'text':'password';
  btn.textContent=el.type==='password'?'👁':'🙈';
}

function previewPhoto(input){
  if(!input.files[0])return;
  const r=new FileReader();
  r.onload=e=>{
    const w=document.getElementById('photoPreview');
    w.innerHTML=`<img src="${e.target.result}" alt="preview">`;
  };
  r.readAsDataURL(input.files[0]);
}

//matric validation
function validateMatric(val) {
  const hint=document.getElementById('matricHint');
  const grad=document.getElementById('graduateFields');
  const kyc=document.getElementById('kycBox');
  val=val.toUpperCase();
  if(!val){hint.textContent='';hint.className='matric-hint';grad.style.display='none';kyc.style.display='none';return;}
  if(!val.startsWith('D03')){hint.textContent='Must start with "D03"';hint.className='matric-hint err';grad.style.display='none';kyc.style.display='none';return;}
  const rest=val.slice(3);
  if(rest.length<2||!/^\d/.test(rest)){hint.textContent='Keep typing… e.g. D032312345';hint.className='matric-hint warn';grad.style.display='none';kyc.style.display='none';return;}
  const year=parseInt(rest.substring(0,2),10);
  if(year>=23){
    hint.textContent=`Year ${year} — registered as Student`;
    hint.className='matric-hint ok';
    grad.style.display='none';
    kyc.style.display='block';
  } else {
    hint.textContent=`Year ${year} — registered as Graduate`;
    hint.className='matric-hint warn';
    grad.style.display='block';
    kyc.style.display='block';
  }
}

function toggleCompany(){
  const sel=document.getElementById('jobStatusSel');
  const show=['employed','self-employed','freelance'].includes(sel?.value);
  const el=document.getElementById('companyFields');
  if(el)el.style.display=show?'block':'none';
}

//eKYC
let kycMethod = null;
let cameraStream = null;

function selectKycMethod(method) {
  kycMethod = method;
  document.querySelectorAll('.kyc-option').forEach(o=>o.classList.remove('active'));
  document.getElementById(method==='upload'?'optUpload':'optCamera').classList.add('active');

  if(method==='upload'){
    document.getElementById('kycUploadZone').classList.add('show');
    document.getElementById('kyc-camera-wrap').style.display='none';
    stopCamera();
  } else {
    document.getElementById('kycUploadZone').classList.remove('show');
    document.getElementById('kyc-camera-wrap').style.display='block';
    startCamera();
  }
}

function startCamera(){
  navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}})
    .then(stream=>{
      cameraStream=stream;
      document.getElementById('kyc-video').srcObject=stream;
    })
    .catch(()=>alert('Camera not available. Please use Upload instead.'));
}

function stopCamera(){
  if(cameraStream){cameraStream.getTracks().forEach(t=>t.stop());cameraStream=null;}
  document.getElementById('kyc-camera-wrap').style.display='none';
}

function capturePhoto(){
  const video=document.getElementById('kyc-video');
  const canvas=document.getElementById('kyc-canvas');
  canvas.width=video.videoWidth;
  canvas.height=video.videoHeight;
  canvas.getContext('2d').drawImage(video,0,0);
  const dataUrl=canvas.toDataURL('image/jpeg',0.9);
  document.getElementById('kycBase64Input').value=dataUrl;
  document.getElementById('kycCapturePreview').src=dataUrl;
  document.getElementById('kycCapturePreview').style.display='block';
  document.getElementById('kycStatus').textContent='Photo captured!';
  document.getElementById('kycStatus').className='kyc-status ok';
  stopCamera();
}

function previewKyc(input){
  if(!input.files[0])return;
  const r=new FileReader();
  r.onload=e=>{
    document.getElementById('kycPreview').src=e.target.result;
    document.getElementById('kycPreview').style.display='block';
    document.getElementById('kycStatus').textContent='Card uploaded!';
    document.getElementById('kycStatus').className='kyc-status ok';
  };
  r.readAsDataURL(input.files[0]);
}

document.addEventListener('DOMContentLoaded',()=>{
  toggleCompany();
  const matric=document.getElementById('matricInput');
  if(matric&&matric.value)validateMatric(matric.value);
});
</script>
</body>
</html>