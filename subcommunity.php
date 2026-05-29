<?php
session_start();
include "db.php";

// Check if database connection exists
if (!isset($conn) || !$conn) {
    die("❌ Database connection failed. Check db.php");
}

// Get subcommunity slug from URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : 'computerscience';

// Validate slug
if (empty($slug)) {
    die("❌ No subcommunity slug provided.");
}

// Fetch subcommunity details
$sub_query = "SELECT * FROM subcommunities WHERE slug = ?";
$sub_stmt = mysqli_prepare($conn, $sub_query);

if (!$sub_stmt) {
    die("❌ Query prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($sub_stmt, "s", $slug);
$execute_result = mysqli_stmt_execute($sub_stmt);

if (!$execute_result) {
    die("❌ Query execute failed: " . mysqli_error($conn));
}

$sub_result = mysqli_stmt_get_result($sub_stmt);

if (!$sub_result) {
    die("❌ Failed to get result: " . mysqli_error($conn));
}

$subcommunity = mysqli_fetch_assoc($sub_result);

if (!$subcommunity) {
    die("❌ Subcommunity with slug '" . htmlspecialchars($slug) . "' not found. Available slugs: computerscience, machinelearning, ranting, cats, whatisthis, programming-y1");
}

$sub_id = $subcommunity['id'];
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Update recent visit (if user is logged in)
if ($user_id) {
    $visit_query = "INSERT INTO recent_visits (user_id, sub_id) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE visited_at = NOW()";
    $visit_stmt = mysqli_prepare($conn, $visit_query);
    mysqli_stmt_bind_param($visit_stmt, "ii", $user_id, $sub_id);
    mysqli_stmt_execute($visit_stmt);
}

// Fetch posts with vote counts and author info
$posts_query = "SELECT p.id, p.title, p.content, p.image_url, p.upvotes, p.downvotes, 
                       p.created_at, u.username, u.profile_photo,
                       (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
                FROM posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.sub_id = ? AND p.is_removed = 0
                ORDER BY p.created_at DESC";
$posts_stmt = mysqli_prepare($conn, $posts_query);
mysqli_stmt_bind_param($posts_stmt, "i", $sub_id);
mysqli_stmt_execute($posts_stmt);
$posts_result = mysqli_stmt_get_result($posts_stmt);
$posts = mysqli_fetch_all($posts_result, MYSQLI_ASSOC);

// Check if user is a member of this subcommunity
$is_member = false;
$member_role = null;
if ($user_id) {
    $member_query = "SELECT role FROM sub_memberships WHERE user_id = ? AND sub_id = ?";
    $member_stmt = mysqli_prepare($conn, $member_query);
    mysqli_stmt_bind_param($member_stmt, "ii", $user_id, $sub_id);
    mysqli_stmt_execute($member_stmt);
    $member_result = mysqli_stmt_get_result($member_stmt);
    if ($member_row = mysqli_fetch_assoc($member_result)) {
        $is_member = true;
        $member_role = $member_row['role'];
    }
}

// Handle join/leave subcommunity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$user_id) {
        header("Location: login.php");
        exit();
    }

    if ($_POST['action'] === 'join') {
        $join_query = "INSERT INTO sub_memberships (user_id, sub_id, role) VALUES (?, ?, 'member')
                       ON DUPLICATE KEY UPDATE role = VALUES(role)";
        $join_stmt = mysqli_prepare($conn, $join_query);
        mysqli_stmt_bind_param($join_stmt, "ii", $user_id, $sub_id);
        if (mysqli_stmt_execute($join_stmt)) {
            // Update member count
            $update_count = "UPDATE subcommunities SET member_count = member_count + 1 WHERE id = ? AND id NOT IN (SELECT sub_id FROM sub_memberships WHERE sub_id = ? AND user_id = ?)";
            $count_stmt = mysqli_prepare($conn, $update_count);
            mysqli_stmt_bind_param($count_stmt, "iii", $sub_id, $sub_id, $user_id);
            mysqli_stmt_execute($count_stmt);
            $is_member = true;
            $member_role = 'member';
        }
    } elseif ($_POST['action'] === 'leave') {
        $leave_query = "DELETE FROM sub_memberships WHERE user_id = ? AND sub_id = ?";
        $leave_stmt = mysqli_prepare($conn, $leave_query);
        mysqli_stmt_bind_param($leave_stmt, "ii", $user_id, $sub_id);
        if (mysqli_stmt_execute($leave_stmt)) {
            $update_count = "UPDATE subcommunities SET member_count = GREATEST(member_count - 1, 1) WHERE id = ?";
            $count_stmt = mysqli_prepare($conn, $update_count);
            mysqli_stmt_bind_param($count_stmt, "i", $sub_id);
            mysqli_stmt_execute($count_stmt);
            $is_member = false;
            $member_role = null;
        }
    }
    
    // Refresh member count
    $refresh_query = "SELECT member_count FROM subcommunities WHERE id = ?";
    $refresh_stmt = mysqli_prepare($conn, $refresh_query);
    mysqli_stmt_bind_param($refresh_stmt, "i", $sub_id);
    mysqli_stmt_execute($refresh_stmt);
    $refresh_result = mysqli_stmt_get_result($refresh_stmt);
    if ($refresh_row = mysqli_fetch_assoc($refresh_result)) {
        $subcommunity['member_count'] = $refresh_row['member_count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($subcommunity['name']); ?> – ScholarSpace</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .sub-banner-wrap { position: relative; height: 200px; background: linear-gradient(135deg, rgba(79,142,247,.2), rgba(192,109,232,.15)); border-radius: 16px; margin-bottom: 20px; overflow: hidden; }
        .sub-banner-wrap::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, var(--accent), var(--accent2)); opacity: 0.1; }
        .sub-info-card { display: flex; gap: 20px; align-items: flex-end; padding: 20px; position: relative; z-index: 1; }
        .sub-avatar-large { width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent2)); display: flex; align-items: center; justify-content: center; font-size: 48px; border: 4px solid var(--bg-deep); flex-shrink: 0; }
        .sub-header-text { flex: 1; }
        .sub-header-text h1 { font-family: var(--font-display); font-size: 28px; font-weight: 800; margin-bottom: 8px; }
        .sub-header-text p { color: var(--text-muted); margin-bottom: 12px; }
        .sub-stats-row { display: flex; gap: 20px; margin-top: 12px; }
        .stat-item { display: flex; flex-direction: column; }
        .stat-item strong { font-size: 18px; font-weight: 700; }
        .stat-item span { font-size: 12px; color: var(--text-muted); }
        .join-btn-wrap { display: flex; gap: 8px; }
        .join-btn-wrap form { display: contents; }
        .comment-thread { margin-left: 20px; border-left: 2px solid var(--card-border); padding-left: 16px; }
        .comment { display: flex; gap: 12px; margin-bottom: 12px; }
        .vote-column { display: flex; flex-direction: column; align-items: center; gap: 4px; }
        .vote-btn { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 16px; padding: 4px; transition: color 0.2s; }
        .vote-btn:hover { color: var(--accent); }
        .vote-btn.upvote:hover { color: var(--success); }
        .vote-btn.downvote:hover { color: var(--danger); }
        .vote-count { font-size: 12px; font-weight: 600; min-width: 30px; text-align: center; }
        .comment-body { flex: 1; }
        .comment-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
        .comment-author { color: var(--accent); font-weight: 600; text-decoration: none; }
        .comment-author:hover { text-decoration: underline; }
        .comment-text { color: var(--text-main); font-size: 14px; line-height: 1.5; margin-bottom: 8px; }
        .comment-actions { display: flex; gap: 8px; }
        .action-btn { background: rgba(255,255,255,.06); border: none; border-radius: 20px; padding: 4px 10px; font-size: 12px; color: var(--text-muted); cursor: pointer; font-family: var(--font-body); transition: background 0.2s, color 0.2s; }
        .action-btn:hover { background: rgba(79,142,247,.15); color: var(--accent); }
        .create-post-box { margin-bottom: 20px; }
        .create-post-input { width: 100%; padding: 12px; background: rgba(255,255,255,.07); border: 1px solid var(--card-border); border-radius: 10px; color: var(--text-main); font-family: var(--font-body); font-size: 14px; outline: none; transition: border-color 0.2s; }
        .create-post-input:focus { border-color: var(--accent); }
        .replies { margin-left: 20px; border-left: 2px solid var(--card-border); padding-left: 16px; }
        .post-preview { display: flex; gap: 16px; padding: 16px 0; border-bottom: 1px solid var(--card-border); }
        .post-preview:last-child { border-bottom: none; }
        .post-info { flex: 1; }
        .post-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
        .post-title { font-family: var(--font-display); font-size: 16px; font-weight: 600; margin-bottom: 8px; color: var(--text-main); cursor: pointer; }
        .post-title:hover { color: var(--accent); }
        .post-snippet { font-size: 14px; color: var(--text-muted); line-height: 1.5; margin-bottom: 10px; }
        .post-image { width: 100%; max-width: 300px; height: 200px; object-fit: cover; border-radius: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="stars-bg"></div>
    <div class="sunset-bg"></div>

    <!-- Navbar -->
    <header class="navbar">
        <a href="index.php" class="nav-logo">ScholarSpace</a>
        <div class="nav-search">
            <input type="text" class="search-input" placeholder="Search...">
        </div>
        <div class="nav-right">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="nav-welcome">Welcome <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
            <?php endif; ?>
            <input type="checkbox" id="profile-toggle" class="profile-toggle-checkbox">
            <label for="profile-toggle" class="profile-avatar">
                <?php echo isset($_SESSION['user_id']) ? strtoupper(substr($_SESSION['username'], 0, 1)) : '👤'; ?>
            </label>
            <div class="profile-menu">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php">My Profile</a>
                    <a href="settings.php">User Settings</a>
                    <hr>
                    <a href="logout.php">Log Out</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="page-wrapper">
        <div class="layout-container">
            <!-- Main Content -->
            <main class="main-content">
                <!-- Subcommunity Header -->
                <div class="sub-banner-wrap">
                    <div class="sub-info-card">
                        <div class="sub-avatar-large">
                            <?php 
                            // Show first letter or emoji based on topic
                            $emoji_map = ['Study' => '📚', 'Hobby' => '🎯', 'Career' => '💼'];
                            echo $emoji_map[$subcommunity['topic']] ?? '📌';
                            ?>
                        </div>
                        <div class="sub-header-text">
                            <h1><?php echo htmlspecialchars($subcommunity['name']); ?></h1>
                            <p><?php echo htmlspecialchars($subcommunity['description']); ?></p>
                            <div class="sub-stats-row">
                                <div class="stat-item">
                                    <strong><?php echo number_format($subcommunity['member_count']); ?></strong>
                                    <span>Members</span>
                                </div>
                                <div class="stat-item">
                                    <strong><?php echo count($posts); ?></strong>
                                    <span>Posts</span>
                                </div>
                            </div>
                        </div>
                        <div class="join-btn-wrap">
                            <?php if (!isset($_SESSION['user_id'])): ?>
                                <a href="login.php" class="btn btn-primary" style="width: 120px; padding: 8px; text-decoration: none;">Join</a>
                            <?php elseif ($is_member): ?>
                                <form method="POST" style="display: contents;">
                                    <input type="hidden" name="action" value="leave">
                                    <button type="submit" class="btn btn-secondary" style="width: 120px; padding: 8px;">Leave</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display: contents;">
                                    <input type="hidden" name="action" value="join">
                                    <button type="submit" class="btn btn-primary" style="width: 120px; padding: 8px;">Join</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Create Post Section (only for members) -->
                <?php if ($is_member): ?>
                <div class="card create-post-box">
                    <div class="card-body">
                        <a href="create_post.php?sub_id=<?php echo $sub_id; ?>" class="btn btn-primary">
                            ✏️ Create a post
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Posts Section -->
                <div class="card">
                    <div style="padding: 20px;">
                        <h3 style="font-family: var(--font-display); font-size: 18px; font-weight: 700; margin-bottom: 16px;">
                            Current Discussion
                        </h3>

                        <?php if (count($posts) === 0): ?>
                            <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                                <p>No posts yet. Be the first to post! 🚀</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                            <div class="post-preview">
                                <div class="vote-column">
                                    <button class="vote-btn upvote" onclick="vote(<?php echo $post['id']; ?>, 'upvote')">▲</button>
                                    <span class="vote-count"><?php echo $post['upvotes'] - $post['downvotes']; ?></span>
                                    <button class="vote-btn downvote" onclick="vote(<?php echo $post['id']; ?>, 'downvote')">▼</button>
                                </div>
                                <div class="post-info">
                                    <div class="post-meta">
                                        Posted by <a href="profile.php?user=<?php echo urlencode($post['username']); ?>" class="comment-author">
                                            <?php echo htmlspecialchars($post['username']); ?>
                                        </a> in r/<?php echo htmlspecialchars($subcommunity['slug']); ?> • 
                                        <?php 
                                            $time_diff = time() - strtotime($post['created_at']);
                                            if ($time_diff < 60) echo "now";
                                            elseif ($time_diff < 3600) echo floor($time_diff / 60) . " min ago";
                                            elseif ($time_diff < 86400) echo floor($time_diff / 3600) . " hours ago";
                                            else echo floor($time_diff / 86400) . " days ago";
                                        ?>
                                    </div>
                                    <h4 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h4>
                                    <p class="post-snippet"><?php echo htmlspecialchars(substr($post['content'], 0, 150)); ?></p>
                                    <?php if ($post['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="Post image" class="post-image">
                                    <?php endif; ?>
                                    <div class="comment-actions">
                                        <button class="action-btn" onclick="alert('Comments coming soon!')">💬 <?php echo $post['comment_count']; ?> Comments</button>
                                        <button class="action-btn" onclick="alert('Share feature coming soon!')">Share</button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>

            <!-- Left Sidebar (Community Info) -->
            <aside class="left-sidebar">
                <div class="card" style="overflow: hidden;">
                    <div style="padding: 16px;">
                        <h3 style="font-family: var(--font-display); font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.8px; margin-bottom: 12px;">About r/<?php echo htmlspecialchars($subcommunity['slug']); ?></h3>
                        
                        <div style="font-size: 13px; line-height: 1.6; color: var(--text-muted); margin-bottom: 16px;">
                            <p><?php echo htmlspecialchars($subcommunity['description']); ?></p>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 12px; background: rgba(255,255,255,.04); border-radius: 8px; border: 1px solid var(--card-border); margin-bottom: 16px;">
                            <div>
                                <strong style="display: block; font-size: 16px; margin-bottom: 4px;">
                                    <?php echo number_format($subcommunity['member_count']); ?>
                                </strong>
                                <span style="font-size: 11px; color: var(--text-muted);">Members</span>
                            </div>
                            <div>
                                <strong style="display: block; font-size: 16px; margin-bottom: 4px;">
                                    <?php echo count($posts); ?>
                                </strong>
                                <span style="font-size: 11px; color: var(--text-muted);">Posts</span>
                            </div>
                        </div>

                        <p style="font-size: 12px; color: var(--text-muted);">
                            <strong>Topic:</strong> <?php echo htmlspecialchars($subcommunity['topic']); ?><br>
                            <strong>Created:</strong> <?php echo date('M d, Y', strtotime($subcommunity['created_at'])); ?>
                        </p>
                    </div>
                </div>

                <!-- Moderators/Info -->
                <div class="card" style="margin-top: 16px;">
                    <div class="sidebar-card-header">Moderators</div>
                    <div style="padding: 12px 16px; font-size: 12px; color: var(--text-muted);">
                        <p>Community moderators welcome here!</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script>
        function vote(postId, voteType) {
            <?php if (!isset($_SESSION['user_id'])): ?>
                window.location.href = 'login.php';
                return;
            <?php endif; ?>
            
            // Placeholder for voting functionality
            alert('Vote recorded: ' + voteType + ' on post ' + postId);
            // location.reload();
        }
    </script>
</body>
</html>