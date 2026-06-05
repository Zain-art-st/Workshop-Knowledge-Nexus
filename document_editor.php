<?php
session_start();
include "db.php";
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id   = $_SESSION['user_id'];
$doc_id    = (int)($_GET['id'] ?? 0);
$doc       = null;
$can_edit  = true;
$is_owner  = false;

// Load existing document
if ($doc_id) {
    $doc = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM documents WHERE id=$doc_id LIMIT 1"));
    if (!$doc) { header("Location: my_posts.php"); exit(); }

    $is_owner = ($doc['owner_id'] == $user_id);

    if (!$is_owner) {
        // Check share permission
        $share = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT permission FROM document_shares WHERE doc_id=$doc_id AND user_id=$user_id LIMIT 1"));
        // Also check token share mode
        if (!$share && $doc['share_mode'] === 'edit') {
            $can_edit = true;
        } elseif ($share) {
            $can_edit = $share['permission'] === 'edit';
        } else {
            //if no access
            header("Location: dashboard.php"); exit();
        }
    }
}

//check join subs
$subs_res = mysqli_query($conn,
    "SELECT s.id, s.name FROM subcommunities s
     JOIN sub_memberships sm ON s.id=sm.sub_id
     WHERE sm.user_id=$user_id ORDER BY s.name ASC");
$my_subs = mysqli_fetch_all($subs_res, MYSQLI_ASSOC);

//Handle save
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_doc']) && $can_edit) {
    $title   = trim($_POST['title']   ?? 'Untitled Document');
    $content = $_POST['content']      ?? '';

    if ($doc_id && $doc) {
        //Update existing
        $upd = mysqli_prepare($conn,"UPDATE documents SET title=?,content=?,updated_at=NOW() WHERE id=? AND owner_id=?");
        mysqli_stmt_bind_param($upd,"ssii",$title,$content,$doc_id,$user_id);
        mysqli_stmt_execute($upd);
        header("Location: document_editor.php?id=$doc_id&saved=1"); exit();
    } else {
        //Create new
        $token = bin2hex(random_bytes(32));
        $ins = mysqli_prepare($conn,
            "INSERT INTO documents (owner_id,title,content,share_token) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($ins,"isss",$user_id,$title,$content,$token);
        mysqli_stmt_execute($ins);
        $new_id = mysqli_insert_id($conn);
        header("Location: document_editor.php?id=$new_id&saved=1"); exit();
    }
}

//Handle share settings update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_share']) && $is_owner) {
    $mode = in_array($_POST['share_mode'],['private','view','edit']) ? $_POST['share_mode'] : 'private';
    mysqli_query($conn,"UPDATE documents SET share_mode='$mode' WHERE id=$doc_id");
    $doc['share_mode'] = $mode;
}

//Handle post to sub
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['post_to_sub']) && $is_owner && $doc_id) {
    $sub_id = (int)($_POST['sub_id'] ?? 0);
    if ($sub_id) {
        $doc_link = "document_editor.php?id=$doc_id";
        $ins = mysqli_prepare($conn,
            "INSERT INTO posts (user_id,sub_id,title,content,link_url) VALUES (?,?,?,?,?)");
        $snippet = mb_substr(strip_tags($doc['content'] ?? ''),0,300);
        $post_title = $doc['title'];
        mysqli_stmt_bind_param($ins,"iisss",$user_id,$sub_id,$post_title,$snippet,$doc_link);
        mysqli_stmt_execute($ins);
        $post_id = mysqli_insert_id($conn);
        mysqli_query($conn,"UPDATE documents SET sub_id=$sub_id,post_id=$post_id WHERE id=$doc_id");
        header("Location: document_editor.php?id=$doc_id&posted=1"); exit();
    }
}

$share_url = $doc ? ((!empty($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'] : '') .
    dirname($_SERVER['PHP_SELF']) . '/view_doc.php?token=' . $doc['share_token']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $doc ? htmlspecialchars($doc['title']) : 'New Document'; ?> – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
  <style>
    body { overflow:hidden; }
    .doc-layout { display:flex; flex-direction:column; height:100vh; padding-top:58px; }

/*doc topbar*/
      .doc-topbar {
      display:flex; align-items:center; gap:12px; padding:8px 20px;
      background:rgba(13,13,26,.9); backdrop-filter:blur(16px);
      border-bottom:1px solid var(--card-border); z-index:100; flex-shrink:0;
    }
    .doc-title-input {
      flex:1; background:none; border:none; outline:none;
      color:var(--text-main); font-family:var(--font-display);
      font-size:16px; font-weight:700; min-width:0;
    }
    .doc-title-input::placeholder { color:var(--text-muted); }
    .doc-status { font-size:11px; color:var(--text-muted); white-space:nowrap; }
    .doc-status.saved { color:var(--success); }
    .doc-btn {
      padding:6px 16px; border-radius:8px; font-size:13px; font-weight:600;
      border:none; cursor:pointer; font-family:var(--font-display); white-space:nowrap; transition:all .2s;
    }
    .doc-btn-primary { background:var(--accent); color:#fff; }
    .doc-btn-primary:hover { background:var(--accent-hover); }
    .doc-btn-ghost { background:rgba(255,255,255,.08); color:var(--text-muted); border:1px solid var(--card-border); }
    .doc-btn-ghost:hover { background:rgba(255,255,255,.14); color:var(--text-main); }

/*Share panel*/
    .share-panel {
      display:none; position:absolute; top:58px; right:20px;
      background:#1e1e35; border:1px solid var(--card-border);
      border-radius:14px; padding:20px; width:340px;
      box-shadow:0 8px 32px rgba(0,0,0,.5); z-index:200;
    }
    .share-panel.open { display:block; }
    .share-panel h4 { font-family:var(--font-display);font-size:15px;font-weight:700;margin-bottom:14px; }
    .share-mode-group { display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:14px; }
    .share-mode-btn {
      padding:8px 6px; border-radius:8px; border:1px solid var(--card-border);
      background:rgba(255,255,255,.04); cursor:pointer; text-align:center;
      font-size:11px; font-weight:600; color:var(--text-muted); transition:all .2s;
      font-family:var(--font-body);
    }
    .share-mode-btn:hover { border-color:var(--accent); color:var(--accent); }
    .share-mode-btn.active { border-color:var(--accent); background:rgba(79,142,247,.15); color:var(--accent); }
    .share-link-box {
      display:flex; gap:6px; align-items:center;
      background:rgba(255,255,255,.06); border:1px solid var(--card-border);
      border-radius:8px; padding:8px 12px; margin-bottom:12px;
    }
    .share-link-box input {
      flex:1; background:none; border:none; outline:none;
      color:var(--text-muted); font-size:12px; font-family:var(--font-body);
    }
    .share-link-box button {
      background:none; border:none; color:var(--accent); cursor:pointer; font-size:12px; font-weight:600;
    }

  /*Post panel*/
    .post-panel {
      display:none; position:absolute; top:58px; right:20px;
      background:#1e1e35; border:1px solid var(--card-border);
      border-radius:14px; padding:20px; width:300px;
      box-shadow:0 8px 32px rgba(0,0,0,.5); z-index:200;
    }
    .post-panel.open { display:block; }
    .post-panel h4 { font-family:var(--font-display);font-size:15px;font-weight:700;margin-bottom:14px; }

  /*Editor area*/
    .doc-editor-wrap {
      flex:1; overflow:hidden; display:flex; flex-direction:column;
      background:#fff; /* Quill works better on white */
    }
    .doc-editor-wrap .ql-toolbar { flex-shrink:0; }
    .doc-editor-wrap .ql-container {
      flex:1; overflow-y:auto; font-size:16px; line-height:1.8;
    }
    #quill-editor { height:100%; }

  /*readonly overlay */
    .readonly-notice {
      text-align:center; padding:8px 20px;
      background:rgba(245,158,11,.1); border-bottom:1px solid rgba(245,158,11,.2);
      font-size:13px; color:var(--warning); flex-shrink:0;
    }
  </style>
</head>
<body>
<div class="stars-bg" style="opacity:.3;"></div>

<header class="navbar">
  <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-right" style="margin-left:auto;gap:8px;">
    <a href="my_posts.php" style="font-size:13px;color:var(--text-muted);text-decoration:none;margin-right:4px;">← My Docs</a>
  </div>w
</header>

<div style="position:relative;">
  <form method="POST" id="docForm">
    <input type="hidden" name="save_doc" value="1">
    <input type="hidden" name="content" id="docContent">

    <div class="doc-topbar">
      <input type="text" name="title" class="doc-title-input"
             placeholder="Untitled Document"
             value="<?php echo htmlspecialchars($doc['title'] ?? ''); ?>"
             <?php echo !$can_edit?'readonly':''; ?>>

      <span class="doc-status <?php echo isset($_GET['saved'])?'saved':''; ?>" id="docStatus">
        <?php echo isset($_GET['saved']) ? '✅ Saved' : ($doc ? 'Last saved '.date('M j, g:i a', strtotime($doc['updated_at'])) : 'Unsaved'); ?>
      </span>

      <?php if ($can_edit): ?>
      <button type="submit" class="doc-btn doc-btn-primary">Save</button>
      <?php endif; ?>

      <?php if ($is_owner && $doc_id): ?>
      <button type="button" class="doc-btn doc-btn-ghost"
              onclick="togglePanel('sharePanel')">Share</button>
      <button type="button" class="doc-btn doc-btn-ghost"
              onclick="togglePanel('postPanel')">Post to Sub</button>
      <?php endif; ?>

      <?php if (!$can_edit): ?>
      <span style="font-size:12px;color:var(--text-muted);padding:4px 10px;background:rgba(255,255,255,.06);border-radius:6px;">👁 View only</span>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($is_owner && $doc_id): ?>
  <div class="share-panel" id="sharePanel">
    <h4>Share Document</h4>
    <form method="POST">
      <input type="hidden" name="update_share" value="1">
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">Who can access via link?</p>
      <div class="share-mode-group">
        <button type="submit" name="share_mode" value="private"
                class="share-mode-btn <?php echo ($doc['share_mode']==='private')?'active':''; ?>">
          <br>Private
        </button>
        <button type="submit" name="share_mode" value="view"
                class="share-mode-btn <?php echo ($doc['share_mode']==='view')?'active':''; ?>">
          👁<br>View only
        </button>
        <button type="submit" name="share_mode" value="edit"
                class="share-mode-btn <?php echo ($doc['share_mode']==='edit')?'active':''; ?>">
          ✏️<br>Can edit
        </button>
      </div>
    </form>
    <?php if ($doc['share_mode'] !== 'private'): ?>
    <div class="share-link-box">
      <input type="text" value="<?php echo htmlspecialchars($share_url); ?>" readonly id="shareLinkInput">
      <button onclick="copyShareLink()">Copy</button>
    </div>
    <p style="font-size:11px;color:var(--text-muted);">Anyone with this link can <?php echo $doc['share_mode']==='edit'?'edit':'view'; ?> this document.</p>
    <?php else: ?>
    <p style="font-size:12px;color:var(--text-muted);">Link sharing is off. Change to View or Edit to generate a link.</p>
    <?php endif; ?>
  </div>

  <div class="post-panel" id="postPanel">
    <h4>Post to Community</h4>
    <?php if ($doc['post_id']): ?>
    <p style="font-size:13px;color:var(--success);">Already posted to a community.</p>
    <a href="post.php?id=<?php echo $doc['post_id']; ?>"
       style="font-size:13px;color:var(--accent);text-decoration:none;">View post →</a>
    <?php else: ?>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">Share this document as a post in one of your communities.</p>
    <form method="POST">
      <input type="hidden" name="post_to_sub" value="1">
      <div class="form-group" style="margin-bottom:12px;">
        <select name="sub_id" required style="background:rgba(255,255,255,.07);border:1px solid var(--card-border);border-radius:8px;padding:8px 12px;color:var(--text-main);width:100%;font-family:var(--font-body);font-size:13px;outline:none;">
          <option value="">Choose community…</option>
          <?php foreach($my_subs as $s): ?>
          <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="doc-btn doc-btn-primary" style="width:100%;">Post</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!--editor-->
<div class="doc-layout">
  <?php if (!$can_edit): ?>
  <div class="readonly-notice">👁 You have view-only access to this document.</div>
  <?php endif; ?>

  <?php if (isset($_GET['posted'])): ?>
  <div style="padding:8px 20px;background:rgba(62,207,142,.1);border-bottom:1px solid rgba(62,207,142,.2);font-size:13px;color:var(--success);text-align:center;">
    ✅ Posted to community successfully! <a href="post.php?id=<?php echo $doc['post_id']; ?>" style="color:var(--accent);">View post →</a>
  </div>
  <?php endif; ?>

  <div class="doc-editor-wrap">
    <div id="quill-editor"><?php echo $doc['content'] ?? ''; ?></div>
  </div>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script src="QuillEditor/quill_toolbar.js"></script>
<script>
// Init Quill
const quill = new Quill('#quill-editor', {
  theme: 'snow',
  readOnly: <?php echo $can_edit ? 'false' : 'true'; ?>,
  modules: { toolbar: <?php echo $can_edit ? 'ScholarSpaceToolbar' : 'false'; ?> }
});

// Auto-save every 30s
<?php if ($can_edit): ?>
let autoSaveTimer = setInterval(saveDoc, 30000);
let isDirty = false;
quill.on('text-change', () => { isDirty = true; document.getElementById('docStatus').textContent = 'Unsaved changes…'; document.getElementById('docStatus').className = 'doc-status'; });

function saveDoc() {
  if (!isDirty) return;
  document.getElementById('docContent').value = quill.root.innerHTML;
  document.getElementById('docForm').submit();
}

// Save on form submit
document.getElementById('docForm').addEventListener('submit', function() {
  document.getElementById('docContent').value = quill.root.innerHTML;
});

// Warn on close if unsaved
window.addEventListener('beforeunload', e => {
  if (isDirty) { e.preventDefault(); e.returnValue = ''; }
});
<?php endif; ?>

// Panel toggle
function togglePanel(id) {
  const panels = ['sharePanel','postPanel'];
  panels.forEach(p => {
    if (p !== id) document.getElementById(p)?.classList.remove('open');
  });
  document.getElementById(id)?.classList.toggle('open');
}
document.addEventListener('click', e => {
  if (!e.target.closest('.share-panel') && !e.target.closest('.post-panel') &&
      !e.target.closest('.doc-btn')) {
    document.querySelectorAll('.share-panel,.post-panel').forEach(p=>p.classList.remove('open'));
  }
});

// Copy link
function copyShareLink() {
  const input = document.getElementById('shareLinkInput');
  if (input) {
    navigator.clipboard.writeText(input.value).then(() => {
      alert('Link copied to clipboard!');
    });
  }
}
</script>
</body>
</html>
