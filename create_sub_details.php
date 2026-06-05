<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}
if (!isset($_SESSION['new_sub_topic'])) { 
    header("Location: create_sub.php"); 
    exit(); 
}

$user_id = $_SESSION['user_id'];
$topic = $_SESSION['new_sub_topic'];
$error = "";

function slugify($str) {
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
    $str = preg_replace('/[\s-]+/', '-', $str);
    return trim($str, '-');
}

function uploadSubImage($file, $prefix) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) return null;
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null;
    
    $dir = __DIR__ . '/uploads/subs/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = $prefix . '_' . time() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return 'uploads/subs/' . $name;
    }
    return null;
}

// Processing incoming generation submission data entries
if (isset($_POST['create_sub'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $rules = trim($_POST['rules'] ?? '');

    if (empty($name)) { 
        $error = "Community name is required."; 
    } elseif (strlen($name) < 3) { 
        $error = "Name must be at least 3 characters."; 
    } elseif (strlen($name) > 50) { 
        $error = "Name must be under 50 characters."; 
    } else {
        $slug = slugify($name);
        if (empty($slug)) { 
            $error = "Invalid name formatting. Use letters and numbers."; 
        } else {
            $chk = mysqli_prepare($conn, "SELECT id FROM subcommunities WHERE name = ? OR slug = ?");
            mysqli_stmt_bind_param($chk, "ss", $name, $slug);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            
            if (mysqli_stmt_num_rows($chk) > 0) {
                $error = "A community with that name already exists.";
            } else {
                $profile_photo = uploadSubImage($_FILES['profile_photo'] ?? null, 'sub_icon_' . $slug) ?? 'default_sub.png';
                $banner_image = uploadSubImage($_FILES['banner_image'] ?? null, 'sub_banner_' . $slug);

                $ins = mysqli_prepare($conn, "INSERT INTO subcommunities (name, slug, description, topic, profile_photo, banner_image, rules, creator_id, member_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                mysqli_stmt_bind_param($ins, "sssssssi", $name, $slug, $description, $topic, $profile_photo, $banner_image, $rules, $user_id);

                if (mysqli_stmt_execute($ins)) {
                    $sub_id = mysqli_insert_id($conn);

                    // Assign initial user moderator roles status
                    $mem = mysqli_prepare($conn, "INSERT INTO sub_memberships (user_id, sub_id, role) VALUES (?, ?, 'moderator')");
                    mysqli_stmt_bind_param($mem, "ii", $user_id, $sub_id);
                    mysqli_stmt_execute($mem);

                    unset($_SESSION['new_sub_topic']);
                    header("Location: sub.php?id=$sub_id&created=1");
                    exit();
                } else {
                    $error = "Failed to create community. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Community – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .create-page { max-width: 600px; margin: 0 auto; padding: 0 20px 60px; }
    .create-hero { padding: 36px 0 24px; text-align: center; }
    .create-hero h1 { font-family: var(--font-display); font-size: 24px; font-weight: 800; margin-bottom: 8px; }

    .topic-chip {
      display: inline-block; padding: 4px 14px; border-radius: 20px;
      background: rgba(79,142,247,.15); border: 1px solid rgba(79,142,247,.3);
      color: var(--accent); font-size: 12px; font-weight: 600; margin-bottom: 20px;
    }
    .step-bar { display: flex; align-items: center; justify-content: center; margin-bottom: 28px; gap: 0; }
    .step-node { display: flex; align-items: center; gap: 8px; }
    .step-circle { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; }
    .step-circle.done { background: var(--success); color: #fff; }
    .step-circle.active { background: var(--accent); color: #fff; }
    .step-label { font-size: 12px; font-weight: 600; }
    .step-label.done { color: var(--success); }
    .step-label.active { color: var(--accent); }
    .step-line { width: 48px; height: 2px; background: var(--accent); margin: 0 4px; }

    .upload-zone {
      border: 2px dashed var(--card-border); border-radius: 12px; padding: 22px;
      text-align: center; cursor: pointer; transition: border-color .2s;
      position: relative; overflow: hidden;
    }
    .upload-zone:hover { border-color: var(--accent); }
    .upload-zone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; z-index: 1; }
    .upload-zone-icon { font-size: 28px; margin-bottom: 6px; }
    .upload-zone-label { font-size: 13px; color: var(--text-muted); }
    .upload-zone-hint { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
    .upload-preview-banner { width: 100%; max-height: 120px; object-fit: cover; border-radius: 8px; display: none; }
    .upload-preview-icon { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; display: none; margin: 0 auto; }
    .char-count { font-size: 11px; color: var(--text-muted); text-align: right; margin-top: 4px; }
  </style>
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div>

<header class="navbar">
  <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-right" style="margin-left:auto;">
    <a href="create_sub.php" style="font-size:13px; color:var(--text-muted); text-decoration:none; margin-right:12px;">← Back</a>
    <a href="dashboard.php" style="font-size:13px; color:var(--text-muted); text-decoration:none;">✕ Cancel</a>
  </div>
</header>

<div class="page-wrapper">
  <div class="create-page">

    <div class="create-hero">
      <h1>Set up your community</h1>
      <div class="topic-chip">📌 <?php echo htmlspecialchars($topic); ?></div>
    </div>

    <div class="step-bar">
      <div class="step-node">
        <div class="step-circle done">✓</div>
        <span class="step-label done">Topic</span>
      </div>
      <div class="step-line"></div>
      <div class="step-node">
        <div class="step-circle active">2</div>
        <span class="step-label active">Details</span>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="create_sub_details.php" enctype="multipart/form-data">
      <div class="form-group">
        <label>Community Icon</label>
        <div class="upload-zone" id="iconZone">
          <input type="file" name="profile_photo" id="iconInput" accept="image/*" onchange="previewIcon(this)">
          <img id="iconPreview" class="upload-preview-icon" src="" alt="">
          <div id="iconPlaceholder">
            <div class="upload-zone-icon">🖼️</div>
            <div class="upload-zone-label">Click to upload icon</div>
            <div class="upload-zone-hint">PNG, JPG — max 5MB</div>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>Banner Image <span class="optional-badge">optional</span></label>
        <div class="upload-zone" id="bannerZone">
          <input type="file" name="banner_image" id="bannerInput" accept="image/*" onchange="previewBanner(this)">
          <img id="bannerPreview" class="upload-preview-banner" src="" alt="">
          <div id="bannerPlaceholder">
            <div class="upload-zone-icon">🏔️</div>
            <div class="upload-zone-label">Click to upload banner</div>
            <div class="upload-zone-hint">Recommended 1200×300 — max 5MB</div>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>Community Name</label>
        <input type="text" name="name" id="nameInput" placeholder="e.g. Programming Y1" maxlength="50" required oninput="updateCounter('nameInput','nameCount')" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        <div class="char-count"><span id="nameCount">0</span> / 50</div>
      </div>

      <div class="form-group">
        <label>Description</label>
        <textarea name="description" id="descInput" rows="3" placeholder="What is this community about?" maxlength="300" oninput="updateCounter('descInput','descCount')"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        <div class="char-count"><span id="descCount">0</span> / 300</div>
      </div>

      <div class="form-group">
        <label>Community Rules <span class="optional-badge">optional</span></label>
        <textarea name="rules" rows="5" placeholder="1. Be respectful&#10;2. No spam&#10;3. Stay on topic"><?php echo htmlspecialchars($_POST['rules'] ?? ''); ?></textarea>
        <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">One rule per line. These show on your sub's sidebar.</div>
      </div>

      <button type="submit" name="create_sub" class="btn btn-primary">🚀 Create Community</button>
    </form>

  </div>
</div>

<script>
function previewIcon(input) {
  if (!input.files || !input.files[0]) return;
  const r = new FileReader();
  r.onload = e => {
    document.getElementById('iconPreview').src = e.target.result;
    document.getElementById('iconPreview').style.display = 'block';
    document.getElementById('iconPlaceholder').style.display = 'none';
  };
  r.readAsDataURL(input.files[0]);
}

function previewBanner(input) {
  if (!input.files || !input.files[0]) return;
  const r = new FileReader();
  r.onload = e => {
    document.getElementById('bannerPreview').src = e.target.result;
    document.getElementById('bannerPreview').style.display = 'block';
    document.getElementById('bannerPlaceholder').style.display = 'none';
  };
  r.readAsDataURL(input.files[0]);
}

function updateCounter(inputId, countId) {
  const currentLength = document.getElementById(inputId).value.length;
  document.getElementById(countId).textContent = currentLength;
}

document.addEventListener('DOMContentLoaded', () => {
  updateCounter('nameInput','nameCount');
  updateCounter('descInput','descCount');
});
</script>
</body>
</html>