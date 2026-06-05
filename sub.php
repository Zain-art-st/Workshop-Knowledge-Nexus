<?php
session_start();
include "db.php";
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$sub_id    = (int)($_GET['id'] ?? 0);
if (!$sub_id) { header("Location: dashboard.php"); exit(); }

/* ── fetch sub ─────────────────────────────────────────────── */
$sub = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT s.*, u.username AS creator_name, u.id AS creator_uid
     FROM subcommunities s LEFT JOIN users u ON s.creator_id = u.id
     WHERE s.id = $sub_id LIMIT 1"));
if (!$sub) { header("Location: dashboard.php"); exit(); }

/* ── track visit ───────────────────────────────────────────── */
$vis = mysqli_prepare($conn,
    "INSERT INTO recent_visits (user_id, sub_id) VALUES (?,?)
     ON DUPLICATE KEY UPDATE visited_at = NOW()");
mysqli_stmt_bind_param($vis, "ii", $user_id, $sub_id);
mysqli_stmt_execute($vis);

/* ── membership & role ─────────────────────────────────────── */
$mem_row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT role FROM sub_memberships WHERE user_id=$user_id AND sub_id=$sub_id LIMIT 1"));
$is_member    = !empty($mem_row);
$is_moderator = ($mem_row['role'] ?? '') === 'moderator';
$is_admin     = $user_type === 'admin';

/* ── sub-ban check ─────────────────────────────────────────── */
$ban_row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT reason FROM sub_bans WHERE user_id=$user_id AND sub_id=$sub_id LIMIT 1"));
$is_sub_banned = !empty($ban_row);

/* ── handle join / leave ───────────────────────────────────── */
if (isset($_POST['join']) && !$is_sub_banned) {
    if (!$is_member) {
        $j = mysqli_prepare($conn,
            "INSERT IGNORE INTO sub_memberships (user_id, sub_id, role) VALUES (?,?,'member')");
        mysqli_stmt_bind_param($j, "ii", $user_id, $sub_id);
        mysqli_stmt_execute($j);
        mysqli_query($conn, "UPDATE subcommunities SET member_count=member_count+1 WHERE id=$sub_id");
    }
    header("Location: sub.php?id=$sub_id"); exit();
}
if (isset($_POST['leave']) && $is_member && !$is_moderator) {
    $l = mysqli_prepare($conn,
        "DELETE FROM sub_memberships WHERE user_id=? AND sub_id=?");
    mysqli_stmt_bind_param($l, "ii", $user_id, $sub_id);
    mysqli_stmt_execute($l);
    mysqli_query($conn, "UPDATE subcommunities SET member_count=GREATEST(member_count-1,0) WHERE id=$sub_id");
    header("Location: sub.php?id=$sub_id"); exit();
}

/* ── moderator actions ─────────────────────────────────────── */
// Moderator can only suspend from this sub — full ban/suspend is admin-only
if (isset($_POST['mod_action']) && ($is_moderator || $is_admin)) {
    $target_uid = (int)($_POST['target_uid'] ?? 0);
    $mod_reason = trim($_POST['mod_reason'] ?? '');
    $action     = $_POST['mod_action'];

    if ($action === 'sub_suspend' && $target_uid) {
        // Check target is not mod/admin
        $trow = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT user_type FROM users WHERE id=$target_uid LIMIT 1"));
        if ($trow && $trow['user_type'] !== 'admin') {
            $sb = mysqli_prepare($conn,
                "INSERT IGNORE INTO sub_bans (sub_id, user_id, banned_by, reason) VALUES (?,?,?,?)");
            mysqli_stmt_bind_param($sb, "iiis", $sub_id, $target_uid, $user_id, $mod_reason);
            mysqli_stmt_execute($sb);
            // Remove from membership
            $rm = mysqli_prepare($conn,
                "DELETE FROM sub_memberships WHERE user_id=? AND sub_id=?");
            mysqli_stmt_bind_param($rm, "ii", $target_uid, $sub_id);
            mysqli_stmt_execute($rm);
        }
    } elseif ($action === 'sub_unsuspend' && $target_uid) {
        $ub = mysqli_prepare($conn,
            "DELETE FROM sub_bans WHERE user_id=? AND sub_id=?");
        mysqli_stmt_bind_param($ub, "ii", $target_uid, $sub_id);
        mysqli_stmt_execute($ub);
    } elseif ($action === 'remove_post') {
        $pid = (int)($_POST['post_id'] ?? 0);
        if ($pid) mysqli_query($conn,
            "UPDATE posts SET is_removed=1 WHERE id=$pid AND sub_id=$sub_id");
    }
    header("Location: sub.php?id=$sub_id"); exit();
}

/* ── posts ─────────────────────────────────────────────────── */
$posts_res = mysqli_query($conn,
    "SELECT p.*, u.username AS author, u.profile_photo AS author_photo,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id=p.id AND c.is_removed=0) AS comment_count
     FROM posts p JOIN users u ON p.user_id=u.id
     WHERE p.sub_id=$sub_id AND p.is_removed=0
     ORDER BY p.created_at DESC LIMIT 30");
$posts = mysqli_fetch_all($posts_res, MYSQLI_ASSOC);

/* ── member list for mod panel ─────────────────────────────── */
$members_res = null;
if ($is_moderator || $is_admin) {
    $members_res = mysqli_query($conn,
        "SELECT u.id, u.username, sm.role
         FROM sub_memberships sm JOIN users u ON sm.user_id = u.id
         WHERE sm.sub_id=$sub_id
         ORDER BY FIELD(sm.role,'moderator','member'), u.username ASC
         LIMIT 50");
}

/* ── banned list for mod panel ─────────────────────────────── */
$banned_list = [];
if ($is_moderator || $is_admin) {
    $br = mysqli_query($conn,
        "SELECT sb.*, u.username FROM sub_bans sb JOIN users u ON sb.user_id=u.id
         WHERE sb.sub_id=$sub_id ORDER BY sb.created_at DESC");
    $banned_list = mysqli_fetch_all($br, MYSQLI_ASSOC);
}

/* ── helpers ───────────────────────────────────────────────── */
function timeAgo(string $d): string { $t=strtotime($d); if(!$t||$t>time()) return 'just now'; $s=time()-$t; if($s<60) return $s.'s ago'; if($s<3600) return floor($s/60).'m ago'; if($s<86400) return floor($s/3600).'h ago'; if($s<604800) return floor($s/86400).'d ago'; return date('M j, Y',$t); }
function ava($photo,$name,$size=32){
    $init=strtoupper(substr($name,0,1));$fs=round($size*.45);
    if($photo&&$photo!=='default.png'&&file_exists(__DIR__.'/'.$photo))
        return "<img src='$photo' alt='".htmlspecialchars($name)."' style='width:{$size}px;height:{$size}px;border-radius:50%;object-fit:cover;display:block;'>";
    return "<span style='font-size:{$fs}px;font-weight:700;color:#fff;'>$init</span>";
}
function fileIcon($ext){$m=['pdf'=>'📕','doc'=>'📘','docx'=>'📘','ppt'=>'📙','pptx'=>'📙','xls'=>'📗','xlsx'=>'📗','txt'=>'📄'];return $m[strtolower($ext)]??'📄';}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($sub['name']); ?> – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* ── layout ── */
    .sub-layout { max-width:1060px; margin:0 auto; padding:0 20px 80px; }
    .sub-body   { display:grid; grid-template-columns:1fr 300px; gap:20px; align-items:start; }
    @media(max-width:768px){ .sub-body { grid-template-columns:1fr; } }

    /* ── banner ── */
    .sub-banner {
      height:190px; overflow:hidden;
      background:linear-gradient(135deg,#1a0a2e 0%,#2d0f3f 40%,#5a1a4a 70%,#c4562a 100%);
    }
    .sub-banner img { width:100%; height:100%; object-fit:cover; display:block; }

    /* ── header card ── */
    .sub-header-card {
      background:var(--bg-card); backdrop-filter:blur(16px);
      border:1px solid var(--card-border); border-top:none;
      border-radius:0 0 16px 16px; padding:0 24px 20px; margin-bottom:24px;
    }
    .sub-icon-row {
      display:flex; justify-content:space-between; align-items:flex-end;
      margin-top:-28px; margin-bottom:14px;
    }
    .sub-big-icon {
      width:72px; height:72px; border-radius:16px;
      background:linear-gradient(135deg,var(--accent),var(--accent2));
      border:4px solid var(--bg-deep); display:flex; align-items:center;
      justify-content:center; font-size:30px; overflow:hidden; flex-shrink:0;
    }
    .sub-big-icon img { width:100%; height:100%; object-fit:cover; }
    .sub-name    { font-family:var(--font-display); font-size:24px; font-weight:800; margin-bottom:6px; }
    .sub-meta    { font-size:13px; color:var(--text-muted); display:flex; gap:16px; flex-wrap:wrap; }
    .sub-meta a  { color:var(--accent); text-decoration:none; }
    .sub-meta a:hover { text-decoration:underline; }
    .sub-actions { display:flex; gap:10px; align-items:center; }
    .join-btn {
      padding:8px 24px; border-radius:20px; font-size:14px; font-weight:700;
      border:none; cursor:pointer; font-family:var(--font-display); transition:all .2s;
    }
    .join-btn.join  { background:var(--accent); color:#fff; }
    .join-btn.join:hover  { background:var(--accent-hover); }
    .join-btn.leave { background:rgba(255,255,255,.1); color:var(--text-muted); border:1px solid var(--card-border); }
    .join-btn.leave:hover { background:rgba(255,79,106,.12); color:var(--danger); border-color:var(--danger); }

    /* ── notices ── */
    .sub-banned-notice {
      background:rgba(255,79,106,.1); border:1px solid rgba(255,79,106,.3);
      border-radius:12px; padding:14px 18px; margin-bottom:20px;
      font-size:14px; color:#ff7a8a; display:flex; align-items:center; gap:10px;
    }

    /* ── compose prompt ── */
    .compose-prompt {
      display:flex; align-items:center; gap:12px; padding:12px 16px;
      background:var(--bg-card); border:1px solid var(--card-border);
      border-radius:12px; margin-bottom:16px; text-decoration:none;
      color:var(--text-muted); font-size:14px; transition:border-color .2s;
    }
    .compose-prompt:hover { border-color:rgba(79,142,247,.4); color:var(--text-main); }

    /* ── sidebar cards ── */
    .sub-sidebar    { display:flex; flex-direction:column; gap:16px; }
    .sub-info-card  { background:var(--bg-card); border:1px solid var(--card-border); border-radius:16px; overflow:hidden; }
    .sub-info-hdr   { padding:13px 16px; font-family:var(--font-display); font-size:13px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.8px; border-bottom:1px solid var(--card-border); }
    .sub-info-body  { padding:14px 16px; font-size:13px; line-height:1.7; color:var(--text-muted); }
    .sub-stat-row   { display:flex; justify-content:space-between; padding:8px 16px; border-bottom:1px solid rgba(255,255,255,.04); font-size:13px; }
    .sub-stat-row:last-child { border-bottom:none; }
    .sub-stat-val   { font-weight:700; color:var(--text-main); }
    .rules-list     { padding:12px 16px; list-style:none; }
    .rules-list li  { padding:7px 0; border-bottom:1px solid rgba(255,255,255,.04); font-size:13px; color:var(--text-muted); display:flex; gap:8px; }
    .rules-list li:last-child { border-bottom:none; }
    .rules-num      { color:var(--accent); font-weight:700; flex-shrink:0; min-width:18px; }

    /* ── mod panel ── */
    .mod-panel      { background:rgba(245,158,11,.05); border:1px solid rgba(245,158,11,.2); border-radius:16px; overflow:hidden; }
    .mod-panel-hdr  { padding:12px 16px; font-size:13px; font-weight:700; color:var(--warning); border-bottom:1px solid rgba(245,158,11,.15); display:flex; align-items:center; gap:8px; }
    .mod-tabs       { display:flex; border-bottom:1px solid rgba(245,158,11,.15); }
    .mod-tab        { flex:1; padding:8px; font-size:12px; font-weight:600; text-align:center; cursor:pointer; color:var(--text-muted); background:none; border:none; font-family:var(--font-body); transition:color .2s; }
    .mod-tab.active { color:var(--warning); border-bottom:2px solid var(--warning); }
    .mod-pane       { display:none; }
    .mod-pane.active{ display:block; }
    .mod-member-row { display:flex; align-items:center; gap:10px; padding:10px 14px; border-bottom:1px solid rgba(255,255,255,.04); }
    .mod-member-row:last-child { border-bottom:none; }
    .mod-role-badge { font-size:10px; padding:2px 7px; border-radius:10px; font-weight:600; flex-shrink:0; }
    .mod-role-badge.moderator { background:rgba(245,158,11,.2); color:var(--warning); }
    .mod-role-badge.member    { background:rgba(255,255,255,.08); color:var(--text-muted); }
    .mod-action-btn { padding:4px 10px; font-size:11px; font-weight:600; border-radius:6px; cursor:pointer; border:none; font-family:var(--font-body); }
    .mod-suspend-btn{ background:rgba(255,79,106,.15); color:var(--danger); border:1px solid rgba(255,79,106,.3); }
    .mod-unsuspend-btn { background:rgba(62,207,142,.15); color:var(--success); border:1px solid rgba(62,207,142,.3); }
    .mod-note       { font-size:11px; color:var(--text-muted); padding:10px 14px; }

    /* ── post file attachment ── */
    .post-file-row {
      margin:0 16px 12px; padding:10px 14px;
      background:rgba(255,255,255,.04); border:1px solid var(--card-border);
      border-radius:8px; display:flex; align-items:center; gap:10px; font-size:13px;
    }
    .post-file-row a { margin-left:auto; color:var(--accent); text-decoration:none; font-size:12px; font-weight:600; }
    .post-file-row a:hover { text-decoration:underline; }

    /* ── FAB ── */
    .post-fab {
      position:fixed; bottom:28px; right:28px;
      width:52px; height:52px; border-radius:50%;
      background:linear-gradient(135deg,var(--accent),var(--accent2));
      border:none; cursor:pointer; font-size:22px; color:#fff;
      box-shadow:0 4px 20px rgba(79,142,247,.4);
      display:flex; align-items:center; justify-content:center;
      transition:transform .2s,box-shadow .2s; z-index:500; text-decoration:none;
    }
    .post-fab:hover { transform:scale(1.1); box-shadow:0 6px 28px rgba(79,142,247,.6); }

    /* ── mod suspend modal ── */
    .modal-backdrop {
      display:none; position:fixed; inset:0; background:rgba(0,0,0,.6);
      z-index:2000; align-items:center; justify-content:center;
    }
    .modal-backdrop.open { display:flex; }
    .modal-box {
      background:#1e1e35; border:1px solid var(--card-border);
      border-radius:16px; padding:28px; max-width:420px; width:90%;
    }
    .modal-box h3 { font-family:var(--font-display); font-size:18px; font-weight:700; margin-bottom:8px; }
    .modal-box p  { font-size:13px; color:var(--text-muted); margin-bottom:16px; }
  </style>
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div>

<!-- NAVBAR -->
<header class="navbar">
  <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-search">
    <input type="text" placeholder="Search anything…" onclick="location.href='search.php'" readonly>
  </div>
  <div class="nav-right">
    <a href="dashboard.php" style="font-size:13px;color:var(--text-muted);text-decoration:none;">← Feed</a>
  </div>
</header>

<!-- SUSPEND MODAL -->
<div class="modal-backdrop" id="suspendModal">
  <div class="modal-box">
    <h3>🚫 Suspend from Sub</h3>
    <p>Suspend <strong id="suspendUsername"></strong> from <strong><?php echo htmlspecialchars($sub['name']); ?></strong>?<br>
    <span style="font-size:12px;">This only removes them from this community. For a site-wide ban, contact an admin.</span></p>
    <form method="POST" action="sub.php?id=<?php echo $sub_id; ?>">
      <input type="hidden" name="mod_action" value="sub_suspend">
      <input type="hidden" name="target_uid" id="suspendTargetUid">
      <div class="form-group">
        <label>Reason <span class="optional-badge">optional</span></label>
        <input type="text" name="mod_reason" placeholder="e.g. Repeated spam">
      </div>
      <div style="display:flex;gap:10px;margin-top:16px;">
        <button type="submit" class="btn btn-primary" style="flex:1;">Confirm Suspend</button>
        <button type="button" onclick="closeModal()" class="btn btn-secondary" style="flex:1;margin-top:0;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<div class="page-wrapper" style="padding-top:58px;">

  <!-- BANNER -->
  <div class="sub-banner">
    <?php if (!empty($sub['banner_image']) && file_exists(__DIR__.'/'.$sub['banner_image'])): ?>
    <img src="<?php echo htmlspecialchars($sub['banner_image']); ?>" alt="banner">
    <?php endif; ?>
  </div>

  <div class="sub-layout">

    <!-- SUB HEADER -->
    <div class="sub-header-card">
      <div class="sub-icon-row">
        <div class="sub-big-icon">
          <?php if (!empty($sub['profile_photo']) && $sub['profile_photo'] !== 'default_sub.png' && file_exists(__DIR__.'/'.$sub['profile_photo'])): ?>
          <img src="<?php echo htmlspecialchars($sub['profile_photo']); ?>" alt="">
          <?php else: echo '🗂️'; endif; ?>
        </div>
        <div class="sub-actions">
          <?php if ($is_sub_banned): ?>
            <span style="font-size:13px;color:var(--danger);">🚫 Suspended from this sub</span>
          <?php elseif (!$is_member): ?>
            <form method="POST">
              <button type="submit" name="join" class="join-btn join">+ Join</button>
            </form>
          <?php elseif ($is_moderator): ?>
            <span style="font-size:12px;color:var(--warning);font-weight:700;">⭐ Moderator</span>
          <?php else: ?>
            <form method="POST">
              <button type="submit" name="leave" class="join-btn leave">Joined ✓</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <div class="sub-name"><?php echo htmlspecialchars($sub['name']); ?></div>
      <div class="sub-meta">
        <span>👥 <?php echo number_format($sub['member_count']); ?> members</span>
        <span>📌 <?php echo htmlspecialchars($sub['topic'] ?? 'General'); ?></span>
        <?php if (!empty($sub['creator_name'])): ?>
        <span>Created by <a href="profile.php?id=<?php echo $sub['creator_uid']; ?>"><?php echo htmlspecialchars($sub['creator_name']); ?></a></span>
        <?php endif; ?>
      </div>
    </div>

    <?php if (isset($_GET['created'])): ?>
    <div class="success-msg" style="margin-bottom:20px;">🎉 Community created! You are now the moderator.</div>
    <?php endif; ?>

    <?php if ($is_sub_banned): ?>
    <div class="sub-banned-notice">
      You have been suspended from this community.
      <?php if (!empty($ban_row['reason'])): ?>
      Reason: <em><?php echo htmlspecialchars($ban_row['reason']); ?></em>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- BODY -->
    <div class="sub-body">

      <!-- FEED -->
      <main>
        <?php if (($is_member || $is_admin) && !$is_sub_banned): ?>
        <a href="create_post.php?sub_id=<?php echo $sub_id; ?>" class="compose-prompt">
          <span style="font-size:20px;">✏️</span>
          Create a post in <?php echo htmlspecialchars($sub['name']); ?>…
        </a>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
        <div class="card card-body" style="text-align:center;padding:60px 20px;">
          <div style="font-size:48px;margin-bottom:14px;">📭</div>
          <h3 style="font-family:var(--font-display);margin-bottom:8px;">No posts yet</h3>
          <p style="color:var(--text-muted);font-size:14px;">Be the first to post something here!</p>
        </div>
        <?php else: ?>
        <?php foreach ($posts as $p): ?>
        <div class="card post-card" style="position:relative;">
          <div class="post-header">
            <div class="post-avatar"><?php echo ava($p['author_photo'],$p['author'],32); ?></div>
            <div class="post-meta">
              <a href="profile.php?id=<?php echo $p['user_id']; ?>" style="color:var(--text-muted);text-decoration:none;font-weight:600;"><?php echo htmlspecialchars($p['author']); ?></a>
              <?php if ($p['user_id'] == $sub['creator_uid']): ?>
                <span style="font-size:10px;background:rgba(245,158,11,.15);color:var(--warning);padding:2px 6px;border-radius:8px;margin-left:4px;">MOD</span>
              <?php endif; ?>
              &nbsp;•&nbsp; <?php echo timeAgo($p['created_at']); ?>
            </div>
            <button class="post-more" onclick="toggleMenu('pm<?php echo $p['id']; ?>')">⋯</button>
            <!-- Post dropdown menu -->
            <div id="pm<?php echo $p['id']; ?>" style="display:none;position:absolute;top:44px;right:16px;background:#1e1e35;border:1px solid var(--card-border);border-radius:10px;min-width:160px;padding:6px 0;z-index:50;box-shadow:0 4px 20px rgba(0,0,0,.4);">
              <?php if ($user_id != $p['user_id']): ?>
              <a href="#" onclick="reportPost(<?php echo $p['id']; ?>);return false;"
                 style="display:block;padding:8px 14px;font-size:13px;color:var(--danger);text-decoration:none;">🚩 Report</a>
              <?php endif; ?>
              <?php if ($is_moderator || $is_admin): ?>
              <form method="POST" action="sub.php?id=<?php echo $sub_id; ?>" style="margin:0;">
                <input type="hidden" name="mod_action" value="remove_post">
                <input type="hidden" name="post_id" value="<?php echo $p['id']; ?>">
                <button type="submit" style="display:block;width:100%;padding:8px 14px;font-size:13px;color:var(--danger);background:none;border:none;cursor:pointer;text-align:left;font-family:var(--font-body);">
                  🗑 Remove Post
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>

          <a href="post.php?id=<?php echo $p['id']; ?>" style="text-decoration:none;display:block;">
            <div class="post-title"><?php echo htmlspecialchars($p['title']); ?></div>
            <?php if (!empty($p['content'])): ?>
            <div class="post-snippet">
              <?php
              $plain = strip_tags($p['content']);
              echo htmlspecialchars(mb_substr($plain,0,220)) . (mb_strlen($plain)>220?'…':'');
              ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($p['image_url'])): ?>
            <img src="<?php echo htmlspecialchars($p['image_url']); ?>" class="post-image" alt="">
            <?php endif; ?>
            <?php if (!empty($p['link_url'])): ?>
            <div style="padding:4px 16px 10px;font-size:12px;color:var(--accent);">
              🔗 <?php echo htmlspecialchars($p['link_url']); ?>
            </div>
            <?php endif; ?>
          </a>

          <?php if (!empty($p['file_url'])): ?>
          <div class="post-file-row">
            <span><?php echo fileIcon(pathinfo($p['file_url'],PATHINFO_EXTENSION)); ?></span>
            <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;">
              <?php echo htmlspecialchars(basename($p['file_url'])); ?>
            </span>
            <a href="<?php echo htmlspecialchars($p['file_url']); ?>" download>⬇ Download</a>
          </div>
          <?php endif; ?>

          <div class="post-actions">
            <div class="vote-group">
              <button class="vote-btn upvote"   onclick="vote(<?php echo $p['id']; ?>,'up',this)">▲</button>
              <span class="vote-count" id="vc-<?php echo $p['id']; ?>"><?php echo number_format($p['upvotes']); ?></span>
              <button class="vote-btn downvote" onclick="vote(<?php echo $p['id']; ?>,'down',this)">▼</button>
            </div>
            <a href="post.php?id=<?php echo $p['id']; ?>" class="action-btn">
              💬 <?php echo $p['comment_count']; ?> Comments
            </a>
            <button class="action-btn" onclick="copyPostLink(<?php echo $p['id']; ?>)">↗ Share</button>
            <?php if (($is_moderator || $is_admin) && $p['user_id'] != $user_id): ?>
            <button class="action-btn" style="color:var(--warning);margin-left:auto;"
                    onclick="openSuspendModal(<?php echo $p['user_id']; ?>,'<?php echo htmlspecialchars($p['author'],ENT_QUOTES); ?>')">
              ⚠️ Suspend User
            </button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </main>

      <!-- SIDEBAR -->
      <aside class="sub-sidebar">

        <!-- About -->
        <div class="sub-info-card">
          <div class="sub-info-hdr">About</div>
          <div class="sub-info-body">
            <?php echo !empty($sub['description']) ? htmlspecialchars($sub['description']) : 'No description yet.'; ?>
          </div>
          <div class="sub-stat-row"><span>Members</span><span class="sub-stat-val"><?php echo number_format($sub['member_count']); ?></span></div>
          <div class="sub-stat-row"><span>Topic</span><span class="sub-stat-val"><?php echo htmlspecialchars($sub['topic']??'General'); ?></span></div>
          <div class="sub-stat-row"><span>Created</span><span class="sub-stat-val"><?php echo date('M Y',strtotime($sub['created_at'])); ?></span></div>
        </div>

        <!-- Rules -->
        <?php if (!empty($sub['rules'])): ?>
        <div class="sub-info-card">
          <div class="sub-info-hdr">Rules</div>
          <ul class="rules-list">
            <?php foreach(array_filter(explode("\n", trim($sub['rules']))) as $i=>$rule): ?>
            <li><span class="rules-num"><?php echo $i+1; ?>.</span><?php echo htmlspecialchars(trim($rule)); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <!-- Mod panel -->
        <?php if ($is_moderator || $is_admin): ?>
        <div class="mod-panel">
          <div class="mod-panel-hdr">⭐ Moderator Panel</div>
          <div class="mod-tabs">
            <button class="mod-tab active" onclick="switchModTab('members',this)">Members</button>
            <button class="mod-tab" onclick="switchModTab('suspended',this)">
              Suspended <?php echo count($banned_list)>0?'('.count($banned_list).')':''; ?>
            </button>
          </div>

          <!-- Members tab -->
          <div class="mod-pane active" id="modpane-members">
            <?php if ($members_res): while ($m = mysqli_fetch_assoc($members_res)): ?>
            <div class="mod-member-row">
              <div style="font-size:13px;font-weight:600;flex:1;"><?php echo htmlspecialchars($m['username']); ?></div>
              <span class="mod-role-badge <?php echo $m['role']; ?>"><?php echo $m['role']; ?></span>
              <?php if ($m['id'] != $user_id && $m['role'] !== 'moderator'): ?>
              <button class="mod-action-btn mod-suspend-btn"
                      onclick="openSuspendModal(<?php echo $m['id']; ?>,'<?php echo htmlspecialchars($m['username'],ENT_QUOTES); ?>')">
                Suspend
              </button>
              <?php endif; ?>
            </div>
            <?php endwhile; endif; ?>
            <div class="mod-note">Moderator powers are limited to this sub only. For site-wide actions, use the Admin Dashboard.</div>
          </div>

          <!-- Suspended tab -->
          <div class="mod-pane" id="modpane-suspended">
            <?php if (empty($banned_list)): ?>
            <div class="mod-note">No suspended users.</div>
            <?php else: ?>
            <?php foreach ($banned_list as $b): ?>
            <div class="mod-member-row">
              <div style="flex:1;">
                <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($b['username']); ?></div>
                <?php if (!empty($b['reason'])): ?>
                <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($b['reason']); ?></div>
                <?php endif; ?>
              </div>
              <form method="POST" action="sub.php?id=<?php echo $sub_id; ?>">
                <input type="hidden" name="mod_action" value="sub_unsuspend">
                <input type="hidden" name="target_uid" value="<?php echo $b['user_id']; ?>">
                <button type="submit" class="mod-action-btn mod-unsuspend-btn">Unsuspend</button>
              </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

      </aside>
    </div>
  </div>
</div>

<!-- FAB — only for members/admin who aren't sub-banned -->
<?php if (($is_member || $is_admin) && !$is_sub_banned): ?>
<a href="create_post.php?sub_id=<?php echo $sub_id; ?>" class="post-fab" title="Create post">✏️</a>
<?php endif; ?>

<script>
/* post dropdown */
function toggleMenu(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', e => {
  if (!e.target.closest('.post-more') && !e.target.closest('[id^="pm"]'))
    document.querySelectorAll('[id^="pm"]').forEach(m => m.style.display='none');
});

/* mod tabs */
function switchModTab(name, btn) {
  document.querySelectorAll('.mod-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.mod-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('modpane-'+name).classList.add('active');
  btn.classList.add('active');
}

/* suspend modal */
function openSuspendModal(uid, username) {
  document.getElementById('suspendTargetUid').value = uid;
  document.getElementById('suspendUsername').textContent = username;
  document.getElementById('suspendModal').classList.add('open');
}
function closeModal() {
  document.getElementById('suspendModal').classList.remove('open');
}
document.getElementById('suspendModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

/* vote */
function vote(id, dir, btn) {
  fetch('vote.php?post_id='+id+'&dir='+dir)
    .then(r=>r.json()).then(d=>{
      if (d.votes !== undefined) document.getElementById('vc-'+id).textContent = d.votes;
    });
}

/* share */
function copyPostLink(id) {
  const base = location.origin + location.pathname.replace('sub.php','');
  navigator.clipboard.writeText(base + 'post.php?id=' + id).then(()=>alert('Link copied!'));
}

/* report */
function reportPost(id) {
  const reason = prompt('Reason for reporting this post:');
  if (!reason) return;
  fetch('report.php?type=post&id='+id+'&reason='+encodeURIComponent(reason))
    .then(r=>r.json()).then(d=>alert(d.message||'Reported.'));
}
</script>
</body>
</html>
