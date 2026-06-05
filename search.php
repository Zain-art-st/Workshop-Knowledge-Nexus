<?php
session_start();
include "db.php";

//Auth check
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

$user_id = $_SESSION['user_id'];
$query = trim($_GET['q'] ?? '');

//Log search
if (!empty($query)) {
    $ins = mysqli_prepare($conn, "INSERT INTO recent_searches (user_id, query) VALUES (?, ?) ON DUPLICATE KEY UPDATE searched_at = NOW()");
    mysqli_stmt_bind_param($ins, "is", $user_id, $query);
    mysqli_stmt_execute($ins);
}

//Clear query item
if (isset($_POST['clear_search'])) {
    $q = $_POST['search_query'] ?? '';
    $del = mysqli_prepare($conn, "DELETE FROM recent_searches WHERE user_id = ? AND query = ?");
    mysqli_stmt_bind_param($del, "is", $user_id, $q);
    mysqli_stmt_execute($del);
    header("Location: search.php"); 
    exit();
}

//Clear history log
if (isset($_POST['clear_all'])) {
    mysqli_query($conn, "DELETE FROM recent_searches WHERE user_id = $user_id");
    header("Location: search.php"); 
    exit();
}

//get up to 8 recent tracks
$searches_res = mysqli_query($conn, "SELECT query FROM recent_searches WHERE user_id = $user_id ORDER BY searched_at DESC LIMIT 8");
$recent_searches = mysqli_fetch_all($searches_res, MYSQLI_ASSOC);

//recently viewed subcommunities
$visits_query = "SELECT s.id, s.name, s.description, s.member_count, s.topic 
                 FROM recent_visits rv 
                 JOIN subcommunities s ON rv.sub_id = s.id 
                 WHERE rv.user_id = $user_id 
                 ORDER BY rv.visited_at DESC LIMIT 6";
$visits_res = mysqli_query($conn, $visits_query);
$recent_visits = mysqli_fetch_all($visits_res, MYSQLI_ASSOC);

//search execution block
$results = ['subs' => [], 'posts' => [], 'users' => []];

if (!empty($query)) {
    $safe = '%' . mysqli_real_escape_string($conn, $query) . '%';
//look up matching sub-forums
    $subs_res = mysqli_query($conn, "SELECT id, name, description, member_count, topic FROM subcommunities WHERE name LIKE '$safe' OR description LIKE '$safe' LIMIT 8");
    $results['subs'] = mysqli_fetch_all($subs_res, MYSQLI_ASSOC);
//look up matching thread posts
    $posts_query = "SELECT p.id, p.title, p.content, p.upvotes, p.created_at, u.username AS author, s.name AS sub_name 
                    FROM posts p 
                    JOIN users u ON p.user_id = u.id 
                    JOIN subcommunities s ON p.sub_id = s.id 
                    WHERE (p.title LIKE '$safe' OR p.content LIKE '$safe') AND p.is_removed = 0 
                    ORDER BY p.upvotes DESC LIMIT 10";
    $posts_res = mysqli_query($conn, $posts_query);
    $results['posts'] = mysqli_fetch_all($posts_res, MYSQLI_ASSOC);
//look up matching network members
    $users_res = mysqli_query($conn, "SELECT id, username, user_type, profile_photo FROM users WHERE username LIKE '$safe' AND user_type != 'admin' LIMIT 6");
    $results['users'] = mysqli_fetch_all($users_res, MYSQLI_ASSOC);
}

$total_results = count($results['subs']) + count($results['posts']) + count($results['users']);
$sub_icons = ['🖥️','📢','🐱','🔍','🤖','🧠','🔬','📚','🎮','💬','🎨','🌍','🏋️','🎵','📸'];

function subIcon($name, $icons) {
    return $icons[abs(crc32($name)) % count($icons)];
}

function timeAgo($date) {
    $seconds = time() - strtotime($date);
    if ($seconds < 60) return $seconds . 's ago';
    if ($seconds < 3600) return floor($seconds / 60) . 'm ago';
    if ($seconds < 86400) return floor($seconds / 3600) . 'h ago';
    return floor($seconds / 86400) . 'd ago';
}

function renderSearchAvatar($photo, $name, $size = 32) {
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
  <title><?php echo $query ? htmlspecialchars($query) . ' – ' : ''; ?>Search – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .search-page { max-width: 800px; margin: 0 auto; padding: 0 20px 60px; }
    .search-bar-wrap {
      padding: 28px 0 24px;
      position: sticky; top: 58px; z-index: 100;
      background: rgba(13,13,26,.85); backdrop-filter: blur(16px);
    }
    .search-bar {
      display: flex; align-items: center; gap: 12px;
      background: rgba(255,255,255,.08); border: 1px solid var(--card-border);
      border-radius: 16px; padding: 14px 18px; transition: border-color .2s;
    }
    .search-bar:focus-within { border-color: var(--accent); }
    .search-bar input {
      flex: 1; background: none; border: none; outline: none;
      color: var(--text-main); font-family: var(--font-body); font-size: 16px;
    }
    .search-bar input::placeholder { color: var(--text-muted); }
    .search-bar-icon { font-size: 18px; }
    .search-bar-clear {
      background: none; border: none; color: var(--text-muted);
      cursor: pointer; font-size: 16px; padding: 4px;
      display: <?php echo $query ? 'block' : 'none'; ?>;
    }

    .result-section { margin-bottom: 28px; }
    .result-section-title {
      font-family: var(--font-display); font-size: 13px; font-weight: 700;
      color: var(--text-muted); text-transform: uppercase; letter-spacing: .8px;
      margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;
    }
    .result-section-title a { font-size: 11px; color: var(--accent); text-decoration: none; text-transform: none; letter-spacing: 0; font-weight: 600; }

    .sub-result {
      display: flex; align-items: center; gap: 14px; padding: 14px 16px;
      background: var(--bg-card); border: 1px solid var(--card-border);
      border-radius: 12px; margin-bottom: 8px; text-decoration: none; color: var(--text-main);
      transition: border-color .2s, transform .15s;
    }
    .sub-result:hover { border-color: rgba(79,142,247,.3); transform: translateX(4px); }
    .sub-result-icon { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg,var(--accent2),var(--accent)); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
    .sub-result-name { font-weight: 700; font-size: 14px; margin-bottom: 3px; }
    .sub-result-desc { font-size: 12px; color: var(--text-muted); }
    .sub-result-meta { margin-left: auto; font-size: 11px; color: var(--text-muted); text-align: right; flex-shrink: 0; }

    .post-result {
      padding: 14px 16px; background: var(--bg-card); border: 1px solid var(--card-border);
      border-radius: 12px; margin-bottom: 8px; text-decoration: none; color: var(--text-main);
      display: block; transition: border-color .2s;
    }
    .post-result:hover { border-color: rgba(79,142,247,.3); }
    .post-result-sub { font-size: 11px; color: var(--accent); margin-bottom: 4px; }
    .post-result-title { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
    .post-result-snippet { font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
    .post-result-meta { font-size: 11px; color: var(--text-muted); }

    .user-result {
      display: flex; align-items: center; gap: 12px; padding: 12px 16px;
      background: var(--bg-card); border: 1px solid var(--card-border);
      border-radius: 12px; margin-bottom: 8px; text-decoration: none; color: var(--text-main);
      transition: border-color .2s;
    }
    .user-result:hover { border-color: rgba(79,142,247,.3); }
    .user-result-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg,var(--accent),var(--accent2)); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
    .user-result-name { font-weight: 700; font-size: 14px; }
    .user-result-type { font-size: 11px; color: var(--text-muted); }

    .recent-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .recent-chip {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 6px 14px; background: rgba(255,255,255,.07);
      border: 1px solid var(--card-border); border-radius: 20px;
      font-size: 13px; color: var(--text-main); cursor: pointer;
      text-decoration: none; transition: all .2s;
    }
    .recent-chip:hover { border-color: var(--accent); color: var(--accent); }

    .empty-state { text-align: center; padding: 70px 20px; }
    .empty-logo {
      font-family: var(--font-display); font-size: 32px; font-weight: 800;
      background: linear-gradient(135deg,rgba(255,255,255,.25),rgba(79,142,247,.5));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text; margin-bottom: 14px;
    }
    .empty-quote { font-size: 14px; color: var(--text-muted); }
    mark { background: rgba(79,142,247,.25); color: var(--accent); border-radius: 3px; padding: 0 2px; }
  </style>
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div>

<header class="navbar">
  <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-right" style="margin-left:auto;">
    <a href="dashboard.php" style="font-size:13px; color:var(--text-muted); text-decoration:none;">Back to feed</a>
  </div>
</header>

<div class="page-wrapper">
  <div class="search-page">

    <div class="search-bar-wrap">
      <form method="GET" action="search.php" id="searchForm">
        <div class="search-bar">
          <span class="search-bar-icon">🔍</span>
          <input type="text" name="q" id="searchInput" placeholder="Search communities, posts, people…" value="<?php echo htmlspecialchars($query); ?>" autofocus oninput="toggleClear(this)">
          <button type="button" class="search-bar-clear" id="clearBtn" onclick="clearSearch()">✕</button>
        </div>
      </form>
    </div>

    <!--intro state-->
    <?php if (empty($query)): ?>

    <?php if (!empty($recent_searches)): ?>
    <div class="result-section">
      <div class="result-section-title">
        Recent Searches
        <form method="POST" style="display:inline;">
          <button type="submit" name="clear_all" style="background:none; border:none; color:var(--danger); font-size:12px; cursor:pointer; font-family:var(--font-body);">Clear all</button>
        </form>
      </div>
      <div class="recent-chips">
        <?php foreach ($recent_searches as $s): ?>
        <span style="display:inline-flex; align-items:center; gap:0;">
          <a href="search.php?q=<?php echo urlencode($s['query']); ?>" class="recent-chip" style="border-radius:20px 0 0 20px; border-right:none;">
            <?php echo htmlspecialchars($s['query']); ?>
          </a>
          <form method="POST" style="display:inline; margin:0;">
            <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($s['query']); ?>">
            <button type="submit" name="clear_search" class="recent-chip" style="border-radius:0 20px 20px 0; padding:6px 10px;">✕</button>
          </form>
        </span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($recent_visits)): ?>
    <div class="result-section">
      <div class="result-section-title">Recently Visited</div>
      <?php foreach ($recent_visits as $sub): ?>
      <a href="sub.php?id=<?php echo $sub['id']; ?>" class="sub-result">
        <div class="sub-result-icon"><?php echo subIcon($sub['name'], $sub_icons); ?></div>
        <div style="flex:1; overflow:hidden;">
          <div class="sub-result-name"><?php echo htmlspecialchars($sub['name']); ?></div>
          <div class="sub-result-desc"><?php echo htmlspecialchars($sub['description'] ?? ''); ?></div>
        </div>
        <div class="sub-result-meta">
          <?php echo number_format($sub['member_count']); ?><br>
          <?php echo htmlspecialchars($sub['topic'] ?? ''); ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($recent_searches) && empty($recent_visits)): ?>
    <div class="empty-state">
      <div class="empty-logo">ScholarSpace</div>
      <divw class="empty-quote">Not so active eh?</div>
    </div>
    <?php endif; ?>

    <!--resutl view-->
    <?php else: ?>

    <?php if ($total_results === 0): ?>
    <div class="empty-state">
      <div style="font-size:48px; margin-bottom:16px;">🔭</div>
      <div class="empty-logo">ScholarSpace</div>
      <div class="empty-quote">No results for "<?php echo htmlspecialchars($query); ?>"</div>
    </div>
    <?php else: ?>

    <?php if (!empty($results['subs'])): ?>
    <div class="result-section">
      <div class="result-section-title">Communities (<?php echo count($results['subs']); ?>)</div>
      <?php foreach ($results['subs'] as $sub): ?>
      <a href="sub.php?id=<?php echo $sub['id']; ?>" class="sub-result">
        <div class="sub-result-icon"><?php echo subIcon($sub['name'], $sub_icons); ?></div>
        <div style="flex:1; overflow:hidden;">
          <div class="sub-result-name"><?php echo htmlspecialchars($sub['name']); ?></div>
          <div class="sub-result-desc"><?php echo htmlspecialchars($sub['description'] ?? ''); ?></div>
        </div>
        <div class="sub-result-meta">👥 <?php echo number_format($sub['member_count']); ?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($results['posts'])): ?>
    <div class="result-section">
      <div class="result-section-title">Posts (<?php echo count($results['posts']); ?>)</div>
      <?php foreach ($results['posts'] as $p): ?>
      <a href="post.php?id=<?php echo $p['id']; ?>" class="post-result">
        <div class="post-result-sub"><?php echo htmlspecialchars($p['sub_name']); ?> • by <?php echo htmlspecialchars($p['author']); ?></div>
        <div class="post-result-title"><?php echo htmlspecialchars($p['title']); ?></div>
        <?php if (!empty($p['content'])): ?>
          <div class="post-result-snippet"><?php echo htmlspecialchars(mb_substr(strip_tags($p['content']), 0, 150)); ?>…</div>
        <?php endif; ?>
        <div class="post-result-meta">▲ <?php echo $p['upvotes']; ?> • <?php echo timeAgo($p['created_at']); ?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($results['users'])): ?>
    <div class="result-section">
      <div class="result-section-title">People (<?php echo count($results['users']); ?>)</div>
      <?php foreach ($results['users'] as $u): ?>
      <a href="profile.php?id=<?php echo $u['id']; ?>" class="user-result">
        <div class="user-result-avatar"><?php echo renderSearchAvatar($u['profile_photo'], $u['username'], 40); ?></div>
        <div>
          <div class="user-result-name"><?php echo htmlspecialchars($u['username']); ?></div>
          <div class="user-result-type">
            <?php echo $u['user_type'] === 'student' ? '🎓 Student' : '💼 Graduate'; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<script>
const searchInput = document.getElementById('searchInput');
let searchTimer;

searchInput.addEventListener('input', function() {
  clearTimeout(searchTimer);
  toggleClear(this);
  searchTimer = setTimeout(() => {
    if (this.value.trim().length >= 2) {
      document.getElementById('searchForm').submit();
    }
  }, 600);
});

function toggleClear(input) {
  document.getElementById('clearBtn').style.display = input.value ? 'block' : 'none';
}

function clearSearch() {
  searchInput.value = '';
  searchInput.focus();
  document.getElementById('clearBtn').style.display = 'none';
  window.location.href = 'search.php';
}
</script>
</body>
</html>