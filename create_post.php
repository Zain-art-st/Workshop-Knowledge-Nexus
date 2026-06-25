<?php
session_start();
include "db.php";
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$error     = "";

//Pre-selected sub from URL
$preselect_sub = (int)($_GET['sub_id'] ?? 0);

//Fetch joined subs
if ($user_type === 'admin') {
    $subs_res = mysqli_query($conn, "SELECT id, name FROM subcommunities ORDER BY name ASC");
} else {
    $subs_res = mysqli_query($conn,
        "SELECT s.id, s.name FROM subcommunities s
         JOIN sub_memberships sm ON s.id = sm.sub_id
         WHERE sm.user_id = $user_id
         ORDER BY s.name ASC");
}
$my_subs = mysqli_fetch_all($subs_res, MYSQLI_ASSOC);

define('MAX_FILE_BYTES', 10 * 1024 * 1024); // 10 MB

function handleUpload($file, $type) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) return null;
    if ($file['size'] > MAX_FILE_BYTES) return ['error' => 'File exceeds 10MB limit.'];

    $dir = __DIR__ . '/uploads/posts/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if ($type === 'image') {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($file['type'], $allowed))
            return ['error' => 'Image must be JPG, PNG, GIF or WebP.'];
    } else {
        $allowed_doc = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
        ];
        if (!in_array($file['type'], $allowed_doc))
            return ['error' => 'Document must be PDF, Word, PowerPoint, Excel or TXT.'];
    }

    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe = preg_replace('/[^a-z0-9._-]/i', '_', $file['name']);
    $name = ($type === 'image' ? 'post_img_' : 'post_doc_') . uniqid() . '_' . $safe;

    if (move_uploaded_file($file['tmp_name'], $dir . $name))
        return ['path' => 'uploads/posts/' . $name];

    return ['error' => 'Upload failed. Check folder permissions.'];
}

if (isset($_POST['submit_post'])) {
    $sub_id  = (int)($_POST['sub_id']    ?? 0);
    $title   = trim($_POST['title']      ?? '');
    $content_parts = [];

    $fields = [
      'content',
      'image_caption',
      'link_description',
      'document_description'
    ];

    foreach ($fields as $field) {
      if (!empty($_POST[$field])) {
        $content_parts[] = trim($_POST[$field]);
      }
    }

  $content = implode("\n\n", $content_parts);
    $link    = trim($_POST['link_url']   ?? '');
    $tab     = $_POST['active_tab']      ?? 'text';

    if (!$sub_id)        { $error = "Please choose a community."; }
    elseif (empty($title)) { $error = "Title is required."; }
    elseif (strlen($title) > 300) { $error = "Title must be under 300 characters."; }
    else {
        // Membership check
        if ($user_type !== 'admin') {
            $mc = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT id FROM sub_memberships WHERE user_id=$user_id AND sub_id=$sub_id LIMIT 1"));
            if (!$mc) { $error = "You must join this community before posting."; }
        }
        // Sub-ban check
        if (!$error) {
            $bc = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT id FROM sub_bans WHERE user_id=$user_id AND sub_id=$sub_id LIMIT 1"));
            if ($bc) { $error = "You are suspended from posting in this community."; }
        }

        if (!$error) {
            $image_url = null;
            $file_url  = null;

        /* Upload image regardless of tab */
        if (!empty($_FILES['image']['name'])) {
          $res = handleUpload($_FILES['image'], 'image');

          if (isset($res['error'])) {
            $error = $res['error'];
          } else {
            $image_url = $res['path'];
          }
        }

        /* Upload document regardless of tab */
        if (!$error && !empty($_FILES['document']['name'])) {
          $res = handleUpload($_FILES['document'], 'doc');

          if (isset($res['error'])) {
            $error = $res['error'];
          } else {
            $file_url = $res['path'];
          }
        }

        /* Save link if entered */
        $link = !empty(trim($_POST['link_url'] ?? ''))
          ? trim($_POST['link_url'])
          : null;

            if (!$error) {
                $ins = mysqli_prepare($conn,
                    "INSERT INTO posts (user_id, sub_id, title, content, image_url, link_url, file_url)
                     VALUES (?,?,?,?,?,?,?)");
                mysqli_stmt_bind_param($ins, "iisssss",
                    $user_id, $sub_id, $title, $content,
                    $image_url, $link, $file_url);
                if (mysqli_stmt_execute($ins)) {
                    header("Location: post.php?id=" . mysqli_insert_id($conn));
                    exit();
                } else {
                    $error = "Post failed: " . mysqli_error($conn);
                }
            }
        }
    }
}

$active_tab = $_POST['active_tab'] ?? ($_GET['tab'] ?? 'text');
if (!in_array($active_tab, ['text','image','link','doc','rich'])) $active_tab = 'text';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Post – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .post-create-page  { max-width:700px; margin:0 auto; padding:0 20px 80px; }
    .post-create-hdr   { padding:28px 0 20px; }
    .post-create-hdr h1{ font-family:var(--font-display); font-size:24px; font-weight:800; }

    /* sub selector */
    .sub-selector {
      display:flex; align-items:center; gap:12px; padding:13px 16px;
      background:var(--bg-card); border:1px solid var(--card-border);
      border-radius:12px; margin-bottom:16px; transition:border-color .2s;
    }
    .sub-selector:focus-within { border-color:var(--accent); }
    .sub-selector select {
      flex:1; background:none; border:none; outline:none;
      color:var(--text-main); font-family:var(--font-display);
      font-size:15px; font-weight:700; cursor:pointer;
    }
    .sub-selector select option { background:#1e1e35; }

    /* tabs */
    .post-type-tabs {
      display:flex; gap:0; background:rgba(255,255,255,.05);
      border-radius:12px; padding:4px; margin-bottom:20px;
    }
    .post-type-tab {
      flex:1; padding:9px; border:none; background:none;
      color:var(--text-muted); border-radius:9px; cursor:pointer;
      font-size:13px; font-weight:600; font-family:var(--font-body);
      transition:all .2s; text-align:center;
    }
    .post-type-tab.active { background:rgba(79,142,247,.2); color:var(--accent); }

    .post-type-pane { display:none; }
    .post-type-pane.active { display:block; }

    /* compose card */
    .post-compose {
      background:var(--bg-card); border:1px solid var(--card-border); border-radius:16px; overflow:hidden;
    }
    .post-compose-title {
      width:100%; padding:16px 20px; background:none;
      border:none; border-bottom:1px solid var(--card-border); outline:none;
      color:var(--text-main); font-family:var(--font-display);
      font-size:18px; font-weight:700;
    }
    .post-compose-title::placeholder { color:var(--text-muted); }
    .post-compose-body {
      width:100%; min-height:140px; padding:14px 20px;
      background:none; border:none; outline:none; resize:vertical;
      color:var(--text-main); font-family:var(--font-body);
      font-size:14px; line-height:1.8;
    }
    .post-compose-body::placeholder { color:var(--text-muted); }
    .char-count-title { font-size:11px; color:var(--text-muted); text-align:right; padding:2px 20px 8px; }

    /* upload zone */
    .upload-zone {
      margin:0 16px 16px; border:2px dashed var(--card-border);
      border-radius:10px; padding:28px; text-align:center;
      cursor:pointer; transition:border-color .2s; position:relative;
    }
    .upload-zone:hover { border-color:var(--accent); }
    .upload-zone input { position:absolute; inset:0; opacity:0; cursor:pointer; z-index:1; }
    .upload-preview-img { max-width:100%; max-height:220px; border-radius:8px; margin-top:12px; display:none; }

    /* file info bar */
    .file-info-bar {
      display:none; margin:0 16px 16px; padding:12px 16px;
      background:rgba(79,142,247,.08); border:1px solid rgba(79,142,247,.2);
      border-radius:10px; align-items:center; gap:10px; font-size:13px;
    }
    .file-info-bar.show { display:flex; }
    .file-size-note { font-size:11px; color:var(--text-muted); }

    /* post footer */
    .post-footer {
      padding:14px 20px; border-top:1px solid var(--card-border);
      display:flex; justify-content:flex-end; gap:10px;
    }
  </style>
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div>

<header class="navbar">
  <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-right" style="margin-left:auto;">
    <a href="javascript:history.back()" style="font-size:13px;color:var(--text-muted);text-decoration:none;">✕ Cancel</a>
  </div>
</header>

<div class="page-wrapper">
  <div class="post-create-page">

    <div class="post-create-hdr"><h1>Create Post</h1></div>

    <?php if ($error): ?>
    <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (empty($my_subs)): ?>
    <div class="card card-body" style="text-align:center;padding:40px;">
      <div style="font-size:40px;margin-bottom:12px;">🗂️</div>
      <h3 style="font-family:var(--font-display);margin-bottom:8px;">No communities joined yet</h3>
      <p style="color:var(--text-muted);font-size:14px;margin-bottom:16px;">Join a community first before posting.</p>
      <a href="dashboard.php" class="btn btn-primary" style="max-width:200px;margin:0 auto;text-decoration:none;">Browse Communities</a>
    </div>

    <?php else: ?>

    <form method="POST" action="create_post.php" enctype="multipart/form-data" id="postForm">
      <input type="hidden" name="active_tab" id="activeTabInput" value="<?php echo htmlspecialchars($active_tab); ?>">

      <!-- Sub selector -->
      <div class="sub-selector">
        <span style="font-size:18px;">🗂️</span>
        <select name="sub_id" required>
          <option value="">Choose a community…</option>
          <?php foreach ($my_subs as $s): ?>
          <option value="<?php echo $s['id']; ?>"
            <?php echo ($s['id'] == $preselect_sub || $s['id'] == (int)($_POST['sub_id'] ?? 0)) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($s['name']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Post type tabs -->
      <div class="post-type-tabs">
        <button type="button" class="post-type-tab <?php echo $active_tab==='text' ?'active':''; ?>"   onclick="switchTab('text',this)">  📝 Text</button>
        <button type="button" class="post-type-tab <?php echo $active_tab==='image'?'active':''; ?>"   onclick="switchTab('image',this)"> 🖼️ Image</button>
        <button type="button" class="post-type-tab <?php echo $active_tab==='link' ?'active':''; ?>"   onclick="switchTab('link',this)">  🔗 Link</button>
        <button type="button" class="post-type-tab <?php echo $active_tab==='doc'  ?'active':''; ?>"   onclick="switchTab('doc',this)">   📄 Document</button>
      </div>

      <div class="post-compose">
        <!-- title shared across all-->
        <input type="text" name="title" id="postTitle" class="post-compose-title"
        placeholder="Title *" maxlength="300" required 
        value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
        <div class="char-count-title"><span id="titleCount">0</span> / 300</div>

        <!--text.just text.-->
        <div class="post-type-pane <?php echo $active_tab==='text'?'active':''; ?>" id="pane-text">
          <textarea name="content" class="post-compose-body"
                    placeholder="What's on your mind? (optional)"><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
        </div>

        <!--clikc images-->
        <div class="post-type-pane <?php echo $active_tab==='image'?'active':''; ?>" id="pane-image">
          <textarea name="image_caption" class="post-compose-body" style="min-height:80px;"
                    placeholder="Caption (optional)"><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
          <div class="upload-zone" id="imgZone">
            <input type="file" name="image" accept="image/*" onchange="previewImg(this)">
            <div id="imgPlaceholder">
              <div style="font-size:36px;margin-bottom:8px;">🖼️</div>
              <div style="font-size:14px;color:var(--text-muted);">Click or drag to upload image</div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">JPG, PNG, GIF, WebP — max 10MB</div>
            </div>
            <img id="imgPreview" class="upload-preview-img" src="" alt="">
          </div>
        </div>

        <!--link url-->
        <div class="post-type-pane <?php echo $active_tab==='link'?'active':''; ?>" id="pane-link">
          <textarea name="link_description" class="post-compose-body" style="min-height:80px;"
                    placeholder="Describe this link (optional)"></textarea>
          <div style="padding:0 16px 16px;">
            <div class="form-group" style="margin-bottom:0;">
              <label>URL</label>
              <input type="url" name="link_url" placeholder="https://"
                     value="<?php echo htmlspecialchars($_POST['link_url'] ?? ''); ?>">
            </div>
          </div>
        </div>

        <!--document upload-->
        <div class="post-type-pane <?php echo $active_tab==='doc'?'active':''; ?>" id="pane-doc">
          <textarea name="document_description" class="post-compose-body" style="min-height:80px;"
                    placeholder="Describe the document (optional)"></textarea>
          <div class="upload-zone" id="docZone">
            <input type="file" name="document"
                   accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt"
                   onchange="previewDoc(this)">
            <div id="docPlaceholder">
              <div style="font-size:36px;margin-bottom:8px;">📄</div>
              <div style="font-size:14px;color:var(--text-muted);">Click to upload document</div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">PDF, Word, PowerPoint, Excel, TXT — max 10MB</div>
            </div>
          </div>
          <div class="file-info-bar" id="fileInfoBar">
            <span style="font-size:22px;">📄</span>
            <div style="flex:1;overflow:hidden;">
              <div id="docFileName" style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
              <div id="docFileSize" class="file-size-note"></div>
            </div>
          </div>
        </div>

        <!--kaki-->
        <div class="post-footer">
          <a href="javascript:history.back()" class="btn btn-secondary"
             style="text-decoration:none;display:inline-block;width:auto;padding:10px 20px;margin:0;">
            Cancel
          </a>
          <button type="submit" name="submit_post" class="btn btn-primary" style="width:auto;padding:10px 28px;">
            Post
          </button>
        </div>
      </div>
    </form>

    <?php endif; ?>
  </div>
</div>

<script>
function switchTab(name, btn) {
  document.querySelectorAll('.post-type-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.post-type-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('pane-'+name).classList.add('active');
  btn.classList.add('active');
  document.getElementById('activeTabInput').value = name;
}

function previewImg(input) {
  if (!input.files[0]) return;
  const r = new FileReader();
  r.onload = e => {
    const img = document.getElementById('imgPreview');
    img.src = e.target.result;
    img.style.display = 'block';
    document.getElementById('imgPlaceholder').style.display = 'none';
  };
  r.readAsDataURL(input.files[0]);
}

function previewDoc(input) {
  if (!input.files[0]) return;
  const f  = input.files[0];
  const mb = (f.size / (1024*1024)).toFixed(2);
  document.getElementById('docFileName').textContent = f.name;
  const sizeEl = document.getElementById('docFileSize');
  sizeEl.textContent = mb + ' MB';
  if (f.size > 10*1024*1024) {
    sizeEl.textContent += ' — ⚠️ Exceeds 10MB limit!';
    sizeEl.style.color = 'var(--danger)';
  }
  document.getElementById('fileInfoBar').classList.add('show');
  document.getElementById('docPlaceholder').style.display = 'none';
}

// title character counter
const titleInput = document.getElementById("postTitle");
const titleCount = document.getElementById("titleCount");

function updateTitleCount() 
{
    titleCount.textContent = titleInput.value.length;
}

titleInput.addEventListener("input", updateTitleCount);

// run once after page loads
updateTitleCount();
</script>
</body>
</html>
