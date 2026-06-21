<?php
session_start();
include "db.php";

// Check if database connection exists
if (!isset($conn) || !$conn) {
    die("❌ Database connection failed. Check db.php");
}

// Get subcommunity id from URL
$sub_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate id
if ($sub_id <= 0) {
    die("❌ Invalid subcommunity ID.");
}

// Fetch subcommunity details
$sub_query = "SELECT * FROM subcommunities WHERE id = ?";
$sub_stmt = mysqli_prepare($conn, $sub_query);

if (!$sub_stmt) {
    die("❌ Query prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($sub_stmt, "i", $sub_id);
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
    die("❌ Subcommunity not found.");
}

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
$posts_query = "SELECT p.id, p.user_id, p.title, p.content, p.image_url, p.link_url, p.upvotes, p.downvotes, 
                       p.created_at, u.username, u.profile_photo,
                       (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND is_removed = 0) as comment_count
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
            $is_member = true;
            $member_role = 'member';
        }
    } elseif ($_POST['action'] === 'leave') {
        $leave_query = "DELETE FROM sub_memberships WHERE user_id = ? AND sub_id = ?";
        $leave_stmt = mysqli_prepare($conn, $leave_query);
        mysqli_stmt_bind_param($leave_stmt, "ii", $user_id, $sub_id);
        if (mysqli_stmt_execute($leave_stmt)) {
            $is_member = false;
            $member_role = null;
        }
    }

    // Get total members of a subcommunity
    $countMember_query = "SELECT COUNT(*) AS total FROM sub_memberships WHERE sub_id = ?";
    $countMember_stmt = mysqli_prepare($conn, $countMember_query);
    mysqli_stmt_bind_param($countMember_stmt, "i", $sub_id);
    mysqli_stmt_execute($countMember_stmt);

    $countMember_result = mysqli_stmt_get_result($countMember_stmt);
    $countMember_row = mysqli_fetch_assoc($countMember_result);

    $total_members = $countMember_row['total'];

    // Update member count of subcommunities table
    $updateMemberCount_query = "UPDATE subcommunities SET member_count = ? WHERE id = ?";
    $updateMemberCount_stmt = mysqli_prepare($conn, $updateMemberCount_query);
    mysqli_stmt_bind_param($updateMemberCount_stmt, "ii", $total_members, $sub_id);

    mysqli_stmt_execute($updateMemberCount_stmt);
    
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
        .profile-picture-and-name {display: flex; align-items: center; gap: 10px}
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
        .join-btn-wrap { display: flex; gap: 8px; flex-direction: column;align-items: flex-end }
        .join-btn-wrap form { display: contents; }
        .vote-btn { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 16px; padding: 4px; transition: color 0.2s; }
        .vote-btn.upvote:hover { color: var(--success); }
        .vote-btn.downvote:hover { color: var(--danger); }
        .vote-count { font-size: 12px; font-weight: 600; min-width: 30px; text-align: center; }
        .post-author { color: var(--accent); font-weight: 600; text-decoration: none; }
        .post-author:hover { text-decoration: underline; }
        .comment-actions { display: flex; gap: 8px; }
        .action-btn { background: rgba(255,255,255,.06); border: none; border-radius: 20px; padding: 4px 10px; font-size: 12px; color: var(--text-muted); cursor: pointer; font-family: var(--font-body); transition: background 0.2s, color 0.2s; }
        .action-btn:hover { background: rgba(79,142,247,.15); color: var(--accent); }
        .post-preview { display: flex; gap: 16px; padding: 16px 0; border-bottom: 1px solid var(--card-border); }
        .post-preview:last-child { border-bottom: none; }
        .post-info {display: flex; flex: 1; justify-content: space-between;}
        .post-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
        .post-title { font-family: var(--font-display); font-size: 16px; font-weight: 600; margin-bottom: 8px; color: var(--text-main); cursor: pointer; }
        .post-title:hover { color: var(--accent); }
        .post-snippet { font-size: 14px; color: var(--text-muted); line-height: 1.5; margin-bottom: 10px; }
        .post-image { width: 100%; max-width: 300px; height: 200px; object-fit: cover; border-radius: 10px; margin: 10px 0; }
        .post-menu {position: relative; display: inline-block;}
        .dropdown-menu {display: none;position: absolute; top: 100%; right: 0; min-width: 150px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,.2); z-index: 1000; background:#1e1e35; border:1px solid var(--card-border);}
        .dropdown-menu a {display: block; padding: 12px 16px; color:var(--text-main); text-decoration: none;}
        .dropdown-menu a:hover {background:rgba(255,255,255,.07); border-radius: 8px;}
        .report-modal, .delete-modal {display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 9999; justify-content: center; align-items: center;}
        .report-content, .delete-content {width: 600px; max-width: 90%; background: #2d2d2d; border-radius: 30px; padding: 40px; color: white;}
        .report-content h2, .delete-content h2 {text-align: center; margin-bottom: 10px;}
        .report-content p, .delete-content p {text-align: center; margin-bottom: 30px;}
        .report-option {display: flex; align-items:center; gap: 15px; margin-bottom: 20px; font-size: 18px; cursor: pointer;}
        .report-option input[type="radio"] {width: 25px; height: 25px;}
        .confirm-btn {width: 100%; padding: 15px; border: none; border-radius: 30px; margin-top: 20px; font-size: 18px; font-weight: bold; cursor: pointer;}
        .close-btn {float: right; font-size: 28px; cursor: pointer;}
        .post-link {color: var(--accent); text-decoration: none; font-weight: 500; display: inline-block; margin: 10px 0; transition: 0.2s;}
        .post-link:hover {color: #6fa8ff; text-decoration: underline;}
        .post-link:visited {color: #b78cff;}

        #snackbar {
            visibility: hidden;
            position: fixed;
            left: 50%;
            bottom: 30px;
            transform: translateX(-50%);
            min-width: 280px;
            max-width: 500px;
            background: #2d2d2d;
            color: white;
            padding: 16px 24px;
            border-radius: 14px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .35);
            z-index: 99999;
            opacity: 0;
            transition: opacity .4s ease, bottom .4s ease;
            font-size: 14px;
        }

        #snackbar.show {
            visibility: visible;
            opacity: 1;
            bottom: 50px;
        }
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
                                    <strong id="post-count-header"><?php echo count($posts); ?></strong>
                                    <span>Posts</span>
                                </div>
                            </div>
                        </div>
                        <div class="join-btn-wrap">
                            <?php if (!isset($_SESSION['user_id'])): ?>
                                <a href="login.php" class="btn btn-primary" style="width: 120px; padding: 8px; text-decoration: none;">Join</a>
                            <?php elseif ($is_member): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="leave">
                                    <button type="submit" class="btn btn-secondary" style="width: 120px; padding: 8px;">Leave</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display: contents;">
                                    <input type="hidden" name="action" value="join">
                                    <button type="submit" class="btn btn-primary" style="width: 120px; padding: 8px;">Join</button>
                                </form>
                            <?php endif; ?>

                            <!-- Create Post Section (only for members) -->
                            <?php if ($is_member): ?>
                            <a href="create_post.php?sub_id=<?php echo $sub_id; ?>" class="btn btn-primary">
                                 Create a post
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Posts Section -->
                <div class="card">
                    <div style="padding: 20px;">
                        <h3 style="font-family: var(--font-display); font-size: 18px; font-weight: 700; margin-bottom: 16px;">
                            Top post now
                        </h3>

                        <?php if (count($posts) === 0): ?>
                            <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                                <p>No posts yet. Be the first to post! 🚀</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                            <div class="post-preview" data-post-id="<?php echo $post['id']; ?>">
                                <div class="post-info">
                                    <div class="post-left">
                                        <div class="post-meta">
                                            <div class="profile-picture-and-name">
                                                <img src="uploads/profiles/<?php echo htmlspecialchars($post['profile_photo']);?>" alt="Profile" class="profile-avatar">
                                                <a href="profile.php?user=<?php echo urlencode($post['username']); ?>" class="post-author">
                                                <?php echo htmlspecialchars($post['username']); ?>
                                                </a>• 
                                            
                                                <?php 
                                                $time_diff = time() - strtotime($post['created_at']);
                                                if ($time_diff < 60) echo "now";
                                                elseif ($time_diff < 3600) echo floor($time_diff / 60) . " min ago";
                                                elseif ($time_diff < 86400) echo floor($time_diff / 3600) . " hours ago";
                                                else echo floor($time_diff / 86400) . " days ago";
                                                ?>
                                            </div>
                                        </div>
                                        <h4 class="post-title" onclick="window.location.href='post.php?id=<?php echo $post['id']; ?>'">
                                                <?php echo htmlspecialchars($post['title']); ?>
                                        </h4>
                                        <p class="post-snippet"><?php echo htmlspecialchars(substr($post['content'], 0, 150)); ?></p>
                                        <?php if ($post['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="Post image" class="post-image">
                                        <?php endif; ?>

                                        <?php if (!empty($post['link_url'])): ?>
                                            <a href="<?php echo htmlspecialchars(
                                                preg_match('/^https?:\/\//', $post['link_url'])
                                                ? $post['link_url']
                                                : 'https://' . $post['link_url']
                                            ); ?>" class="post-link" target="_blank" rel="noopener noreferrer">
                                            <?php echo htmlspecialchars($post['link_url']);?></a>
                                        <?php endif; ?>


                                        <div class="comment-actions">
                                            <div class="vote-group">
                                                <button type="button" class="vote-btn upvote" name="vote-action" value="upvote" onclick="vote(<?php echo $post['id']; ?>, 'upvote')">▲</button>
                                                <span class="vote-count" id="upvotes-<?php echo $post['id'];?>"><?php echo $post['upvotes']; ?></span>
                                                <span style="width:1px; height: 20px; background: rgba(255,255,255,.25)"></span>
                                                <span class="vote-count" id="downvotes-<?php echo $post['id'];?>"><?php echo $post['downvotes'];?></span>
                                                <button type="button" class="vote-btn downvote" name="vote-action" value="downvote" onclick="vote(<?php echo $post['id']; ?>, 'downvote')">▼</button>
                                            </div>
                                            <button class="action-btn" onclick="window.location.href='post.php?id=<?php echo $post['id']; ?>'">
                                                💬 <?php echo $post['comment_count']; ?> Comments
                                            </button>
                                        </div>
                                    </div>
                                    <div class="post-right">
                                        <div class="post-menu">
                                             <button class="action-btn" onclick="toggleMenu(this)" style="font-size: 20px; color: white">&#8942;</button>
                                             <div class="dropdown-menu">

                                                <a href="#" onclick="openReportModal(<?php echo $post['id']; ?>); return false;">Report</a>
                                                
                                                <?php $canDelete = isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $post['user_id'] || ($_SESSION['role'] ?? '') === 'admin'); ?>
                                                <?php if($canDelete): ?>
                                                    <a href="#" onclick="openDeleteModal(<?php echo $post['id']; ?>); return false;">Delete</a>
                                                <?php endif; ?>
                                             </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>

            <!-- Right Sidebar (Community Info) -->
            <aside class="right-sidebar">
                <div class="card" style="overflow: hidden;">
                    <div style="padding: 16px;">
                        <h3 style="font-family: var(--font-display); font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.8px; margin-bottom: 12px;">About <?php echo htmlspecialchars($subcommunity['name']); ?></h3>
                        
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
                                <strong id="post-count-sidebar" style="display: block; font-size: 16px; margin-bottom: 4px;">
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

    <div id="reportModal" class="report-modal">
        <div class="report-content">
            <span class="close-btn" onclick="closeReportModal()">&times;</span>

            <h2>REPORT</h2>
            <p>Tell us what's going on</p>

            <form id="reportForm">
                <input type="hidden" id="reportPostId" name="post_id">

                <label class="report-option">
                    <input type="radio" name="reason" value="Explicit content">
                    Explicit content
                </label>

                <label class="report-option">
                    <input type="radio" name="reason" value="Harassment and bullying">
                    Harassment and bullying
                </label>

                <label class="report-option">
                    <input type="radio" name="reason" value="Harmful or dangerous acts">
                    Harmful or dangerous acts
                </label>

                <label class="report-option">
                    <input type="radio" name="reason" value="Self harm">
                    Suicidal, self harm or disorders that caused harm
                </label>

                <label class="report-option">
                    <input type="radio" name="reason" value="Fake news">
                    Fake news
                </label>

                <button type="button" class="confirm-btn" onclick="submitReport()">
                    Confirm
                </button>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="delete-modal">
        <div class="delete-content">
            <span class="close-btn" onclick="closeDeleteModal()">
                &times;
            </span>

            <h2 style="color:red;">Warning</h2>

            <p>Are you sure you want to delete this post?</p>

            <input type="hidden" id="deletePostId">

            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button class="confirm-btn" style="background:#555;" onclick="closeDeleteModal()">
                    No
                </button>

                <button class="confirm-btn" style="background:#d9534f;" onclick="deletePost()">
                    Yes
                </button>
            </div>
        </div>
    </div>

    <div id="snackbar"></div>

    <script>
        function vote(postId, voteType) {
            <?php if (!isset($_SESSION['user_id'])): ?>
                window.location.href = 'login.php';
                return;
            <?php endif; ?>
            
            fetch("vote.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body:
                    "post_id=" + encodeURIComponent(postId) +
                    "&vote_type=" + encodeURIComponent(voteType)
            })
            .then(response => response.json())
            .then(data => {

                if(data.success)
                {
                    document.getElementById(
                        "upvotes-" + postId
                    ).textContent = data.upvotes;

                    document.getElementById(
                        "downvotes-" + postId
                    ).textContent = data.downvotes;
                }
                else
                {
                    showSnackbar(data.message);
                    return
                }
            })
            .catch(error => {
                console.error(error);
            });
        }

        function toggleMenu(button)
        {
            const menu = button.nextElementSibling;

            // Close all other menus
            document.querySelectorAll('.dropdown-menu').forEach(m => {
                if (m !== menu) 
                {
                    m.style.display = 'none';
                }
            });

            // Toggle current menu
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }

        // Close menu when clicking elsewhere
        document.addEventListener('click', function(e) 
        {
            if(!e.target.closest('.post-menu')) 
            {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
                });
            }
        });

        function openReportModal(postId)
        {
            document.getElementById("reportPostId").value = postId;
            document.getElementById("reportModal").style.display = "flex";
        }

        function closeReportModal()
        {
            document.getElementById("reportModal").style.display = "none";
        }

        window.onclick = function(event)
        {
            if(event.target.id==="reportModal")
            {
                closeReportModal();
            }

            if(event.target.id==="deleteModal")
            {
                closeDeleteModal();
            }
        }

        function submitReport()
        {
            const postId = document.getElementById("reportPostId").value;

            const reason = document.querySelector('input[name="reason"]:checked');

            if(!reason)
            {
                showSnackbar("Please select a reason");
                return;
            }

            fetch("report_post.php", {
                method: "POST",
                headers: {
                    "Content-Type":
                    "application/x-www-form-urlencoded"
                },
                body:
                    "post_id=" + encodeURIComponent(postId) +
                    "&reason=" + encodeURIComponent(reason.value)
            })
            .then(response => response.text())
            .then(data => {
                data = data.trim();
                
                if(data === "success")
                {
                    showSnackbar("Report submitted");
                    closeReportModal();
                }
                else if(data === "already_reported")
                {
                    showSnackbar("You already reported this post");
                }
                else
                {
                    showSnackbar("Failed to submit report");
                }
            })
            .catch(error => {
                console.error(error);
            });
        }

        function openDeleteModal(postId)
        {
            document.getElementById("deletePostId").value = postId;
            document.getElementById("deleteModal").style.display = "flex";
        }

        function closeDeleteModal()
        {
            document.getElementById("deleteModal").style.display = "none";
        }

        function deletePost()
        {
            const postId = document.getElementById("deletePostId").value;

            fetch("delete_post.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body:
                    "post_id=" + encodeURIComponent(postId)
            })
            .then(response=>response.text())
            .then(data => {
                
                if(data.trim() === "success")
                {
                    closeDeleteModal();

                    const post = document.querySelector(`[data-post-id="${postId}"]`);

                    if(post)
                    {
                        post.remove();

                        const headerCount = document.getElementById("post-count-header");
                        const sidebarCount = document.getElementById("post-count-sidebar");

                        if(headerCount)
                        {
                            headerCount.textContent =
                                Math.max(0, parseInt(headerCount.textContent) - 1);
                        }

                        if(sidebarCount)
                        {
                            sidebarCount.textContent =
                                Math.max(0, parseInt(sidebarCount.textContent) - 1);
                        }
                    }

                    showSnackbar("Post deleted successfully");
                }
                else
                {
                    showSnackbar(data);
                }
            })
            .catch(error => {
                console.error(error);
            });
        }

        function showSnackbar(message)
        {
            const snackbar = document.getElementById("snackbar");
            snackbar.textContent = message;
            snackbar.classList.add("show");
            setTimeout(() => {
                snackbar.classList.remove("show");
            }, 5000);
        }

        // Unique key based on the page URL path to handle multiple pages
        const scrollKey = `scrollPosition-${window.location.pathname}`;

        // 1. Restore scroll position when the DOM is fully loaded
        window.addEventListener('DOMContentLoaded', () => {
            const savedPosition = sessionStorage.getItem(scrollKey);
            if (savedPosition) {
            // Use standard scrollTo method to position the window
            window.scrollTo(0, parseInt(savedPosition, 10));
            }
        });

        // 2. Save scroll position right before the user leaves or refreshes
        window.addEventListener('beforeunload', () => {
            sessionStorage.setItem(scrollKey, window.scrollY);
        });
    </script>
</body>
</html>