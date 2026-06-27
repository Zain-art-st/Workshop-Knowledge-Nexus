<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$user_type = $_SESSION['user_type'];

$urow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT profile_photo FROM users WHERE id=$user_id LIMIT 1"));
$profile_photo = $urow['profile_photo'] ?? 'default.png';

// Recent visits
$visits_res    = mysqli_query($conn,
    "SELECT s.id, s.name, s.description FROM recent_visits rv
     JOIN subcommunities s ON rv.sub_id=s.id
     WHERE rv.user_id=$user_id ORDER BY rv.visited_at DESC LIMIT 5");
$recent_visits = mysqli_fetch_all($visits_res, MYSQLI_ASSOC);
if (empty($recent_visits)) {
    $recent_visits = mysqli_fetch_all(mysqli_query($conn,
        "SELECT id, name, description FROM subcommunities LIMIT 5"), MYSQLI_ASSOC);
}

// Posts feed
$posts_res = mysqli_query(
  $conn,
  "SELECT p.*, u.username AS author, u.profile_photo AS author_photo,
            s.name AS sub_name, s.id AS sub_id
     FROM posts p
     JOIN users u ON p.user_id=u.id
     JOIN subcommunities s ON p.sub_id=s.id
     WHERE p.is_removed=0
     ORDER BY p.created_at DESC
     LIMIT 20"
);
$posts = mysqli_fetch_all($posts_res, MYSQLI_ASSOC);

// Detect current user's vote state
foreach ($posts as &$p) {
  $up_users = json_decode($p['upvote_users'] ?? '[]', true);
  $down_users = json_decode($p['downvote_users'] ?? '[]', true);

  $up_users = is_array($up_users) ? $up_users : [];
  $down_users = is_array($down_users) ? $down_users : [];

  $p['user_upvoted'] = in_array($user_id, $up_users);
  $p['user_downvoted'] = in_array($user_id, $down_users);
}
unset($p);

$sub_icons = ['🖥️','📢','🐱','🔍','🤖','🧠','🔬','📚','🎮','💬','🎨','🌍','🏋️','🎵','📸'];
function subIcon($n,$i){return $i[abs(crc32($n))%count($i)];}
function timeAgo(string $d): string {
    $t = strtotime($d);
    if (!$t || $t > time()) return 'just now';
    $s = time() - $t;
    if ($s < 60)      return $s . 's ago';
    if ($s < 3600)    return floor($s/60) . 'm ago';
    if ($s < 86400)   return floor($s/3600) . 'h ago';
    if ($s < 604800)  return floor($s/86400) . 'd ago';
    if ($s < 2592000) return floor($s/604800) . 'w ago';
    return date('M j, Y', $t);
}
function ava($photo,$name,$size=32){
    $init=strtoupper(substr($name,0,1));
    $fs=round($size*.45);
    if($photo&&$photo!=='default.png'&&file_exists(__DIR__.'/'.$photo))
        return "<img src='$photo' alt='".htmlspecialchars($name)."' style='width:{$size}px;height:{$size}px;border-radius:50%;object-fit:cover;display:block;'>";
    return "<span style='font-size:{$fs}px;font-weight:700;color:#fff;'>$init</span>";
}

function fileIcon($ext)
{
  $m = ['pdf' => '📕', 'doc' => '📘', 'docx' => '📘', 'ppt' => '📙', 'pptx' => '📙', 'xls' => '📗', 'xlsx' => '📗', 'txt' => '📄'];
  return $m[strtolower($ext)] ?? '📄';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div>

<!--sliding sidebar overlay-->
<div class="slide-overlay" id="slideOverlay" onclick="closeSidebar()"></div>
<aside class="slide-sidebar" id="slideSidebar">
  <div class="slide-header">
    <div class="slide-user-info">
      <div class="slide-avatar"><?php echo ava($profile_photo,$username,48); ?></div>
      <div>
        <div class="slide-username"><?php echo htmlspecialchars($username); ?></div>
        <div class="slide-userbadge">
          <?php if($user_type==='admin') echo '⚙️ Administrator';
                elseif($user_type==='student') echo '🎓 Verified Student';
                else echo '💼 Graduate'; ?>
        </div>
      </div>
    </div>
    <button class="slide-close" onclick="closeSidebar()">✕</button>
  </div>
  <nav class="slide-nav">
    <a href="profile.php?id=<?php echo $user_id; ?>" class="slide-item"><span class="slide-icon">👤</span>Edit Profile</a>
    <a href="my_posts.php" class="slide-item"><span class="slide-icon">📝</span>Your Posts</a>
    <a href="notifications.php" class="slide-item"><span class="slide-icon">🔔</span>Notifications</a>
    <a href="about.php" class="slide-item"><span class="slide-icon">ℹ️</span>About</a>
    <a href="create_sub.php" class="slide-item"><span class="slide-icon">➕</span>Create your own Sub!</a>
    <button onclick="openDocModal(); closeSidebar();" class="slide-item" style="background:none;border:none;width:100%;text-align:left;cursor:pointer;font-family:var(--font-body);">
      <span class="slide-icon">📄</span>Create a Document
    </button>
    <?php if($user_type==='admin'): ?>
    <div class="slide-divider"></div>
    <a href="admin.php" class="slide-item slide-admin"><span class="slide-icon">⚙️</span>Administrator Dashboard</a>
    <?php endif; ?>
    <div class="slide-divider"></div>
    <a href="logout.php" class="slide-item slide-logout"><span class="slide-icon">🚪</span>Log Out</a>
  </nav>
</aside>

<header class="navbar">
  <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-search">
    <input type="text" placeholder="Search anything…" id="navSearchInput" onclick="openSearch()" readonly>
  </div>
  <div class="nav-right">
    <span class="nav-welcome">Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
    <div class="profile-avatar" onclick="openSidebar()" style="cursor:pointer;">
      <?php echo ava($profile_photo,$username,36); ?>
    </div>
  </div>
</header>

<!--search overla-->
<div class="search-overlay" id="searchOverlay">
  <div class="search-overlay-inner">
    <div class="search-overlay-bar">
      <span class="search-overlay-icon">🔍</span>
      <input type="text" id="searchOverlayInput"
             placeholder="Search communities, posts, people…"
             oninput="liveSearch(this.value)">
      <button class="search-overlay-close" onclick="closeSearch()">✕</button>
    </div>
    <div id="searchResults">
      <div class="search-empty">
        <div class="search-empty-logo">ScholarSpace</div>
        <div class="search-empty-quote">Not so active eh?</div>
      </div>
    </div>
  </div>
</div>

<!--page-->
<div class="page-wrapper">
  <div class="layout-container">

    <!--feed-->
    <main>
      <div class="welcome-banner card" style="margin-bottom:20px;">
        <div>
          <h2>Welcome back, <?php echo htmlspecialchars($username); ?>! 👋</h2>
          <p>Here's what's happening in your communities.</p>
        </div>
        <span class="status-badge <?php echo $user_type; ?>">
          <?php if($user_type==='student') echo 'Student';
                elseif($user_type==='admin') echo 'Admin';
                else echo 'Graduate'; ?>
        </span>
      </div>

      <?php if(empty($posts)): ?>
      <div class="card card-body" style="text-align:center;padding:60px 20px;">
        <div style="font-size:48px;margin-bottom:16px;">📭</div>
        <h3 style="font-family:var(--font-display);margin-bottom:8px;">Nothing here yet</h3>
        <p style="color:var(--text-muted);font-size:14px;">Be the first to post in a community!</p>
      </div>
      <?php else: ?>
      <?php foreach($posts as $p): ?>
        <?php
          // Get total comments (parent + child comments)
          $total_comments_query = " SELECT COUNT(*) AS total FROM comments
          WHERE post_id = {$p['id']} AND is_removed = 0";

          $total_comments = mysqli_fetch_assoc(mysqli_query($conn, $total_comments_query))['total'];
          ?>
      <div class="card post-card">
        <div class="post-header">
          <div class="post-avatar"><?php echo ava($p['author_photo'],$p['author'],32); ?></div>
          <div class="post-meta">
            <a href="sub.php?id=<?php echo $p['sub_id']; ?>" class="sub-link"><?php echo htmlspecialchars($p['sub_name']); ?></a>
            &nbsp;•&nbsp;
            <a href="profile.php?id=<?php echo $p['user_id']; ?>" style="color:var(--text-muted);text-decoration:none;"><?php echo htmlspecialchars($p['author']); ?></a>
            &nbsp;•&nbsp;<?php echo timeAgo($p['created_at']); ?>
          </div>
          <button class="post-more" onclick="togglePostMenu('dpm<?php echo $p['id']; ?>', event)">⋯</button>
            <div class="post-more-menu" id="dpm<?php echo $p['id']; ?>">
              <?php if ($user_id != $p['user_id']): ?>
              <a href="#" onclick="reportPost(<?php echo $p['id']; ?>);return false;" class="danger">🚩 Report Post</a>
              <?php endif; ?>
              <?php if ($user_id == $p['user_id']): ?>
              <a href="#" onclick="deletePost(<?php echo $p['id']; ?>);return false;" class="danger">🗑 Delete Post</a>
              <?php endif; ?>
              <?php if ($user_type === 'admin' && $user_id != $p['user_id']): ?>
              <button class="danger" onclick="adminRemovePost(<?php echo $p['id']; ?>)">🗑 Remove Post</button>
              <?php endif; ?>
            </div>
        </div>
        <div class="post-title"><?php echo htmlspecialchars($p['title']); ?></div>
        <?php if(!empty($p['content'])): ?>
          <?php
            $contentParts = explode("\n\n", strip_tags($p['content']));
            $preview = $contentParts[0] ?? '';
            ?>
          <div class="post-snippet">
            <?php echo htmlspecialchars(mb_substr($preview, 0, 220)) ?>
            <?php echo mb_strlen($preview) > 220 ? '…' : '' ?>
          </div>
        <?php endif; ?>
        <?php if(!empty($p['image_url'])): ?>
        <img src="<?php echo htmlspecialchars($p['image_url']); ?>" class="post-image" alt="">
        <?php endif; ?>
        <?php if(!empty($p['link_url'])): ?>
        <a href="<?php echo htmlspecialchars($p['link_url']); ?>" target="_blank"
           style="display:block;padding:4px 16px 12px;font-size:12px;color:var(--accent);">
          🔗 <?php echo htmlspecialchars($p['link_url']); ?>
        </a>
        <?php endif; ?>

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
            <button
            class="vote-btn upvote <?php echo $p['user_upvoted'] ? 'active' : ''; ?>"
            onclick="vote(<?php echo $p['id']; ?>,'up',this)">▲</button>
            <?php
              $vote_score = $p['upvotes'] - $p['downvotes'];
            ?>
            <span class="vote-count" id="vc-<?php echo $p['id']; ?>"><?php echo number_format($vote_score); ?></span>
            <button
            class="vote-btn downvote <?php echo $p['user_downvoted'] ? 'active' : ''; ?>"
            onclick="vote(<?php echo $p['id']; ?>,'down',this)">▼</button>
          </div>
          <a href="post.php?id=<?php echo $p['id']; ?>" class="action-btn">💬 <?php echo $total_comments; ?> Comments</a>
          <button class="action-btn" onclick="copyLink(<?php echo $p['id']; ?>)">↗ Share</button>
          <?php if($user_id!=$p['user_id']): ?>
          <button class="action-btn" style="margin-left:auto;"
                  onclick="reportPost(<?php echo $p['id']; ?>)">🚩 Report</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </main>

    <!--right handed side-->
    <aside class="right-sidebar">
      <div class="sidebar-card">
        <div class="sidebar-card-header">Recently Visited</div>
        <?php foreach($recent_visits as $sub): ?>
        <a href="sub.php?id=<?php echo $sub['id']; ?>" class="sub-item">
          <div class="sub-item-icon"><?php echo subIcon($sub['name'],$sub_icons); ?></div>
          <div class="sub-item-info">
            <div class="sub-item-name"><?php echo htmlspecialchars($sub['name']); ?></div>
            <div class="sub-item-desc"><?php echo htmlspecialchars($sub['description']??''); ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <div class="sidebar-card">
        <div class="sidebar-card-header">Your Profile</div>
        <div style="padding:16px;">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
              <?php echo ava($profile_photo,$username,44); ?>
            </div>
            <div>
              <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($username); ?></div>
              <div style="font-size:11px;color:var(--text-muted);">
                <?php echo $user_type==='student'?'🎓 Student':($user_type==='admin'?'⚙️ Admin':'💼 Graduate'); ?>
              </div>
            </div>
          </div>
          <a href="profile.php?id=<?php echo $user_id; ?>"
             style="display:block;text-align:center;padding:8px;background:rgba(79,142,247,.12);border:1px solid rgba(79,142,247,.25);border-radius:8px;font-size:13px;color:var(--accent);text-decoration:none;">
            View Full Profile
          </a>
        </div>
      </div>
    </aside>

  </div>
</div>

<!--doc modal-->
<div id="docModal" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(10,10,20,.92);backdrop-filter:blur(8px);flex-direction:column;">

  <!-- Doc topbar -->
  <div id="docTopbar" style="display:flex;align-items:center;gap:10px;padding:8px 16px;background:#1e1c35;border-bottom:1px solid rgba(255,255,255,.1);height:52px;flex-shrink:0;">

    <!-- Title -->
    <input type="text" id="docTitle" placeholder="Untitled Document"
           style="flex:1;background:none;border:none;outline:none;color:#f0eff5;font-family:'Sora',sans-serif;font-size:16px;font-weight:700;min-width:0;">

    <!-- Status -->
    <span id="docSaveStatus" style="font-size:11px;color:#9b9ab0;white-space:nowrap;"></span>

    <!-- Post to sub button -->
    <div style="position:relative;">
      <button onclick="toggleDocPanel('postSubPanel')"
              style="padding:6px 14px;background:rgba(79,142,247,.15);border:1px solid rgba(79,142,247,.3);border-radius:8px;color:#4f8ef7;font-size:13px;font-weight:600;cursor:pointer;font-family:'Sora',sans-serif;">
        Post to Sub
      </button>
      <div id="postSubPanel" style="display:none;position:absolute;top:38px;right:0;background:#1e1c35;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;width:260px;z-index:10;box-shadow:0 8px 32px rgba(0,0,0,.5);">
        <div style="font-family:'Sora',sans-serif;font-size:14px;font-weight:700;margin-bottom:10px;">Post to Community</div>
        <p style="font-size:12px;color:#9b9ab0;margin-bottom:12px;">This will create a post linking to your document.</p>
        <select id="docSubSelect"
                style="width:100%;padding:8px 12px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:8px;color:#f0eff5;font-family:'DM Sans',sans-serif;font-size:13px;outline:none;margin-bottom:10px;">
          <option value="">Choose community…</option>
          <?php
          $subs_for_doc = mysqli_query($conn,
              "SELECT s.id, s.name FROM subcommunities s
               JOIN sub_memberships sm ON s.id=sm.sub_id
               WHERE sm.user_id=$user_id ORDER BY s.name ASC");
          while ($sd = mysqli_fetch_assoc($subs_for_doc)):
          ?>
          <option value="<?php echo $sd['id']; ?>"><?php echo htmlspecialchars($sd['name']); ?></option>
          <?php endwhile; ?>
        </select>
        <button onclick="postDocToSub()"
                style="width:100%;padding:9px;background:linear-gradient(135deg,#4f8ef7,#c06de8);border:none;border-radius:8px;color:#fff;font-family:'Sora',sans-serif;font-size:14px;font-weight:700;cursor:pointer;">
          Post
        </button>
      </div>
    </div>

    <!-- Share button -->
    <div style="position:relative;">
      <button onclick="openSharePanel()"
              style="padding:6px 14px;background:rgba(192,109,232,.15);border:1px solid rgba(192,109,232,.3);border-radius:8px;color:#c06de8;font-size:13px;font-weight:600;cursor:pointer;font-family:'Sora',sans-serif;">
        🔗 Share
      </button>
      <div id="shareDocPanel" style="display:none;position:absolute;top:38px;right:0;background:#1e1c35;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;width:280px;z-index:10;box-shadow:0 8px 32px rgba(0,0,0,.5);">
        <div style="font-family:'Sora',sans-serif;font-size:14px;font-weight:700;margin-bottom:6px;">Share Document</div>
        <p style="font-size:12px;color:#9b9ab0;margin-bottom:12px;">Enter a username to give them access to this document.</p>
        <input type="text" id="shareUsername" placeholder="Enter username…"
               style="width:100%;box-sizing:border-box;padding:8px 12px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:8px;color:#f0eff5;font-family:'DM Sans',sans-serif;font-size:13px;outline:none;margin-bottom:10px;">
        <div id="sharePermRow" style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
          <label style="font-size:12px;color:#9b9ab0;font-family:'DM Sans',sans-serif;">Permission:</label>
          <select id="sharePermission"
                  style="flex:1;padding:6px 10px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:8px;color:#f0eff5;font-family:'DM Sans',sans-serif;font-size:12px;outline:none;">
            <option value="view">View only</option>
            <option value="edit">Can edit</option>
          </select>
        </div>
        <button onclick="shareDoc()"
                style="width:100%;padding:9px;background:linear-gradient(135deg,#c06de8,#4f8ef7);border:none;border-radius:8px;color:#fff;font-family:'Sora',sans-serif;font-size:14px;font-weight:700;cursor:pointer;">
          Share
        </button>
        <div id="shareStatus" style="margin-top:8px;font-size:12px;text-align:center;min-height:16px;"></div>
      </div>
    </div>

    <!-- Export PDF -->
    <button onclick="exportDocPDF()"
            style="padding:6px 14px;background:rgba(62,207,142,.12);border:1px solid rgba(62,207,142,.3);border-radius:8px;color:#3ecf8e;font-size:13px;font-weight:600;cursor:pointer;font-family:'Sora',sans-serif;">
      Export PDF
    </button>

    <!-- Close -->
    <button onclick="closeDocModal()"
            style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.08);border:none;color:#9b9ab0;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .2s;"
            onmouseover="this.style.background='rgba(255,79,106,.2)';this.style.color='#ff4f6a';"
            onmouseout="this.style.background='rgba(255,255,255,.08)';this.style.color='#9b9ab0';">
      ✕
    </button>
  </div>

  <!--canvas-->
  <div style="flex:1;overflow-y:auto;background:#e8e8e8;padding:32px 20px;" id="docPageWrap">
    <div id="docPage"
         style="width:100%;max-width:800px;min-height:1056px;margin:0 auto;background:#fff;border-radius:4px;box-shadow:0 4px 24px rgba(0,0,0,.25);overflow:hidden;">
      <div id="docQuillEditor" style="min-height:1056px;font-size:15px;line-height:1.8;"></div>
    </div>
  </div>
</div>

<!--Print styles-->
<style>
  @media print {
    body > *:not(#docPrintArea) { display:none !important; }
    #docPrintArea {
      display:block !important; position:static !important;
      width:100%; background:#fff; color:#000;
    }
    #docPrintArea h1 { font-size:22px; margin-bottom:16px; font-family:serif; }
    #docPrintTitle { font-size:22px; font-weight:bold; margin-bottom:20px; font-family:serif; }
  }

  .vote-btn.upvote.active {
    color: #3ecf8e;
}

.vote-btn.downvote.active {
    color: #ff4f6a ;
}
</style>

<div id="docPrintArea" style="display:none;">
  <div id="docPrintTitle"></div>
  <div id="docPrintContent"></div>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
// Sidebar plsu existing side
function openSidebar(){document.getElementById('slideSidebar').classList.add('open');document.getElementById('slideOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('slideSidebar').classList.remove('open');document.getElementById('slideOverlay').classList.remove('open');document.body.style.overflow='';}
function openSearch(){document.getElementById('searchOverlay').classList.add('open');document.body.style.overflow='hidden';setTimeout(()=>document.getElementById('searchOverlayInput').focus(),80);}
function closeSearch(){document.getElementById('searchOverlay').classList.remove('open');document.body.style.overflow='';}
document.addEventListener('keydown',e=>{
  if(e.key==='Escape'){
    closeSidebar(); closeSearch();
    if(document.getElementById('docModal').style.display!=='none') closeDocModal();
  }
});

// 3 dot 
function togglePostMenu(id, e) {
  e.stopPropagation();
  const menu = document.getElementById(id);
  const isOpen = menu.classList.contains('open');
  document.querySelectorAll('.post-more-menu').forEach(m => m.classList.remove('open'));
  if (!isOpen) menu.classList.add('open');
}
document.addEventListener('click', () => {
  document.querySelectorAll('.post-more-menu').forEach(m => m.classList.remove('open'));
});

//Live search
function liveSearch(q){
  const r=document.getElementById('searchResults');
  if(!q.trim()){r.innerHTML='<div class="search-empty"><div class="search-empty-logo">ScholarSpace</div><div class="search-empty-quote">Not so active eh?</div></div>';return;}
  fetch('search_api.php?q='+encodeURIComponent(q))
    .then(x=>x.json()).then(data=>{
      if(!data.length){r.innerHTML=`<div class="search-empty"><div class="search-empty-logo">ScholarSpace</div><div class="search-empty-quote">No results for "${q}"</div></div>`;return;}
      r.innerHTML=data.map(i=>`<a href="${i.url}" class="search-result-item"><div class="search-result-icon">${i.icon}</div><div><div class="search-result-title">${i.title}</div><div class="search-result-sub">${i.subtitle}</div></div></a>`).join('');
    }).catch(()=>{});
}

//Vote n everything else
function vote(id, dir, btn) {
  fetch('vote.php?post_id=' + id + '&dir=' + dir)
    .then(r => r.json())
    .then(d => {

      if (d.error) {
        alert(d.error);
        return;
      }

      // Update score
      document.getElementById('vc-' + id).textContent =
        d.upvotes - d.downvotes;

      const group = btn.closest('.vote-group');

      group.querySelectorAll('.vote-btn')
        .forEach(b => b.classList.remove('active'));

      if (d.user_vote === 'up') {
        group.querySelector('.upvote')
          .classList.add('active');
      }

      if (d.user_vote === 'down') {
        group.querySelector('.downvote')
          .classList.add('active');
      }
    })
    .catch(err => {
      console.log(err);
      alert('Vote failed.');
    });
}
function copyLink(id){navigator.clipboard.writeText(location.origin+location.pathname.replace('dashboard.php','')+'post.php?id='+id).then(()=>alert('Link copied!'));}
function reportPost(id){const reason=prompt('Reason for reporting:');if(!reason)return;fetch('report.php?type=post&id='+id+'&reason='+encodeURIComponent(reason)).then(r=>r.json()).then(d=>alert(d.message||'Reported.'));}

function deletePost(id) {
  if (!confirm('Delete this post? This cannot be undone.')) return;
  fetch('delete_post.php?id='+id+'&action=delete')
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        // Remove card from DOM instantly
        const card = document.getElementById('dpm'+id)?.closest('.post-card');
        if (card) card.style.transition='opacity .3s', card.style.opacity='0',
          setTimeout(() => card.remove(), 300);
      } else {
        alert(data.error || 'Could not delete post.');
      }
    })
    .catch(() => alert('Network error. Please try again.'));
}

function adminRemovePost(id) {
  if (!confirm('Remove this post as admin? The post will be hidden from all users.')) return;
  fetch('delete_post.php?id='+id+'&action=remove')
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const card = document.getElementById('dpm'+id)?.closest('.post-card');
        if (card) card.style.transition='opacity .3s', card.style.opacity='0',
          setTimeout(() => card.remove(), 300);
      } else {
        alert(data.error || 'Could not remove post.');
      }
    })
    .catch(() => alert('Network error. Please try again.'));
}

//Document modal
let docQuill = null;
let docIsDirty = false;
let docCurrentId = null;

function openDocModal() {
  const modal = document.getElementById('docModal');
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';

  // Init Quill once
  if (!docQuill) {
    docQuill = new Quill('#docQuillEditor', {
      theme: 'snow',
      placeholder: 'Start writing your document…',
      modules: {
        toolbar: [
          [{ 'font': [] }, { 'size': ['small', false, 'large', 'huge'] }],
          [{ 'header': [1, 2, 3, 4, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ 'color': [] }, { 'background': [] }],
          [{ 'align': [] }],
          [{ 'list': 'ordered' }, { 'list': 'bullet' }],
          [{ 'indent': '-1' }, { 'indent': '+1' }],
          ['blockquote', 'code-block'],
          ['link'],
          ['clean']
        ]
      }
    });

    // Style Quill for white page
    const toolbar = document.querySelector('#docModal .ql-toolbar');
    const container = document.querySelector('#docModal .ql-container');
    const editor = document.querySelector('#docModal .ql-editor');
    if (toolbar) {
      toolbar.style.cssText = 'background:#f8f8f8;border-color:#ddd;border-bottom:1px solid #ddd;position:sticky;top:0;z-index:5;';
    }
    if (container) {
      container.style.cssText = 'border:none;font-size:15px;';
    }
    if (editor) {
      editor.style.cssText = 'color:#1a1a1a;font-size:15px;line-height:1.8;padding:40px 48px;min-height:1000px;';
    }

    docQuill.on('text-change', () => {
      docIsDirty = true;
      document.getElementById('docSaveStatus').textContent = '● Unsaved';
      document.getElementById('docSaveStatus').style.color = '#f59e0b';
    });
  }

  //Auto-save every 30s (can change)
  setInterval(autoSaveDoc, 30000);
}

function closeDocModal() {
  if (docIsDirty) {
    if (!confirm('You have unsaved changes. Close anyway?')) return;
  }
  document.getElementById('docModal').style.display = 'none';
  document.body.style.overflow = '';
  document.getElementById('postSubPanel').style.display = 'none';
}

function toggleDocPanel(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

//auto save to database
function autoSaveDoc() {
  if (!docIsDirty || !docQuill) return;
  saveDoc();
}

function saveDoc() {
  if (!docQuill) return;
  const title   = document.getElementById('docTitle').value || 'Untitled Document';
  const content = docQuill.root.innerHTML;
  const status  = document.getElementById('docSaveStatus');

  status.textContent = '⏳ Saving…';
  status.style.color = '#9b9ab0';

  fetch('save_document.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: docCurrentId, title, content })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      docCurrentId = data.doc_id;
      docIsDirty   = false;
      status.textContent = '✅ Saved';
      status.style.color = '#3ecf8e';
      setTimeout(() => { if (!docIsDirty) status.textContent = ''; }, 3000);
    } else {
      status.textContent = '❌ Save failed';
      status.style.color = '#ff4f6a';
    }
  })
  .catch(() => {
    status.textContent = '❌ Save failed';
    status.style.color = '#ff4f6a';
  });
}

//pdf export
function exportDocPDF() {
  if (!docQuill) return;
  const title   = document.getElementById('docTitle').value || 'Untitled Document';
  const content = docQuill.root.innerHTML;
  
  document.getElementById('docPrintTitle').textContent  = title;
  document.getElementById('docPrintContent').innerHTML  = content;
  document.getElementById('docPrintArea').style.display = 'block';

  // print dialog
  window.print();

  // Hide print area 
  setTimeout(() => {
    document.getElementById('docPrintArea').style.display = 'none';
  }, 1000);
}

//Post to sub
function postDocToSub() {
  if (!docQuill) return;
  const sub_id  = document.getElementById('docSubSelect').value;
  const title   = document.getElementById('docTitle').value || 'Untitled Document';
  const content = docQuill.root.innerHTML;

  if (!sub_id) { alert('Please choose a community first.'); return; }
  if (!docQuill.getText().trim()) { alert('Please write something first.'); return; }

  fetch('save_document.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: docCurrentId, title, content, post_to_sub: sub_id })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      docCurrentId = data.doc_id;
      docIsDirty   = false;
      document.getElementById('postSubPanel').style.display = 'none';
      alert('✅ Posted to community! Redirecting…');
      if (data.post_id) window.location.href = 'post.php?id=' + data.post_id;
      else location.reload();
    } else {
      alert('Failed to post: ' + (data.error || 'Unknown error'));
    }
  })
  .catch(() => alert('Network error. Please try again.'));
}

document.addEventListener('click', e => {
  if (!e.target.closest('#postSubPanel') && !e.target.closest('[onclick*="postSubPanel"]')) {
    const p = document.getElementById('postSubPanel');
    if (p) p.style.display = 'none';
  }
});

//Share document
async function openSharePanel() {
  // save it first to get a doc_id
  if (!docCurrentId) {
    if (!docQuill || !docQuill.getText().trim()) {
      alert('Please write something before sharing.');
      return;
    }
    await new Promise((resolve) => {
      const title   = document.getElementById('docTitle').value || 'Untitled Document';
      const content = docQuill.root.innerHTML;
      const status  = document.getElementById('docSaveStatus');
      status.textContent = '⏳ Saving…';
      status.style.color = '#9b9ab0';
      fetch('save_document.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: null, title, content })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          docCurrentId = data.doc_id;
          docIsDirty   = false;
          status.textContent = '✅ Saved';
          status.style.color = '#3ecf8e';
          setTimeout(() => { if (!docIsDirty) status.textContent = ''; }, 3000);
        } else {
          status.textContent = '❌ Save failed';
          status.style.color = '#ff4f6a';
        }
        resolve();
      })
      .catch(() => {
        status.textContent = '❌ Save failed';
        status.style.color = '#ff4f6a';
        resolve();
      });
    });

    if (!docCurrentId) {
      alert('Could not save the document. Please try again.');
      return;
    }
  }

  // Toggle shjare
  const panel = document.getElementById('shareDocPanel');
  panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
  if (panel.style.display === 'block') {
    document.getElementById('shareUsername').focus();
    document.getElementById('shareStatus').textContent = '';
  }
}

function shareDoc() {
  const username   = document.getElementById('shareUsername').value.trim();
  const permission = document.getElementById('sharePermission').value;
  const status     = document.getElementById('shareStatus');

  if (!username) { status.textContent = '⚠️ Enter a username.'; status.style.color = '#f59e0b'; return; }
  if (!docCurrentId) { status.textContent = '⚠️ Save the document first.'; status.style.color = '#f59e0b'; return; }

  status.textContent = 'Sharing…';
  status.style.color = '#9b9ab0';

  fetch('share_document.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ doc_id: docCurrentId, username, permission })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      status.textContent = '✅ Shared with ' + username + '!';
      status.style.color = '#3ecf8e';
      document.getElementById('shareUsername').value = '';
    } else {
      status.textContent = '❌ ' + (data.error || 'Could not share.');
      status.style.color = '#ff4f6a';
    }
  })
  .catch(() => {
    status.textContent = '❌ Network error.';
    status.style.color = '#ff4f6a';
  });
}

document.addEventListener('click', e => {
  if (!e.target.closest('#shareDocPanel') && !e.target.closest('[onclick*="openSharePanel"]')) {
    const p = document.getElementById('shareDocPanel');
    if (p) p.style.display = 'none';
  }
});

//warn dirty
window.addEventListener('beforeunload', e => {
  if (docIsDirty) { e.preventDefault(); e.returnValue = ''; }
});
</script>
</body></html>