<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$post_id = (int)($_GET['id'] ?? 0);

if (!$post_id) { 
    header("Location: dashboard.php"); 
    exit(); 
}

// Fetch thread item details
$post_query = "SELECT p.*, u.username AS author, u.profile_photo AS author_photo, s.name AS sub_name, s.id AS sub_id, s.profile_photo AS sub_photo
               FROM posts p
               JOIN users u ON p.user_id = u.id
               JOIN subcommunities s ON p.sub_id = s.id
               WHERE p.id = $post_id AND p.is_removed = 0 LIMIT 1";
$post = mysqli_fetch_assoc(mysqli_query($conn, $post_query));

if (!$post) { 
    header("Location: dashboard.php"); 
    exit(); 
}

// Check membership state for comments allowance
$mem_query = "SELECT role FROM sub_memberships WHERE user_id = $user_id AND sub_id = {$post['sub_id']} LIMIT 1";
$mem = mysqli_fetch_assoc(mysqli_query($conn, $mem_query));

$is_member = !empty($mem);
$is_moderator = ($mem['role'] ?? '') === 'moderator';
$is_admin = ($user_type === 'admin');
$can_comment = ($is_member || $is_admin);

// Check if user is banned from this sub
$ban_query = "SELECT id FROM sub_bans WHERE user_id = $user_id AND sub_id = {$post['sub_id']} LIMIT 1";
$sub_banned = mysqli_fetch_assoc(mysqli_query($conn, $ban_query));
if ($sub_banned) {
    $can_comment = false;
}

// Processing incoming new comments
if (isset($_POST['submit_comment']) && $can_comment) {
    $content = trim($_POST['content'] ?? '');
    $parent_id = (int)($_POST['parent_id'] ?? 0) ?: null;
    
    if (!empty($content)) {
        $ins = mysqli_prepare($conn, "INSERT INTO comments (post_id, user_id, parent_id, content) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins, "iiis", $post_id, $user_id, $parent_id, $content);
        mysqli_stmt_execute($ins);
        header("Location: post.php?id=$post_id#comments");
        exit();
    }
}

// Moderation handling - removal of target comments
if (isset($_POST['remove_comment']) && ($is_moderator || $is_admin)) {
    $cid = (int)($_POST['comment_id'] ?? 0);
    if ($cid) {
        mysqli_query($conn, "UPDATE comments SET is_removed = 1 WHERE id = $cid");
    }
    header("Location: post.php?id=$post_id"); 
    exit();
}

// Upvote tracking execution inside comments
if (isset($_POST['vote_comment'])) {
    $cid = (int)($_POST['comment_id'] ?? 0);
    $dir = $_POST['vote_dir'] ?? '';
    if ($cid && $dir === 'up') {
        mysqli_query($conn, "UPDATE comments SET upvotes = upvotes + 1 WHERE id = $cid");
    }
    header("Location: post.php?id=$post_id#c$cid"); 
    exit();
}

// Fetch primary parent comments
$comments_query = "SELECT c.*, u.username AS author, u.profile_photo AS author_photo,
            (SELECT COUNT(*) FROM comments r WHERE r.parent_id = c.id AND r.is_removed = 0) AS reply_count
     FROM comments c
     JOIN users u ON c.user_id = u.id
     WHERE c.post_id = $post_id AND c.parent_id IS NULL AND c.is_removed = 0
     ORDER BY c.upvotes DESC, c.created_at ASC";
$comments_res = mysqli_query($conn, $comments_query);
$comments = mysqli_fetch_all($comments_res, MYSQLI_ASSOC);

// Map children replies to parents
$replies_map = [];
foreach ($comments as $c) {
    $cid = $c['id'];
    $rep_query = "SELECT c.*, u.username AS author, u.profile_photo AS author_photo
                  FROM comments c 
                  JOIN users u ON c.user_id = u.id
                  WHERE c.parent_id = $cid AND c.is_removed = 0
                  ORDER BY c.created_at ASC";
    $rep = mysqli_query($conn, $rep_query);
    $replies_map[$cid] = mysqli_fetch_all($rep, MYSQLI_ASSOC);
}

function timeAgo($date) {
    $diff = time() - strtotime($date);
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

function renderCommentAvatar($photo, $name, $size = 32) {
    $initial = strtoupper(substr($name, 0, 1));
    $fontSize = round($size * 0.45);
    if ($photo && $photo !== 'default.png' && file_exists(__DIR__ . '/' . $photo)) {
        return "<img src='$photo' alt='".htmlspecialchars($name)."' style='width:{$size}px; height:{$size}px; border-radius:50%; object-fit:cover; display:block;'>";
    }
    return "<span style='font-size:{$fontSize}px; font-weight:700; color:#fff;'>$initial</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($post['title']); ?> – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .post-page { max-width: 860px; margin: 0 auto; padding: 0 20px 80px; }
    .post-full {
      background: var(--bg-card); backdrop-filter: blur(16px);
      border: 1px solid var(--card-border); border-radius: 16px;
      overflow: hidden; margin-bottom: 20px;
    }
    .post-full-header { padding: 18px 20px 0; display: flex; align-items: center; gap: 10px; }
    .post-full-sub {
      display: inline-flex; align-items: center; gap: 8px;
      font-size: 13px; font-weight: 700; color: var(--text-main);
      text-decoration: none; margin-bottom: 12px; padding: 0 20px;
      display: block;
    }
    .post-full-sub:hover { color: var(--accent); }
    .post-full-title {
      font-family: var(--font-display); font-size: 22px; font-weight: 800;
      padding: 0 20px 12px; line-height: 1.4;
    }
    .post-full-content {
      padding: 0 20px 16px; font-size: 15px; line-height: 1.8;
      color: #000000; font-weight: bold; white-space: pre-wrap;
    }
    .post-full-image {
      width: calc(100% - 40px); margin: 0 20px 16px;
      border-radius: 12px; max-height: 500px; object-fit: cover; display: block;
    }
    .post-full-link {
      margin: 0 20px 16px; padding: 12px 16px;
      background: rgba(79,142,247,.08); border: 1px solid rgba(79,142,247,.2);
      border-radius: 10px; font-size: 13px; color: var(--accent);
      text-decoration: none; display: flex; align-items: center; gap: 8px;
      word-break: break-all;
    }
    .post-full-file {
      margin: 0 20px 16px; padding: 14px 16px;
      background: rgba(255,255,255,.05); border: 1px solid var(--card-border);
      border-radius: 10px; display: flex; align-items: center; gap: 12px;
    }
    .post-full-file-icon { font-size: 28px; flex-shrink: 0; }
    .post-full-file-name { font-size: 14px; font-weight: 600; }
    .post-full-file-meta { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
    .post-full-file-dl {
      margin-left: auto; padding: 8px 16px; background: var(--accent);
      color: #fff; border-radius: 8px; text-decoration: none; font-size: 13px;
      font-weight: 600; white-space: nowrap;
    }
    .post-full-actions {
      padding: 12px 16px; border-top: 1px solid var(--card-border);
      display: flex; align-items: center; gap: 8px;
    }

    /* Quill Editor Engine Classes Injection styles */
    .post-rich-content { padding: 0 20px 16px; font-size: 15px; line-height: 1.8; color: #000000; font-weight: bold; }
    .post-rich-content h1, .post-rich-content h2, .post-rich-content h3 { color: var(--text-main); margin: 16px 0 8px; font-family: var(--font-display); }
    .post-rich-content p { margin-bottom: 12px; }
    .post-rich-content ul, .post-rich-content ol { padding-left: 24px; margin-bottom: 12px; }
    .post-rich-content li { margin-bottom: 4px; }
    .post-rich-content strong { color: var(--text-main); }
    .post-rich-content a { color: var(--accent); }
    .post-rich-content blockquote { border-left: 3px solid var(--accent); padding-left: 12px; color: var(--text-muted); margin: 12px 0; }
    .post-rich-content code { background: rgba(255,255,255,.08); padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 13px; }
    .post-rich-content pre { background: rgba(255,255,255,.06); padding: 12px 16px; border-radius: 8px; overflow-x: auto; margin-bottom: 12px; }

    .comments-header {
      font-family: var(--font-display); font-size: 16px; font-weight: 700;
      margin-bottom: 16px; display: flex; align-items: center; gap: 10px;
    }
    .comment-count-badge {
      background: rgba(79,142,247,.15); color: var(--accent);
      border: 1px solid rgba(79,142,247,.3); padding: 2px 10px;
      border-radius: 10px; font-size: 12px;
    }
    .comment-compose {
      background: var(--bg-card); border: 1px solid var(--card-border);
      border-radius: 14px; overflow: hidden; margin-bottom: 20px;
    }
    .comment-compose textarea {
      width: 100%; min-height: 90px; padding: 14px 16px;
      background: none; border: none; outline: none; resize: vertical;
      color: var(--text-main); font-family: var(--font-body);
      font-size: 14px; line-height: 1.6;
    }
    .comment-compose textarea::placeholder { color: var(--text-muted); }
    .comment-compose-footer { padding: 10px 14px; border-top: 1px solid var(--card-border); display: flex; justify-content: flex-end; gap: 8px; }
    .comment-submit {
      padding: 7px 20px; background: var(--accent); color: #fff;
      border: none; border-radius: 8px; font-size: 13px; font-weight: 700;
      cursor: pointer; font-family: var(--font-display); transition: opacity .2s;
    }
    .comment-submit:hover { opacity: .9; }

    .comment-card { background: var(--bg-card); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px; margin-bottom: 12px; }
    .comment-card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    .comment-avatar {
      width: 32px; height: 32px; border-radius: 50%;
      background: linear-gradient(135deg,var(--accent),var(--accent2));
      display: flex; align-items: center; justify-content: center;
      font-size: 14px; font-weight: 700; color: #fff; overflow: hidden; flex-shrink: 0;
    }
    .comment-author { font-size: 13px; font-weight: 700; color: var(--text-main); text-decoration: none; }
    .comment-author:hover { color: var(--accent); }
    .comment-time { font-size: 11px; color: var(--text-muted); }
    .comment-text { font-size: 14px; line-height: 1.7; color: var(--text-muted); margin-bottom: 10px; }
    .comment-actions { display: flex; align-items: center; gap: 8px; }
    .comment-vote-group {
      display: flex; align-items: center; gap: 2px;
      background: rgba(255,255,255,.06); border-radius: 20px; padding: 3px 8px;
    }
    .comment-action-btn {
      background: none; border: none; font-size: 12px; color: var(--text-muted);
      cursor: pointer; padding: 4px 8px; border-radius: 6px;
      font-family: var(--font-body); transition: color .2s, background .2s;
    }
    .comment-action-btn:hover { color: var(--accent); background: rgba(79,142,247,.08); }

    .replies-section { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--card-border); }
    .reply-card { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,.04); }
    .reply-card:last-child { border-bottom: none; padding-bottom: 0; }
    .reply-line { width: 2px; background: rgba(255,255,255,.08); border-radius: 1px; flex-shrink: 0; margin-left: 6px; }
    .reply-body { flex: 1; }
    .reply-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
    .reply-text { font-size: 13px; line-height: 1.7; color: var(--text-muted); }

    .reply-form {
      display: none; margin-top: 10px;
      background: rgba(255,255,255,.04); border-radius: 10px; overflow: hidden;
      border: 1px solid var(--card-border);
    }
    .reply-form.open { display: block; }
    .reply-form textarea {
      width: 100%; min-height: 70px; padding: 10px 14px;
      background: none; border: none; outline: none; resize: none;
      color: var(--text-main); font-family: var(--font-body); font-size: 13px;
    }
    .reply-form-footer { padding: 8px 12px; border-top: 1px solid var(--card-border); display: flex; justify-content: flex-end; gap: 8px; }
    .reply-cancel { padding: 5px 14px; background: none; border: 1px solid var(--card-border); border-radius: 6px; color: var(--text-muted); cursor: pointer; font-size: 12px; font-family: var(--font-body); }
    .reply-submit { padding: 5px 14px; background: var(--accent); color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 700; font-family: var(--font-display); }

    .no-comments { text-align: center; padding: 40px 20px; background: var(--bg-card); border: 1px solid var(--card-border); border-radius: 14px; }
  </style>
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div>

<header class="navbar">
  <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-search">
    <input type="text" placeholder="Search anything…" onclick="location.href='search.php'" readonly>
  </div>
  <div class="nav-right">
    <a href="sub.php?id=<?php echo $post['sub_id']; ?>" style="font-size:13px; color:var(--text-muted); text-decoration:none;">← <?php echo htmlspecialchars($post['sub_name']); ?></a>
  </div>
</header>

<div class="page-wrapper">
  <div class="post-page">

    <!-- body section -->
    <div class="post-full">
      <div class="post-full-header">
        <div class="post-avatar"><?php echo renderCommentAvatar($post['author_photo'], $post['author'], 32); ?></div>
        <div class="post-meta">
          <a href="profile.php?id=<?php echo $post['user_id']; ?>" style="color:var(--text-muted); text-decoration:none; font-weight:600;"><?php echo htmlspecialchars($post['author']); ?></a>
          &nbsp;•&nbsp; <?php echo timeAgo($post['created_at']); ?>
        </div>
      </div>

      <a href="sub.php?id=<?php echo $post['sub_id']; ?>" class="post-full-sub">
        <?php echo htmlspecialchars($post['sub_name']); ?>
      </a>

      <div class="post-full-title"><?php echo htmlspecialchars($post['title']); ?></div>

      <?php
        $content = $post['content'] ?? '';
        $is_rich = !empty($content) && (strpos($content, '<p>') !== false || strpos($content, '<h') !== false);
      ?>

      <?php if (!empty($content) && $is_rich): ?>
        <div class="post-rich-content"><?php echo $content; ?></div>
      <?php elseif (!empty($content)): ?>
        <div class="post-full-content"><?php echo htmlspecialchars($content); ?></div>
      <?php endif; ?>

      <?php if (!empty($post['image_url'])): ?>
        <img src="<?php echo htmlspecialchars($post['image_url']); ?>" class="post-full-image" alt="">
      <?php endif; ?>

      <?php if (!empty($post['link_url'])): ?>
        <a href="<?php echo htmlspecialchars($post['link_url']); ?>" target="_blank" class="post-full-link">
          <?php echo htmlspecialchars($post['link_url']); ?>
        </a>
      <?php endif; ?>

      <?php if (!empty($post['file_url'])): ?>
      <div class="post-full-file">
        <div class="post-full-file-icon">
          <?php
            $ext = strtolower(pathinfo($post['file_url'], PATHINFO_EXTENSION));
            $file_icons = ['pdf'=>'📕','doc'=>'📘','docx'=>'📘','ppt'=>'📙','pptx'=>'📙','xls'=>'📗','xlsx'=>'📗','txt'=>'📄'];
            echo $file_icons[$ext] ?? '📄';
          ?>
        </div>
        <div>
          <div class="post-full-file-name"><?php echo basename($post['file_url']); ?></div>
          <div class="post-full-file-meta">
            <?php echo strtoupper($ext); ?> document
            <?php if (file_exists(__DIR__ . '/' . $post['file_url'])): ?>
              — <?php echo round(filesize(__DIR__ . '/' . $post['file_url']) / 1024, 1); ?> KB
            <?php endif; ?>
          </div>
        </div>
        <a href="<?php echo htmlspecialchars($post['file_url']); ?>" download class="post-full-file-dl">⬇ Download</a>
      </div>
      <?php endif; ?>

      <div class="post-full-actions">
        <div class="vote-group">
          <button class="vote-btn upvote" onclick="votePost(<?php echo $post_id; ?>,'up',this)">▲</button>
          <span class="vote-count" id="vc-<?php echo $post_id; ?>"><?php echo number_format($post['upvotes']); ?></span>
          <button class="vote-btn downvote" onclick="votePost(<?php echo $post_id; ?>,'down',this)">▼</button>
        </div>
        <span class="action-btn">💬 <?php echo count($comments); ?> Comments</span>
        <button class="action-btn" onclick="copyLink()">↗ Share</button>
        <?php if ($user_id != $post['user_id']): ?>
          <button class="action-btn" onclick="reportThis()" style="margin-left:auto;">🚩 Report</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- comments queue section -->
    <div class="comments-section" id="comments">
      <div class="comments-header">
        Comments
        <span class="comment-count-badge"><?php echo count($comments); ?></span>
      </div>

      <?php if ($can_comment): ?>
      <div class="comment-compose">
        <form method="POST" action="post.php?id=<?php echo $post_id; ?>">
          <textarea name="content" placeholder="What are your thoughts?" required></textarea>
          <div class="comment-compose-footer">
            <button type="submit" name="submit_comment" class="comment-submit">Comment</button>
          </div>
        </form>
      </div>
      <?php elseif (!$is_member): ?>
      <div style="text-align:center; padding:16px; font-size:13px; color:var(--text-muted); background:var(--bg-card); border:1px solid var(--card-border); border-radius:12px; margin-bottom:20px;">
        <a href="sub.php?id=<?php echo $post['sub_id']; ?>" style="color:var(--accent);">Join this community</a> to leave a comment.
      </div>
      <?php endif; ?>

      <?php if (empty($comments)): ?>
      <div class="no-comments">
        <div style="font-size:40px; margin-bottom:12px;">🗨️</div>
        <div style="font-family:var(--font-display); font-size:15px; margin-bottom:6px;">No comments yet</div>
        <div style="font-size:13px; color:var(--text-muted);">Be the first to share your thoughts!</div>
      </div>
      <?php else: ?>
      <?php foreach ($comments as $c): ?>
      <div class="comment-card" id="c<?php echo $c['id']; ?>">
        <div class="comment-card-header">
          <div class="comment-avatar"><?php echo renderCommentAvatar($c['author_photo'], $c['author'], 32); ?></div>
          <div>
            <a href="profile.php?id=<?php echo $c['user_id']; ?>" class="comment-author"><?php echo htmlspecialchars($c['author']); ?></a>
            <div class="comment-time"><?php echo timeAgo($c['created_at']); ?></div>
          </div>
        </div>

        <div class="comment-text"><?php echo nl2br(htmlspecialchars($c['content'])); ?></div>

        <div class="comment-actions">
          <form method="POST" style="display:inline;">
            <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
            <input type="hidden" name="vote_dir" value="up">
            <div class="comment-vote-group">
              <button type="submit" name="vote_comment" class="vote-btn upvote">▲</button>
              <span class="vote-count"><?php echo $c['upvotes']; ?></span>
            </div>
          </form>
          <?php if ($can_comment): ?>
            <button class="comment-action-btn" onclick="toggleReply(<?php echo $c['id']; ?>)">
              💬 Reply <?php echo $c['reply_count'] > 0 ? '('.$c['reply_count'].')' : ''; ?>
            </button>
          <?php endif; ?>
          <?php if ($c['user_id'] == $user_id || $is_moderator || $is_admin): ?>
          <form method="POST" style="display:inline; margin-left:auto;">
            <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
            <button type="submit" name="remove_comment" class="comment-action-btn" style="color:var(--danger);" onclick="return confirm('Remove this comment?')">
              🗑 Remove
            </button>
          </form>
          <?php endif; ?>
        </div>

        <?php if ($can_comment): ?>
        <div class="reply-form" id="rf-<?php echo $c['id']; ?>">
          <form method="POST" action="post.php?id=<?php echo $post_id; ?>">
            <input type="hidden" name="parent_id" value="<?php echo $c['id']; ?>">
            <textarea name="content" placeholder="Write a reply…" required></textarea>
            <div class="reply-form-footer">
              <button type="button" class="reply-cancel" onclick="toggleReply(<?php echo $c['id']; ?>)">Cancel</button>
              <button type="submit" name="submit_comment" class="reply-submit">Reply</button>
            </div>
          </form>
        </div>
        <?php endif; ?>

        <!-- nested child reply -->
        <?php if (!empty($replies_map[$c['id']])): ?>
        <div class="replies-section">
          <?php foreach ($replies_map[$c['id']] as $r): ?>
          <div class="reply-card" id="c<?php echo $r['id']; ?>">
            <div class="reply-line"></div>
            <div class="reply-body">
              <div class="reply-header">
                <div class="comment-avatar" style="width:26px; height:26px; font-size:11px;">
                  <?php echo renderCommentAvatar($r['author_photo'], $r['author'], 26); ?>
                </div>
                <a href="profile.php?id=<?php echo $r['user_id']; ?>" class="comment-author" style="font-size:12px;"><?php echo htmlspecialchars($r['author']); ?></a>
                <span class="comment-time"><?php echo timeAgo($r['created_at']); ?></span>
                <?php if ($r['user_id'] == $user_id || $is_moderator || $is_admin): ?>
                <form method="POST" style="display:inline; margin-left:auto;">
                  <input type="hidden" name="comment_id" value="<?php echo $r['id']; ?>">
                  <button type="submit" name="remove_comment" class="comment-action-btn" style="color:var(--danger); font-size:11px;" onclick="return confirm('Remove reply?')">🗑</button>
                </form>
                <?php endif; ?>
              </div>
              <div class="reply-text"><?php echo nl2br(htmlspecialchars($r['content'])); ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
function toggleReply(id) {
  const el = document.getElementById('rf-' + id);
  el.classList.toggle('open');
  if (el.classList.contains('open')) {
    el.querySelector('textarea').focus();
  }
}
function votePost(id, dir, btn) {
  fetch('vote.php?post_id=' + id + '&dir=' + dir)
    .then(res => res.json())
    .then(d => {
      if (d.votes !== undefined) {
        document.getElementById('vc-' + id).textContent = d.votes;
      }
    });
}
function copyLink() {
  navigator.clipboard.writeText(window.location.href).then(() => alert('Link copied to clipboard!'));
}
function reportThis() {
  const reason = prompt('Reason for reporting this post:');
  if (!reason) return;
  fetch('report.php?type=post&id=<?php echo $post_id; ?>&reason=' + encodeURIComponent(reason))
    .then(res => res.json())
    .then(d => alert(d.message || 'Report logged.'));
}
</script>
</body>
</html>