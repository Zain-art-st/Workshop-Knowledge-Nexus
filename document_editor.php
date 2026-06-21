<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$doc_id    = (int)($_GET['id'] ?? 0);
$can_edit  = false;
$is_owner  = false;
$doc       = null;

// Load 
if ($doc_id) {
    $doc = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM documents WHERE id = $doc_id LIMIT 1"));

    if (!$doc) {
        // Document not found
        header("Location: dashboard.php?error=doc_not_found");
        exit();
    }

    $is_owner = ((int)$doc['owner_id'] === $user_id) || $user_type === 'admin';

    if ($is_owner) {
        $can_edit = true;
    } else {
        // Check permission
        $share = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT permission FROM document_shares
             WHERE doc_id = $doc_id AND user_id = $user_id LIMIT 1"));
        if ($share) {
            $can_edit = $share['permission'] === 'edit';
        } elseif (!empty($doc['share_mode']) && $doc['share_mode'] === 'edit') {
            $can_edit = true;
        } else {
            // No access
            header("Location: dashboard.php?error=no_access");
            exit();
        }
    }
} else {
    // New document 
    $is_owner = true;
    $can_edit = true;
}

//share mode 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_share']) && $is_owner && $doc_id) {
    $mode = in_array($_POST['share_mode'], ['private','view','edit'])
          ? $_POST['share_mode'] : 'private';
    mysqli_query($conn,
        "UPDATE documents SET share_mode='$mode' WHERE id=$doc_id");
    $doc['share_mode'] = $mode;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_to_sub']) && $is_owner && $doc_id) {
    $sub_id = (int)($_POST['sub_id'] ?? 0);
    if ($sub_id) {
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                  . '://' . $_SERVER['HTTP_HOST']
                  . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/';
        $doc_url  = $base_url . 'view_doc.php?token=' . $doc['share_token'];
        $snippet  = mb_substr(strip_tags($doc['content'] ?? ''), 0, 300);
        $ptitle   = $doc['title'];

        $ins = mysqli_prepare($conn,
            "INSERT INTO posts (user_id, sub_id, title, content, link_url) VALUES (?,?,?,?,?)");
        mysqli_stmt_bind_param($ins, "iisss",
            $user_id, $sub_id, $ptitle, $snippet, $doc_url);
        mysqli_stmt_execute($ins);
        $post_id = mysqli_insert_id($conn);

        mysqli_query($conn,
            "UPDATE documents SET sub_id=$sub_id, post_id=$post_id,
             share_mode=IF(share_mode='private','view',share_mode)
             WHERE id=$doc_id");
        $doc = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT * FROM documents WHERE id=$doc_id LIMIT 1"));

        header("Location: post.php?id=$post_id");
        exit();
    }
}

$share_url = '';
if ($doc && !empty($doc['share_token'])) {
    $base_url  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
               . '://' . $_SERVER['HTTP_HOST']
               . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/';
    $share_url = $base_url . 'view_doc.php?token=' . $doc['share_token'];
}

$subs_res = mysqli_query($conn,
    "SELECT s.id, s.name FROM subcommunities s
     JOIN sub_memberships sm ON s.id = sm.sub_id
     WHERE sm.user_id = $user_id ORDER BY s.name ASC");
$my_subs = mysqli_fetch_all($subs_res, MYSQLI_ASSOC);
if ($user_type === 'admin') {
    $my_subs = mysqli_fetch_all(
        mysqli_query($conn, "SELECT id, name FROM subcommunities ORDER BY name ASC"),
        MYSQLI_ASSOC
    );
}
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
    html, body { height:100%; overflow:hidden; margin:0; padding:0; }

    .doc-shell {
      display: flex;
      flex-direction: column;
      height: 100vh;
      background: #1a1a2e;
    }

    .doc-topbar {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 0 16px;
      height: 54px;
      background: #13122a;
      border-bottom: 1px solid rgba(255,255,255,.1);
      flex-shrink: 0;
      position: relative;
      z-index: 200;
    }
    .doc-back {
      font-size: 13px; color: #9b9ab0; text-decoration: none;
      white-space: nowrap; padding: 5px 10px;
      border-radius: 6px; transition: background .2s;
    }
    .doc-back:hover { background: rgba(255,255,255,.08); color: #f0eff5; }
    .doc-title-input {
      flex: 1; background: none; border: none; outline: none;
      color: #f0eff5; font-family: 'Sora', sans-serif;
      font-size: 16px; font-weight: 700; min-width: 0;
    }
    .doc-title-input::placeholder { color: #9b9ab0; }
    .doc-title-input:read-only   { cursor: default; opacity: .7; }
    .doc-status {
      font-size: 11px; color: #9b9ab0; white-space: nowrap; min-width: 80px;
    }
    .doc-status.saved   { color: #3ecf8e; }
    .doc-status.unsaved { color: #f59e0b; }
    .doc-status.error   { color: #ff4f6a; }

    .doc-btn {
      padding: 6px 14px; border-radius: 8px; font-size: 13px;
      font-weight: 600; border: none; cursor: pointer;
      font-family: 'Sora', sans-serif; white-space: nowrap;
      transition: opacity .2s, transform .15s;
    }
    .doc-btn:hover { opacity: .88; transform: translateY(-1px); }
    .doc-btn-save   { background: #4f8ef7; color: #fff; }
    .doc-btn-share  { background: rgba(79,142,247,.15); color: #4f8ef7; border: 1px solid rgba(79,142,247,.3); }
    .doc-btn-post   { background: rgba(192,109,232,.15); color: #c06de8; border: 1px solid rgba(192,109,232,.3); }
    .doc-btn-pdf    { background: rgba(62,207,142,.12); color: #3ecf8e; border: 1px solid rgba(62,207,142,.3); }
    .doc-btn-close  {
      width: 32px; height: 32px; border-radius: 50%;
      background: rgba(255,255,255,.08); color: #9b9ab0;
      display: flex; align-items: center; justify-content: center;
      font-size: 15px; flex-shrink: 0;
    }
    .doc-btn-close:hover { background: rgba(255,79,106,.2); color: #ff4f6a; }

    .doc-panel {
      display: none; position: absolute;
      top: 58px; right: 16px;
      background: #1e1c35;
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 14px; padding: 18px;
      min-width: 300px; max-width: 360px;
      box-shadow: 0 8px 32px rgba(0,0,0,.6);
      z-index: 300;
    }
    .doc-panel.open { display: block; }
    .doc-panel h4 {
      font-family: 'Sora', sans-serif; font-size: 14px;
      font-weight: 700; margin-bottom: 12px;
      color: #f0eff5;
    }
    .doc-panel p { font-size: 12px; color: #9b9ab0; margin-bottom: 10px; line-height: 1.6; }

    .share-mode-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 6px; margin-bottom: 14px; }
    .share-mode-btn {
      padding: 8px 4px; border-radius: 8px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(255,255,255,.04);
      color: #9b9ab0; font-size: 11px; font-weight: 600;
      cursor: pointer; text-align: center; transition: all .2s;
      font-family: 'DM Sans', sans-serif;
    }
    .share-mode-btn:hover,
    .share-mode-btn.active {
      border-color: #4f8ef7; background: rgba(79,142,247,.15); color: #4f8ef7;
    }
    .share-link-row {
      display: flex; gap: 6px; align-items: center;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 8px; padding: 8px 12px;
      margin-bottom: 8px;
    }
    .share-link-row input {
      flex: 1; background: none; border: none; outline: none;
      color: #9b9ab0; font-size: 12px; font-family: 'DM Sans', sans-serif;
    }
    .share-link-row button {
      background: none; border: none; color: #4f8ef7;
      cursor: pointer; font-size: 12px; font-weight: 600;
      font-family: 'DM Sans', sans-serif; white-space: nowrap;
    }
    .share-link-row button:hover { text-decoration: underline; }

    .post-sub-select {
      width: 100%; padding: 8px 12px; margin-bottom: 10px;
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 8px; color: #f0eff5;
      font-family: 'DM Sans', sans-serif; font-size: 13px; outline: none;
    }
    .post-sub-select option { background: #1e1c35; }

  .doc-editor-wrap {
      flex: 1;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      background: #e8e8e8;
    }

    .doc-editor-wrap .ql-toolbar.ql-snow {
      background: #f5f5f5;
      border: none;
      border-bottom: 1px solid #ddd;
      position: sticky;
      top: 0;
      z-index: 10;
      flex-shrink: 0;
    }

    .doc-page-scroll {
      flex: 1;
      overflow-y: auto;
      padding: 32px 20px;
    }

    .doc-page {
      width: 100%;
      max-width: 820px;
      min-height: 90vh;
      margin: 0 auto;
      background: #fff;
      border-radius: 4px;
      box-shadow: 0 4px 24px rgba(0,0,0,.25);
      overflow: hidden;
    }

    .doc-page .ql-container.ql-snow {
      border: none;
      font-size: 15px;
    }
    .doc-page .ql-editor {
      min-height: 90vh;
      padding: 48px 56px;
      color: #1a1a1a;
      font-size: 15px;
      line-height: 1.9;
    }
    .doc-page .ql-editor.ql-blank::before {
      color: #aaa;
      font-style: normal;
      left: 56px;
    }

    .readonly-banner {
      background: rgba(245,158,11,.1);
      border-bottom: 1px solid rgba(245,158,11,.2);
      padding: 8px 20px;
      font-size: 13px;
      color: #f59e0b;
      text-align: center;
      flex-shrink: 0;
    }


    .posted-banner {
      background: rgba(62,207,142,.1);
      border-bottom: 1px solid rgba(62,207,142,.2);
      padding: 8px 20px;
      font-size: 13px;
      color: #3ecf8e;
      text-align: center;
      flex-shrink: 0;
    }

    @media print {
      .doc-shell    { display: none !important; }
      #printArea    { display: block !important; }
    }
  </style>
</head>
<body>

<div id="printArea" style="display:none; padding:40px; font-family:Georgia,serif; color:#000;">
  <h1 id="printTitle" style="margin-bottom:24px; font-size:24px;"></h1>
  <div id="printContent" style="font-size:14px; line-height:1.9;"></div>
</div>

<div class="doc-shell">

  <div class="doc-topbar">
    <a href="dashboard.php" class="doc-back">← Back</a>

    <input type="text" id="docTitle" class="doc-title-input"
           placeholder="Untitled Document"
           value="<?php echo htmlspecialchars($doc['title'] ?? ''); ?>"
           <?php echo !$can_edit ? 'readonly' : ''; ?>>

    <span class="doc-status" id="docStatus">
      <?php echo $doc ? 'Last saved ' . date('g:i a', strtotime($doc['updated_at'])) : 'Not saved yet'; ?>
    </span>

    <?php if ($can_edit): ?>
    <button class="doc-btn doc-btn-save" onclick="saveDoc()">💾 Save</button>
    <?php endif; ?>

    <?php if ($is_owner && $doc_id): ?>
    <button class="doc-btn doc-btn-share" onclick="togglePanel('sharePanel')">🔗 Share</button>
    <button class="doc-btn doc-btn-post"  onclick="togglePanel('postPanel')">📤 Post to Sub</button>
    <?php endif; ?>

    <button class="doc-btn doc-btn-pdf" onclick="exportPDF()">⬇️ Export PDF</button>

    <button class="doc-btn doc-btn-close" onclick="closeDoc()" title="Close">✕</button>
  </div>

  <?php if ($is_owner && $doc_id): ?>
  <div class="doc-panel" id="sharePanel">
    <h4>🔗 Share Document</h4>
    <p>Anyone who has the link can access this document based on the permission you set below.</p>
    <form method="POST" action="document_editor.php?id=<?php echo $doc_id; ?>">
      <input type="hidden" name="update_share" value="1">
      <div class="share-mode-row">
        <button type="submit" name="share_mode" value="private"
                class="share-mode-btn <?php echo ($doc['share_mode']==='private')?'active':''; ?>">
          🔒<br>Private
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
    <?php if (!empty($share_url) && $doc['share_mode'] !== 'private'): ?>
    <div class="share-link-row">
      <input type="text" id="shareLinkInput" value="<?php echo htmlspecialchars($share_url); ?>" readonly>
      <button onclick="copyShareLink()">Copy</button>
    </div>
    <p style="margin:0;">
      Anyone with this link can
      <strong style="color:#f0eff5;"><?php echo $doc['share_mode']==='edit' ? 'edit' : 'view'; ?></strong>
      this document.
    </p>
    <?php else: ?>
    <p style="margin:0;color:#9b9ab0;">Set to View or Edit above to generate a shareable link.</p>
    <?php endif; ?>
  </div>


  <div class="doc-panel" id="postPanel">
    <h4>📤 Post to Community</h4>
    <?php if (!empty($doc['post_id'])): ?>
    <p style="color:#3ecf8e;">Already posted to a community.</p>
    <a href="post.php?id=<?php echo $doc['post_id']; ?>"
       style="color:#4f8ef7;font-size:13px;text-decoration:none;">View post →</a>
    <?php else: ?>
    <p>Share this document as a post. The link will direct readers to the document.</p>
    <form method="POST" action="document_editor.php?id=<?php echo $doc_id; ?>">
      <input type="hidden" name="post_to_sub" value="1">
      <select name="sub_id" class="post-sub-select" required>
        <option value="">Choose a community…</option>
        <?php foreach ($my_subs as $s): ?>
        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="doc-btn doc-btn-save" style="width:100%;padding:9px;">
        Post to Community
      </button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!$can_edit): ?>
  <div class="readonly-banner">You have view-only access to this document.</div>
  <?php endif; ?>

  <?php if (isset($_GET['posted'])): ?>
  <div class="posted-banner">
    ✅ Posted to community!
    <a href="post.php?id=<?php echo $doc['post_id'] ?? ''; ?>" style="color:#4f8ef7;">View post →</a>
  </div>
  <?php endif; ?>

  <div class="doc-editor-wrap" id="editorWrap">
    <div class="doc-page-scroll">
      <div class="doc-page">
        <div id="quillEditor"><?php echo $doc['content'] ?? ''; ?></div>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
const quill = new Quill('#quillEditor', {
  theme: 'snow',
  readOnly: <?php echo $can_edit ? 'false' : 'true'; ?>,
  placeholder: 'Start writing…',
  modules: {
    toolbar: <?php echo $can_edit ? 'true' : 'false'; ?>
  }
});

<?php if ($can_edit): ?>
quill.getModule('toolbar') && (() => {
  // Quill default snow toolbar 
})();
<?php endif; ?>

let isDirty     = false;
let currentDocId = <?php echo $doc_id ?: 'null'; ?>;

quill.on('text-change', () => {
  isDirty = true;
  setStatus('unsaved', '● Unsaved changes');
});

setInterval(() => { if (isDirty) saveDoc(); }, 30000);

function setStatus(type, text) {
  const el = document.getElementById('docStatus');
  el.textContent  = text;
  el.className    = 'doc-status ' + type;
}

function saveDoc() {
  if (!<?php echo $can_edit ? 'true' : 'false'; ?>) return;
  const title   = document.getElementById('docTitle').value.trim() || 'Untitled Document';
  const content = quill.root.innerHTML;
  setStatus('', 'Saving…');

  fetch('save_document.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: currentDocId, title, content })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      currentDocId = data.doc_id;
      isDirty      = false;
      setStatus('saved', '✅Saved');
      // Update URL without reload if new doc
      if (!<?php echo $doc_id ?: 'false'; ?>) {
        history.replaceState(null, '', 'document_editor.php?id=' + data.doc_id);
      }
      setTimeout(() => { if (!isDirty) setStatus('', ''); }, 3000);
    } else {
      setStatus('error', 'Save failed');
      console.error('Save error:', data.error);
    }
  })
  .catch(err => {
    setStatus('error', 'Save failed');
    console.error('Network error:', err);
  });
}

function exportPDF() {
  const title   = document.getElementById('docTitle').value || 'Untitled Document';
  const content = quill.root.innerHTML;
  document.getElementById('printTitle').textContent = title;
  document.getElementById('printContent').innerHTML = content;
  document.getElementById('printArea').style.display = 'block';
  window.print();
  setTimeout(() => {
    document.getElementById('printArea').style.display = 'none';
  }, 1500);
}

function togglePanel(id) {
  const panels = document.querySelectorAll('.doc-panel');
  panels.forEach(p => {
    if (p.id !== id) p.classList.remove('open');
  });
  document.getElementById(id)?.classList.toggle('open');
}

document.addEventListener('click', e => {
  if (!e.target.closest('.doc-panel') && !e.target.closest('.doc-btn-share') &&
      !e.target.closest('.doc-btn-post')) {
    document.querySelectorAll('.doc-panel').forEach(p => p.classList.remove('open'));
  }
});

function copyShareLink() {
  const input = document.getElementById('shareLinkInput');
  if (!input) return;
  navigator.clipboard.writeText(input.value)
    .then(() => alert('Link copied to clipboard!'))
    .catch(() => {
      input.select();
      document.execCommand('copy');
      alert('Link copied!');
    });
}

function closeDoc() {
  if (isDirty) {
    if (!confirm('You have unsaved changes. Leave without saving?')) return;
  }
  window.location.href = 'dashboard.php';
}

window.addEventListener('beforeunload', e => {
  if (isDirty) { e.preventDefault(); e.returnValue = ''; }
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.doc-panel').forEach(p => p.classList.remove('open'));
  }
});
</script>
</body>
</html>