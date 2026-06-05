<?php
session_start();
include "db.php";

$token = trim($_GET['token'] ?? '');
if (empty($token)) { header("Location: dashboard.php"); exit(); }

$doc = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT d.*, u.username AS owner_name
     FROM documents d JOIN users u ON d.owner_id=u.id
     WHERE d.share_token='".mysqli_real_escape_string($conn,$token)."' LIMIT 1"));

if (!$doc || $doc['share_mode'] === 'private') {
    http_response_code(403);
    ?><!DOCTYPE html><html><head><title>Access Denied</title><link rel="stylesheet" href="styles.css"></head>
    <body><div class="stars-bg"></div><div class="sunset-bg"></div>
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;text-align:center;padding:20px;">
      <div style="font-size:48px;margin-bottom:16px;">🔒</div>
      <h2 style="font-family:var(--font-display);margin-bottom:8px;">Access Denied</h2>
      <p style="color:var(--text-muted);font-size:14px;">This document is private or the link is invalid.</p>
      <a href="dashboard.php" style="color:var(--accent);text-decoration:none;margin-top:16px;font-size:13px;">← Go to Feed</a>
    </div></body></html>
    <?php exit();
}

$can_edit = false;
$user_id  = $_SESSION['user_id'] ?? null;

if ($user_id) {
    if ($doc['owner_id'] == $user_id) {
        // Owner 
        header("Location: document_editor.php?id=".$doc['id']); exit();
    }
    // Check share
    $share = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT permission FROM document_shares WHERE doc_id={$doc['id']} AND user_id=$user_id LIMIT 1"));
    if ($share) $can_edit = $share['permission']==='edit';
    elseif ($doc['share_mode']==='edit') $can_edit = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($doc['title']); ?> – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
  <style>
    body { overflow:hidden; }
    .doc-layout  { display:flex; flex-direction:column; height:100vh; padding-top:58px; }
    .doc-topbar  { display:flex;align-items:center;gap:12px;padding:8px 20px;background:rgba(13,13,26,.9);backdrop-filter:blur(16px);border-bottom:1px solid var(--card-border);flex-shrink:0; }
    .doc-title   { font-family:var(--font-display);font-size:16px;font-weight:700;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .doc-editor-wrap { flex:1;overflow:hidden;display:flex;flex-direction:column;background:#fff; }
    .doc-editor-wrap .ql-container { flex:1;overflow-y:auto;font-size:16px;line-height:1.8; }
    #quill-editor { height:100%; }
    .doc-btn { padding:6px 16px;border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;font-family:var(--font-display);transition:all .2s; }
    .doc-btn-primary { background:var(--accent);color:#fff; }
    .doc-btn-ghost { background:rgba(255,255,255,.08);color:var(--text-muted);border:1px solid var(--card-border); }
    .doc-status { font-size:11px;color:var(--text-muted); }
    .doc-status.saved { color:var(--success); }
  </style>
</head>
<body>
<header class="navbar">
  <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-right" style="margin-left:auto;">
    <?php if (!$user_id): ?>
    <a href="login.php" style="font-size:13px;color:var(--accent);text-decoration:none;">Login to edit</a>
    <?php endif; ?>
  </div>
</header>

<?php if ($can_edit): ?>
<div style="position:relative;">
  <form method="POST" action="document_editor.php?id=<?php echo $doc['id']; ?>" id="docForm">
    <input type="hidden" name="save_doc" value="1">
    <input type="hidden" name="content" id="docContent">
    <input type="hidden" name="title" value="<?php echo htmlspecialchars($doc['title']); ?>">
    <div class="doc-topbar">
      <div class="doc-title"><?php echo htmlspecialchars($doc['title']); ?></div>
      <span style="font-size:12px;color:var(--text-muted);">by <?php echo htmlspecialchars($doc['owner_name']); ?></span>
      <span class="doc-status" id="docStatus">Viewing</span>
      <button type="submit" class="doc-btn doc-btn-primary">Save</button>
    </div>
  </form>
</div>
<?php else: ?>
<div class="doc-topbar">
  <div class="doc-title"><?php echo htmlspecialchars($doc['title']); ?></div>
  <span style="font-size:12px;color:var(--text-muted);">by <?php echo htmlspecialchars($doc['owner_name']); ?></span>
  <span style="font-size:12px;padding:4px 10px;background:rgba(255,255,255,.06);border-radius:6px;color:var(--text-muted);">👁 View only</span>
</div>
<?php endif; ?>

<div class="doc-layout">
  <div class="doc-editor-wrap">
    <div id="quill-editor"><?php echo $doc['content'] ?? ''; ?></div>
  </div>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script src="QuillEditor/quill_toolbar.js"></script>
<script>
const quill = new Quill('#quill-editor', {
  theme: 'snow',
  readOnly: <?php echo $can_edit ? 'false' : 'true'; ?>,
  modules: { toolbar: <?php echo $can_edit ? 'ScholarSpaceToolbar' : 'false'; ?> }
});
<?php if ($can_edit): ?>
let isDirty = false;
quill.on('text-change', () => {
  isDirty = true;
  document.getElementById('docStatus').textContent = 'Unsaved changes…';
  document.getElementById('docStatus').className = 'doc-status';
});
document.getElementById('docForm')?.addEventListener('submit', function() {
  document.getElementById('docContent').value = quill.root.innerHTML;
});
window.addEventListener('beforeunload', e => { if(isDirty){e.preventDefault();e.returnValue='';} });
<?php endif; ?>
</script>
</body>
</html>
