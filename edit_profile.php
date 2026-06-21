<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$error = "";
$success = "";
//Pull profile blocks
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id LIMIT 1"));
$student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM student_profiles WHERE user_id = $user_id LIMIT 1"));
$graduate = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM graduate_profiles WHERE user_id = $user_id LIMIT 1"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $photo = $user['profile_photo'];
    //File upload block
    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($_FILES['profile_photo']['type'], $allowed) && $_FILES['profile_photo']['size'] <= 5 * 1024 * 1024) {
            $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $name = 'profile_' . $user_id . '_' . time() . '.' . $ext;
            $dir = __DIR__ . '/uploads/profiles/';
            
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir . $name)) {
                $photo = 'uploads/profiles/' . $name;
                // Delete old 
                if ($user['profile_photo'] && $user['profile_photo'] !== 'default.png' && file_exists(__DIR__ . '/' . $user['profile_photo'])) {
                    @unlink(__DIR__ . '/' . $user['profile_photo']);
                }
            }
        } else {
            $error = "Photo must be JPG, PNG, GIF, or WebP under 5MB.";
        }
    }

    if (!$error) {
        $upd = mysqli_prepare($conn, "UPDATE users SET profile_photo = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd, "si", $photo, $user_id);
        mysqli_stmt_execute($upd);
        $_SESSION['profile_photo'] = $photo;

        if ($user_type === 'graduate' && $graduate) {
            $job_status = $_POST['job_status'] ?? $graduate['job_status'];
            $company = trim($_POST['company'] ?? '');
            $job_title = trim($_POST['job_title'] ?? '');
            $salary_range = $_POST['salary_range'] ?? '';
            $education_level = $_POST['education_level'] ?? $graduate['education_level'];
            $field_of_study = trim($_POST['field_of_study'] ?? '');
            $graduation_year = $_POST['graduation_year'] ?? null;
            $linkedin_url = trim($_POST['linkedin_url'] ?? '');
            $bio = trim($_POST['bio'] ?? '');
            $skills = trim($_POST['skills'] ?? '');
            $gy = !empty($graduation_year) ? $graduation_year : null;

            $upd2 = mysqli_prepare($conn, "UPDATE graduate_profiles SET job_status=?, company=?, job_title=?, salary_range=?, education_level=?, field_of_study=?, graduation_year=?, linkedin_url=?, bio=?, skills=? WHERE user_id=?");
            mysqli_stmt_bind_param($upd2, "ssssssssssi", $job_status, $company, $job_title, $salary_range, $education_level, $field_of_study, $gy, $linkedin_url, $bio, $skills, $user_id);
            mysqli_stmt_execute($upd2);
        }

        $success = "Profile updated successfully!";
        // reload references
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id LIMIT 1"));
        $graduate = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM graduate_profiles WHERE user_id = $user_id LIMIT 1"));
    }
}

function renderAvatarHelper($photo, $name, $size = 80){
    $initial = strtoupper(substr($name, 0, 1));
    $fontSize = round($size * 0.45);
    if ($photo && $photo !== 'default.png' && file_exists(__DIR__ . '/' . $photo)) {
        return "<img src='$photo' style='width:{$size}px; height:{$size}px; border-radius:50%; object-fit:cover; display:block;'>";
    }
    return "<span style='font-size:{$fontSize}px; font-weight:700; color:#fff;'>$initial</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profile ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .edit-page { max-width: 620px; margin: 0 auto; padding: 0 20px 60px; }
    .edit-header { padding: 32px 0 24px; }
    .edit-header h1 { font-family: var(--font-display); font-size: 24px; font-weight: 800; }
    .edit-card {
      background: var(--bg-card); backdrop-filter: blur(16px);
      border: 1px solid var(--card-border); border-radius: 16px;
      padding: 24px; margin-bottom: 16px;
    }
    .edit-card h3 {
      font-family: var(--font-display); font-size: 15px; font-weight: 700;
      margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--card-border);
      display: flex; align-items: center; gap: 8px;
    }
    .photo-editor { display: flex; align-items: center; gap: 20px; }
    .photo-editor-avatar {
      width: 80px; height: 80px; border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      display: flex; align-items: center; justify-content: center;
      overflow: hidden; flex-shrink: 0; position: relative;
    }
    .photo-editor-avatar:hover .photo-overlay { opacity: 1; }
    .photo-overlay {
      position: absolute; inset: 0; background: rgba(0,0,0,.5);
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; opacity: 0; transition: opacity .2s;
      cursor: pointer; border-radius: 50%;
    }
    .photo-editor-info { flex: 1; }
    .photo-editor-info p { font-size: 12px; color: var(--text-muted); margin-top: 6px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media(max-width:500px){ .form-row { grid-template-columns: 1fr; } }
    .save-bar {
      position: sticky; bottom: 20px; z-index: 50;
      background: rgba(13,13,26,.9); backdrop-filter: blur(16px);
      border: 1px solid var(--card-border); border-radius: 12px;
      padding: 14px 20px; display: flex; gap: 10px; align-items: center;
    }
    .save-bar p { font-size: 13px; color: var(--text-muted); flex: 1; }
  </style>
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div>

<header class="navbar">
  <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-right" style="margin-left:auto;">
    <a href="profile.php?id=<?php echo $user_id; ?>" style="font-size:13px; color:var(--text-muted); text-decoration:none;">← My Profile</a>
  </div>
</header>

<div class="page-wrapper">
  <div class="edit-page">
    <div class="edit-header">
      <h1>✏️ Edit Profile</h1>
    </div>

    <?php if ($error): ?><div class="error-msg"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success-msg"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="editForm">
      <div class="edit-card">
        <h3>Profile Photo</h3>
        <div class="photo-editor">
          <label for="photoInput" style="cursor:pointer;">
            <div class="photo-editor-avatar">
              <?php echo renderAvatarHelper($user['profile_photo'], $user['username'], 80); ?>
              <div class="photo-overlay">📷</div>
            </div>
          </label>
          <input type="file" name="profile_photo" id="photoInput" accept="image/*" style="display:none;" onchange="previewPhoto(this)">
          <div class="photo-editor-info">
            <strong style="font-size:14px;"><?php echo htmlspecialchars($user['username']); ?></strong>
            <p>Click the circle to upload a new avatar image.<br>Accepts JPG, PNG, GIF, WebP (Max 5MB).</p>
            <label for="photoInput" style="color:var(--accent); font-size:13px; cursor:pointer; text-decoration:underline;">Change photo</label>
          </div>
        </div>
      </div>

      <?php if ($user_type === 'student' && $student): ?>
      <div class="edit-card">
        <h3>Student Registration</h3>
        <div class="form-group">
          <label>Matric Number</label>
          <input type="text" value="<?php echo htmlspecialchars($student['matric_number']); ?>" disabled style="opacity:.5; cursor:not-allowed;">
        </div>
        <p style="font-size:12px; color:var(--text-muted);">Matric number cannot be changed manually. Open a support ticket if it is incorrect.</p>
      </div>

      <?php elseif ($user_type === 'graduate' && $graduate): ?>
      <div class="edit-card">
        <h3>Career Info</h3>
        <div class="form-group">
          <label>Current Status</label>
          <select name="job_status" id="jobStatusSel" onchange="toggleCompanyFields()">
            <option value="employed" <?php if($graduate['job_status'] === 'employed') echo 'selected'; ?>>Employed</option>
            <option value="self-employed" <?php if($graduate['job_status'] === 'self-employed') echo 'selected'; ?>>Self-Employed</option>
            <option value="freelance" <?php if($graduate['job_status'] === 'freelance') echo 'selected'; ?>>Freelance</option>
            <option value="further_study" <?php if($graduate['job_status'] === 'further_study') echo 'selected'; ?>>Further Study</option>
            <option value="unemployed" <?php if($graduate['job_status'] === 'unemployed') echo 'selected'; ?>>Unemployed / Seeking</option>
          </select>
        </div>
        
        <div id="companyFields">
          <div class="form-row">
            <div class="form-group">
              <label>Company Name</label>
              <input type="text" name="company" placeholder="e.g. Google Malaysia" value="<?php echo htmlspecialchars($graduate['company'] ?? ''); ?>">
            </div>
            <div class="form-group">
              <label>Job Title</label>
              <input type="text" name="job_title" placeholder="e.g. Software Engineer" value="<?php echo htmlspecialchars($graduate['job_title'] ?? ''); ?>">
            </div>
          </div>
          <div class="form-group">
            <label>Salary Range <span class="optional-badge">optional</span></label>
            <select name="salary_range">
              <option value="" <?php if(empty($graduate['salary_range'])) echo 'selected'; ?>>Prefer not to say</option>
              <option value="Below RM2,000" <?php if(($graduate['salary_range']??'') === 'Below RM2,000') echo 'selected'; ?>>Below RM 2,000</option>
              <option value="RM2,000-RM4,000" <?php if(($graduate['salary_range']??'') === 'RM2,000-RM4,000') echo 'selected'; ?>>RM 2,000 – RM 4,000</option>
              <option value="RM4,000-RM7,000" <?php if(($graduate['salary_range']??'') === 'RM4,000-RM7,000') echo 'selected'; ?>>RM 4,000 – RM 7,000</option>
              <option value="RM7,000-RM12,000" <?php if(($graduate['salary_range']??'') === 'RM7,000-RM12,000') echo 'selected'; ?>>RM 7,000 – RM 12,000</option>
              <option value="Above RM12,000" <?php if(($graduate['salary_range']??'') === 'Above RM12,000') echo 'selected'; ?>>Above RM 12,000</option>
            </select>
          </div>
        </div>
      </div>

      <div class="edit-card">
        <h3>Academic History</h3>
        <div class="form-row">
          <div class="form-group">
            <label>Highest Qualification</label>
            <select name="education_level">
              <option value="diploma" <?php if(($graduate['education_level']??'') === 'diploma') echo 'selected'; ?>>Diploma</option>
              <option value="bachelor" <?php if(($graduate['education_level']??'') === 'bachelor') echo 'selected'; ?>>Bachelor's Degree</option>
              <option value="master" <?php if(($graduate['education_level']??'') === 'master') echo 'selected'; ?>>Master's Degree</option>
              <option value="phd" <?php if(($graduate['education_level']??'') === 'phd') echo 'selected'; ?>>PhD</option>
              <option value="other" <?php if(($graduate['education_level']??'') === 'other') echo 'selected'; ?>>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Graduation Year</label>
            <input type="number" name="graduation_year" min="1980" max="2030" placeholder="e.g. 2022" value="<?php echo htmlspecialchars($graduate['graduation_year'] ?? ''); ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Field of Study</label>
          <input type="text" name="field_of_study" placeholder="e.g. Computer Science" value="<?php echo htmlspecialchars($graduate['field_of_study'] ?? ''); ?>">
        </div>
      </div>

      <div class="edit-card">
        <h3>Bio & Socials</h3>
        <div class="form-group">
          <label>Skills <span class="optional-badge">separate with commas</span></label>
          <input type="text" name="skills" placeholder="e.g. Python, UI/UX, Data Analysis" value="<?php echo htmlspecialchars($graduate['skills'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label>LinkedIn Profile Link</label>
          <input type="url" name="linkedin_url" placeholder="https://linkedin.com/in/username" value="<?php echo htmlspecialchars($graduate['linkedin_url'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label>Short Bio</label>
          <textarea name="bio" rows="4" placeholder="Tell the community a bit about yourself..."><?php echo htmlspecialchars($graduate['bio'] ?? ''); ?></textarea>
        </div>
      </div>
      <?php endif; ?>

      <div class="save-bar">
        <p>Ensure your information is accurate.</p>
        <a href="profile.php?id=<?php echo $user_id; ?>" class="btn btn-secondary" style="width:auto; padding:10px 20px; margin:0; text-decoration:none; display:inline-block;">Cancel</a>
        <button type="submit" class="btn btn-primary" style="width:auto; padding:10px 24px;">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function previewPhoto(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const wrapper = document.querySelector('.photo-editor-avatar');
    wrapper.innerHTML = `<img src="${e.target.result}" style="width:80px; height:80px; border-radius:50%; object-fit:cover; display:block;">
      <div class="photo-overlay">📷</div>`;
  };
  reader.readAsDataURL(input.files[0]);
}

function toggleCompanyFields() {
  const selectBox = document.getElementById('jobStatusSel');
  if(!selectBox) return;
  
  const currentVal = selectBox.value;
  const requiresCompany = ['employed', 'self-employed', 'freelance'].includes(currentVal);
  const targetFields = document.getElementById('companyFields');
  
  if (targetFields) {
    targetFields.style.display = requiresCompany ? 'block' : 'none';
  }
}
document.addEventListener('DOMContentLoaded', toggleCompanyFields);
</script>
</body>
</html>