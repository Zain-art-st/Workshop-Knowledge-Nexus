<?php
session_start();
include "db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$step  = 1;

// ── Helper: validate password strength ───────────────────────────────────────
function validatePassword(string $pw): string {
    if (strlen($pw) < 8)                         return "Password must be at least 8 characters.";
    if (!preg_match('/[A-Z]/', $pw))             return "Password must contain at least one uppercase letter.";
    if (!preg_match('/[a-z]/', $pw))             return "Password must contain at least one lowercase letter.";
    if (!preg_match('/[0-9]/', $pw))             return "Password must contain at least one number.";
    if (!preg_match('/[\W_]/', $pw))             return "Password must contain at least one special character (e.g. @, #, !).";
    return "";
}

// ── Helper: parse matric and determine type ───────────────────────────────────
// Format: D03YYNNN  (D03 + 2-digit year + digits)
// Year >= 23 → student,  Year <= 22 → graduate
function parseMatric(string $matric): array {
    $matric = strtoupper(trim($matric));
    if (!preg_match('/^D03(\d{2})\d+$/', $matric, $m)) {
        return ['valid' => false, 'msg' => 'Matric number must start with "D03" followed by digits (e.g. D03231234).'];
    }
    $year = (int)$m[1];
    $type = ($year >= 23) ? 'student' : 'graduate';
    return ['valid' => true, 'year' => $year, 'type' => $type, 'matric' => $matric];
}

// ── Helper: handle profile photo upload ──────────────────────────────────────
function handlePhotoUpload(array $file, string $username): string {
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) {
        return 'default.png';
    }
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'], $allowed)) return 'default.png';
    if ($file['size'] > 5 * 1024 * 1024)   return 'default.png'; // 5 MB max

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . preg_replace('/[^a-z0-9]/i','_',$username) . '_' . time() . '.' . $ext;
    $dest     = __DIR__ . '/uploads/profiles/' . $filename;

    if (!is_dir(__DIR__ . '/uploads/profiles')) {
        mkdir(__DIR__ . '/uploads/profiles', 0755, true);
    }
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return 'uploads/profiles/' . $filename;
    }
    return 'default.png';
}


//  Validate basic info

if (isset($_POST['next_step1'])) {
    $username  = trim($_POST['username']);
    $email     = trim($_POST['email']);
    $pass      = $_POST['password'];
    $pass2     = $_POST['password2'];

    if (empty($username) || empty($email) || empty($pass)) {
        $error = "Please fill in all required fields.";
        $step  = 1;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
        $step  = 1;
    } elseif ($pass !== $pass2) {
        $error = "Passwords do not match.";
        $step  = 1;
    } else {
        $pwError = validatePassword($pass);
        if ($pwError) {
            $error = $pwError;
            $step  = 1;
        } else {
            // Check uniqueness
            $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE email=? OR username=?");
            mysqli_stmt_bind_param($chk, "ss", $email, $username);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            $chk2 = mysqli_prepare($conn, "SELECT id FROM pending_registrations WHERE email=? OR username=?");
            mysqli_stmt_bind_param($chk2, "ss", $email, $username);
            mysqli_stmt_execute($chk2);
            mysqli_stmt_store_result($chk2);

            if (mysqli_stmt_num_rows($chk) > 0 || mysqli_stmt_num_rows($chk2) > 0) {
                $error = "That email or username is already taken.";
                $step  = 1;
            } else {
                $_SESSION['reg_username'] = $username;
                $_SESSION['reg_email']    = $email;
                $_SESSION['reg_pass']     = $pass;
                $step = 2;
            }
        }
    }
}

// ════════════════════════════════════════════════════════════════════════════
//  STEP 2 → Send OTP : Profile details
// ════════════════════════════════════════════════════════════════════════════
if (isset($_POST['submit_profile'])) {
    $username  = $_SESSION['reg_username'] ?? '';
    $email     = $_SESSION['reg_email']    ?? '';
    $pass      = $_SESSION['reg_pass']     ?? '';

    if (empty($username) || empty($email)) {
        $error = "Session expired. Please start again.";
        $step  = 1;
    } else {
        $matric_raw = strtoupper(trim($_POST['matric_number'] ?? ''));
        $parsed     = parseMatric($matric_raw);

        if (!$parsed['valid']) {
            $error = $parsed['msg'];
            $step  = 2;
        } else {
            $auto_type  = $parsed['type'];
            $extra_data = [];

            if ($auto_type === 'student') {
                $extra_data = ['matric_number' => $parsed['matric']];
            } else {
                // graduate — collect all fields
                $extra_data = [
                    'matric_number'   => $parsed['matric'],
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

            // Handle profile photo
            $photo = 'default.png';
            if (!empty($_FILES['profile_photo']['name'])) {
                $photo = handlePhotoUpload($_FILES['profile_photo'], $username);
            }

            // Hash password
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

            // Save to pending_registrations
            $delPending = mysqli_prepare($conn, "DELETE FROM pending_registrations WHERE email=?");
            mysqli_stmt_bind_param($delPending, "s", $email);
            mysqli_stmt_execute($delPending);

            $ins = mysqli_prepare($conn,
                "INSERT INTO pending_registrations (username, email, password_hash, user_type, profile_photo, extra_data)
                 VALUES (?,?,?,?,?,?)"
            );
            $extra_json = json_encode($extra_data);
            mysqli_stmt_bind_param($ins, "ssssss", $username, $email, $hash, $auto_type, $photo, $extra_json);

            if (mysqli_stmt_execute($ins)) {
                // Generate & store OTP
                $otp        = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires_at = date('Y-m-d H:i:s', time() + 600);
mysqli_query($conn, "SET time_zone = '+00:00'");

                $delOtp = mysqli_prepare($conn, "DELETE FROM otp_codes WHERE email=?");
                mysqli_stmt_bind_param($delOtp, "s", $email);
                mysqli_stmt_execute($delOtp);

                $insOtp = mysqli_prepare($conn,
                    "INSERT INTO otp_codes (email, otp, expires_at) VALUES (?,?,?)"
                );
                mysqli_stmt_bind_param($insOtp, "sss", $email, $otp, $expires_at);
                mysqli_stmt_execute($insOtp);

                // Send email
                include_once "mailer.php";
                $sent = sendOTPEmail($email, $username, $otp);

                $_SESSION['otp_email'] = $email;
                $_SESSION['reg_auto_type'] = $auto_type;

                header("Location: verify_otp.php" . ($sent ? "" : "?mail_error=1"));
                exit();
            } else {
                $error = "Registration failed. Please try again.";
                $step  = 2;
            }
        }
    }
}

// Detect step
if (isset($_POST['next_step1']) && $step === 2) { /* already set */ }
elseif (isset($_POST['submit_profile'])) { /* already handled */ }
elseif (isset($_SESSION['reg_username']) && !isset($_POST['next_step1'])) {
    $step = 2;
}

$saved_username = $_SESSION['reg_username'] ?? '';
$saved_email    = $_SESSION['reg_email']    ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .pw-strength { height:4px; border-radius:2px; margin-top:6px; transition:all 0.3s; background:#333; }
    .pw-strength-bar { height:100%; border-radius:2px; transition:width 0.3s, background 0.3s; width:0; }
    .pw-hint { font-size:11px; color:var(--text-muted); margin-top:4px; }
    .photo-preview-wrap { display:flex; flex-direction:column; align-items:center; gap:10px; margin-bottom:20px; }
    .photo-preview { width:90px; height:90px; border-radius:50%; object-fit:cover;
                     border:2px solid var(--card-border); background:rgba(255,255,255,0.1);
                     display:flex; align-items:center; justify-content:center; font-size:36px; overflow:hidden; }
    .photo-preview img { width:100%; height:100%; object-fit:cover; }
    .photo-upload-btn { font-size:12px; color:var(--accent); cursor:pointer; text-decoration:underline; }
    .matric-hint { font-size:11px; margin-top:5px; min-height:16px; }
    .matric-hint.ok   { color: var(--success); }
    .matric-hint.warn { color: var(--warning); }
    .matric-hint.err  { color: var(--danger);  }
    .password-wrapper { position:relative; }
    .password-wrapper input { padding-right:44px; }
    .toggle-pw { position:absolute; right:10px; top:50%; transform:translateY(-50%);
                 background:none; border:none; cursor:pointer; font-size:16px; color:var(--text-muted); }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    @media(max-width:480px){ .form-row { grid-template-columns:1fr; } }
  </style>
</head>
<body>
  <div class="stars-bg"></div>
  <div class="sunset-bg"></div>

  <div class="auth-page" style="padding: 30px 20px;">
    <div class="auth-brand">ScholarSpace</div>

    <div class="auth-card" style="max-width:500px;">

      <!-- Step dots -->
      <div class="step-indicator">
        <div class="step-dot <?php echo $step >= 1 ? ($step > 1 ? 'done' : 'active') : ''; ?>"></div>
        <div class="step-dot <?php echo $step >= 2 ? 'active' : ''; ?>"></div>
      </div>

      <h2 class="auth-card-title">
        <?php echo $step === 1 ? 'Create Account' : 'Complete Your Profile'; ?>
      </h2>

      <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <!-- ════ STEP 1 ════ -->
      <?php if ($step === 1): ?>
      <form method="POST" action="register.php">
        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" placeholder="e.g. ali_azri" required
                 value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                 pattern="[A-Za-z0-9_]+" title="Letters, numbers and underscores only">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" placeholder="your@email.com" required
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
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
            <input type="password" name="password2" id="regPassword2"
                   placeholder="Repeat password" required>
            <button type="button" class="toggle-pw" onclick="togglePw('regPassword2',this)">👁</button>
          </div>
        </div>
        <button type="submit" name="next_step1" class="btn btn-primary">Continue →</button>
      </form>

      <!-- ════ STEP 2 ════ -->
      <?php elseif ($step === 2): ?>
      <form method="POST" action="register.php" enctype="multipart/form-data">

        <!-- Profile photo -->
        <div class="photo-preview-wrap">
          <div class="photo-preview" id="photoPreview">😊</div>
          <label class="photo-upload-btn" for="profilePhotoInput">Upload profile picture</label>
          <input type="file" name="profile_photo" id="profilePhotoInput"
                 accept="image/*" style="display:none" onchange="previewPhoto(this)">
        </div>

        <!-- Matric number with live validation -->
        <div class="form-group">
          <label>Matric Number</label>
          <input type="text" name="matric_number" id="matricInput"
                 placeholder="e.g. D032312345" required
                 oninput="validateMatric(this.value)"
                 value="<?php echo htmlspecialchars($_POST['matric_number'] ?? ''); ?>">
          <div class="matric-hint" id="matricHint"></div>
        </div>

        <!-- Graduate fields — shown dynamically or when PHP detects graduate -->
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
                <label>Company / Organisation</label>
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
            <label>Skills <span class="optional-badge">optional — separate by comma</span></label>
            <input type="text" name="skills" placeholder="e.g. Python, UI/UX, Data Analysis">
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

        <button type="submit" name="submit_profile" class="btn btn-primary" style="margin-top:8px;">
          Send Verification Code →
        </button>
        <a href="register.php?reset=1" onclick="clearSession()" class="btn btn-secondary"
           style="text-decoration:none;text-align:center;display:block;margin-top:10px;">← Back</a>
      </form>
      <?php endif; ?>

      <div class="auth-links">
        Already have an account? <a href="login.php">Login</a>
      </div>
    </div>
  </div>

<script>
// ── Clear session step on back ──────────────────────────────────────────────
function clearSession() {
  // Just navigate back — PHP will handle it
}
<?php if (isset($_GET['reset'])): ?>
<?php
  unset($_SESSION['reg_username'], $_SESSION['reg_email'], $_SESSION['reg_pass']);
  header("Location: register.php");
  exit();
?>
<?php endif; ?>

// ── Password strength ───────────────────────────────────────────────────────
function checkStrength(pw) {
  const bar  = document.getElementById('pwBar');
  const hint = document.getElementById('pwHint');
  let score  = 0;
  let tips   = [];

  if (pw.length >= 8)           score++; else tips.push('8+ chars');
  if (/[A-Z]/.test(pw))         score++; else tips.push('uppercase');
  if (/[a-z]/.test(pw))         score++; else tips.push('lowercase');
  if (/[0-9]/.test(pw))         score++; else tips.push('number');
  if (/[\W_]/.test(pw))         score++; else tips.push('symbol');

  const colors = ['#ff4f6a','#ff4f6a','#f59e0b','#4f8ef7','#3ecf8e'];
  const labels = ['Too weak','Weak','Fair','Good','Strong'];
  bar.style.width   = (score * 20) + '%';
  bar.style.background = colors[score - 1] || '#333';
  hint.textContent  = score === 5 ? '✅ ' + labels[score-1]
    : labels[score-1] + ' — needs: ' + tips.join(', ');
  hint.style.color  = colors[score - 1] || 'var(--text-muted)';
}

// ── Toggle password visibility ──────────────────────────────────────────────
function togglePw(id, btn) {
  const el = document.getElementById(id);
  el.type  = el.type === 'password' ? 'text' : 'password';
  btn.textContent = el.type === 'password' ? '👁' : '🙈';
}

// ── Profile photo preview ───────────────────────────────────────────────────
function previewPhoto(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const wrap = document.getElementById('photoPreview');
    wrap.innerHTML = `<img src="${e.target.result}" alt="preview">`;
  };
  reader.readAsDataURL(input.files[0]);
}

// ── Matric validation ───────────────────────────────────────────────────────
function validateMatric(val) {
  const hint   = document.getElementById('matricHint');
  const gradEl = document.getElementById('graduateFields');
  val = val.toUpperCase();

  if (!val) {
    hint.textContent = '';
    hint.className   = 'matric-hint';
    gradEl.style.display = 'none';
    return;
  }

  if (!val.startsWith('D03')) {
    hint.textContent = '❌ Must start with "D03"';
    hint.className   = 'matric-hint err';
    gradEl.style.display = 'none';
    return;
  }

  const rest = val.slice(3);
  if (rest.length < 2 || !/^\d/.test(rest)) {
    hint.textContent = 'Keep typing… e.g. D032312345';
    hint.className   = 'matric-hint warn';
    gradEl.style.display = 'none';
    return;
  }

  const year = parseInt(rest.substring(0, 2), 10);
  if (year >= 23) {
    hint.textContent = `✅ Year ${year} — registered as Student`;
    hint.className   = 'matric-hint ok';
    gradEl.style.display = 'none';
  } else {
    hint.textContent = `⚠️ Year ${year} — registered as Graduate (additional info required)`;
    hint.className   = 'matric-hint warn';
    gradEl.style.display = 'block';
  }
}

// ── Toggle company fields ───────────────────────────────────────────────────
function toggleCompany() {
  const sel  = document.getElementById('jobStatusSel');
  const show = ['employed','self-employed','freelance'].includes(sel?.value);
  const el   = document.getElementById('companyFields');
  if (el) el.style.display = show ? 'block' : 'none';
}

// Init on page load
document.addEventListener('DOMContentLoaded', () => {
  toggleCompany();
  const matric = document.getElementById('matricInput');
  if (matric && matric.value) validateMatric(matric.value);
});
</script>
</body>
</html>
