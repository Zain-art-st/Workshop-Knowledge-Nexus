<?php
session_start();
include "db.php";

$post_id = intval($_GET['id']);

// Fetch post with author info
$post_query = "SELECT p.*, u.username, u.profile_photo FROM posts p JOIN users u ON p.user_id = u.id WHERE p.id = ?";
$post_stmt = mysqli_prepare($conn, $post_query);
mysqli_stmt_bind_param($post_stmt, "i", $post_id);
mysqli_stmt_execute($post_stmt);
$post_result = mysqli_stmt_get_result($post_stmt);
$post = mysqli_fetch_assoc($post_result);

if(!$post)
    {
    die("Post not found");
    }

// Fetch comments with author info
$comment_query = "SELECT c.*, u.username, u.profile_photo FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? AND c.is_removed = 0 ORDER BY c.created_at ASC";
$comment_stmt = mysqli_prepare($conn, $comment_query);
mysqli_stmt_bind_param($comment_stmt, "i", $post_id);
mysqli_stmt_execute($comment_stmt);
$comments_result = mysqli_stmt_get_result($comment_stmt);
$comments = [];
while($comment = mysqli_fetch_assoc($comments_result))
    {
    $comments[] = $comment;
    }

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - ScholarSpace</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .post-container {max-width: 700px; margin: 0 auto; padding: 0 20px; position: relative;}
        .post-card {width: 100%; margin-bottom: 20px;}

        .post-header {
            display: flex;
            gap: 12px;
            padding: 16px 20px;
            align-items: center;
        }

        .post-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .post-header-info {
            flex: 1;
        }

        .post-username {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-main);
        }

        .post-time {
            font-size: 12px;
            color: var(--text-muted);
        }

        .post-title {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 700;
            padding: 0 20px 12px 20px;
            color: var(--text-main);
            line-height: 1.4;
        }

        .post-content {
            padding: 0 20px 12px 20px;
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .post-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            margin: 12px 0;
            border-radius: 10px;
        }

        .post-actions {
            display: flex;
            gap: 8px;
            padding: 12px 20px;
            border-top: 1px solid var(--card-border);
            border-bottom: 1px solid var(--card-border);
        }

        .vote-group {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255,255,255,.06);
            border-radius: 20px;
            padding: 4px 10px;
            flex-shrink: 0;
        }

        .vote-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 14px;
            padding: 0;
            transition: color 0.2s;
        }

        .vote-btn:hover {
            color: var(--accent);
        }

        .vote-count {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            min-width: 20px;
            text-align: center;
        }

        .action-btn {
            background: rgba(255,255,255,.06);
            border: none;
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 12px;
            color: var(--text-muted);
            cursor: pointer;
            font-family: var(--font-body);
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: rgba(79,142,247,.15);
            color: var(--accent);
        }

        .comments-section {
            padding: 20px;
        }

        .comments-header {
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--text-main);
        }

        .comment-item {
            display: flex;
            gap: 12px;
            padding: 16px 0;
            border-bottom: 1px solid var(--card-border);
        }

        .comment-item:last-child {
            border-bottom: none;
        }

        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent2), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .comment-body {
            flex: 1;
        }

        .comment-header {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 6px;
        }

        .comment-username {
            font-weight: 600;
            font-size: 13px;
            color: var(--text-main);
        }

        .comment-time {
            font-size: 11px;
            color: var(--text-muted);
        }

        .comment-content {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .comment-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }


        .comment-reply-btn {
            background: rgba(255, 255, 255, .06);
            border: none;
            border-radius: 20px;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 12px;
            padding: 4px 10px;
            transition: color 0.2s;
            height: 36px;
        }

        .comment-reply-btn:hover {
            color: var(--accent);
        }

        .add-comment-form {
            padding: 16px 20px;
            border-top: 1px solid var(--card-border);
            display: flex;
            gap: 8px;
        }

        .add-comment-form textarea {
            flex: 1;
            background: rgba(255,255,255,.06);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 10px;
            color: var(--text-main);
            font-family: var(--font-body);
            font-size: 13px;
            resize: none;
            outline: none;
            transition: border-color 0.2s;
        }

        .add-comment-form textarea:focus {
            border-color: var(--accent);
            background: rgba(79, 142, 247,.1);
        }

        .add-comment-form button {
            background: var(--accent);
            border: none;
            border-radius: 10px;
            padding: 10px 16px;
            color: white;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .add-comment-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79,142,247,.3);
        }

        .no-comments {
            text-align: center;
            padding: 32px 20px;
            color: var(--text-muted);
            font-size: 14px;
        }

        .post-link {color: var(--accent); text-decoration: none; font-weight: 500; display: inline-block; margin: 5px 0 10px 20px; transition: 0.2s;}
        .post-link:hover {color: #6fa8ff; text-decoration: underline;}
        .post-link:visited {color: #b78cff;}
        .child-comments {margin-top: 12px;}
        .child-comment {margin-left: 55px; padding-left: 14px; border-left: 2px solid rgba(255, 255, 255, .08); border-bottom: none;}
        .reply-box {margin-top: 12px; display: flex; flex-direction: column; gap: 10px;}
        .reply-box textarea {width: 100%; min-height: 80px; padding: 12px; border-radius: 12px; background: rgba(255, 255, 255, .05); border: 1px solid var(--card-border); color: white; resize: none}
        .reply-actions {display: flex; justify-content: flex-end; gap: 10px;}
        .reply-cancel {background: none; border: none; color: var(--text-muted); cursor: pointer}
        .reply-submit {background: var(--accent); border: none; color: white; padding: 8px 18px; border-radius: 10px; cursor:pointer}
        .back-btn {position: absolute; left: -30px; top: 18px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; text-decoration: none; color: var(--text-main); font-size: 28px; transition: .2s;}
        .back-btn:hover {background: rgba(255, 255, 255, .08); color: var(--accent);}
        .comment-menu {position: relative; background: rgba(255, 255, 255, .06); border: none; border-radius: 20px; color: var(--text-muted); cursor: pointer; font-size: 12px; padding: 0; transition: color 0.2s;}
        .comment-menu-btn {background: none; border: none; color: white; cursor: pointer; font-size: 18px; padding: 6px;}
        .comment-dropdown {display: none; position: absolute; top: 110%; right: 0; min-width: 140px; background: #1e1e35; border: 1px solid rgba(255, 255, 255, .15); border-radius: 10px; overflow: hidden; z-index: 100;}
        .comment-dropdown a {display: block; padding: 10px 14px; color: white; text-decoration: none;}
        .comment-dropdown a:hover {background: rgba(255, 255, 255, .08);}

        .report-modal {display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 9999; justify-content: center; align-items: center;}
        .report-content {width: 600px; max-width: 90%; background: #2d2d2d; border-radius: 30px; padding: 40px; color: white;}
        .report-content h2 {text-align: center; margin-bottom: 10px;}
        .report-content p {text-align: center; margin-bottom: 30px;}
        .confirm-btn {width: 100%; padding: 15px; border: none; border-radius: 30px; margin-top: 20px; font-size: 18px; font-weight: bold; cursor: pointer;}
        .close-btn {float: right; font-size: 28px; cursor: pointer;}

        
    </style>
</head>
<body>
    <div class="stars-bg"></div>
    <div class="sunset-bg"></div>

    <!-- Navbar -->
     <header class="navbar">
        <a href="index.php" class="nav-logo">ScholarSpace</a>
        <div class="nav-search">
            <input type="text" placeholder="Search anything...">
        </div>
        <div class="nav-right">
            <?php if(isset($_SESSION['user_id'])) : ?>
                <span class="nav-welcome">Welcome <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
            <?php endif; ?>
            <input type="checkbox" id="profile-toggle" class="profile-toggle-checkbox">
            <label for="profile-toggle" class="profile-avatar">
                <?php echo isset($_SESSION['user_id']) ? strtoupper(substr($_SESSION['username'], 0, 1)) : '👤'; ?>
            </label>
            <div class="profile-menu">
                <?php if(isset($_SESSION['user_id'])) : ?>
                    <a href="#">My Profile</a>
                    <a href="#">Settings</a>
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
        <div class="post-container">
            <a href="subcommunity.php?id=<?php echo $post['sub_id']; ?>" class="back-btn">&larr;</a>
            <!-- Post Card -->
            <div class="card post-card">
                <!-- Post Header -->
                <div class="post-header">
                    <div class="post-avatar">
                    <?php echo strtoupper(substr($post['username'], 0, 1)); ?>
                    </div>
                    <div class="post-header-info">
                        <div class="post-username"><?php echo htmlspecialchars($post['username']); ?></div>
                        <div class="post-time">
                            <?php
                                $time_diff = time() - strtotime($post['created_at']);
                                if($time_diff < 60) echo "just now";
                                else if($time_diff < 3600) echo floor($time_diff / 60) . " min ago";
                                else if($time_diff < 86400) echo floor($time_diff / 3600) . " hours ago";
                                else echo floor($time_diff / 86400) . " days ago";
                            ?>
                        </div>
                    </div>
                </div>


             <!-- Post Title & Content -->
              <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>

              <?php if($post['content']) : ?>
                <div class="post-content"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
              <?php endif; ?>

              <!-- Post Image -->
               <?php if($post['image_url']): ?>
                <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="Post image" class="post-image">
               <?php endif; ?>

               <!-- Post Link -->
                <?php if (!empty($post['link_url'])): ?>
                    <a href="<?php echo htmlspecialchars(
                        preg_match('/^https?:\/\//', $post['link_url'])
                        ? $post['link_url']
                        : 'https://' . $post['link_url']
                    ); ?>" class="post-link" target="_blank" rel="noopener noreferrer">
                    <?php echo htmlspecialchars($post['link_url']);?></a>
                <?php endif; ?>

              <!-- Post Actions -->
                <div class="post-actions">
                    <div class="vote-group">
                        <button type="button" class="vote-btn upvote" onclick="vote(<?php echo $post['id']; ?>, 'upvote')">▲</button>
                        <span class="vote-count" id="upvotes-<?php echo $post['id'];?>"><?php echo $post['upvotes']; ?></span>
                        <span style="width:1px; height: 20px; background: rgba(255,255,255,.25)"></span>
                        <span class="vote-count" id="downvotes-<?php echo $post['id'];?>"><?php echo $post['downvotes'];?></span>
                        <button type="button" class="vote-btn downvote" onclick="vote(<?php echo $post['id']; ?>, 'downvote')">▼</button>
                    </div>
                    <button class="action-btn">💬 <?php echo count($comments); ?> Comments</button>

                </div> 
            </div>

        

        <!-- Comments Section -->
         <div class="card" style="margin-top: 20px;">
            <div class="comments-section">
                <div class="comments-header">Comments</div>

                <?php if(count($comments) === 0): ?>
                    <div class="no-comments">No comments yet. Be the first!</div>
                <?php else: ?>

                    <!-- Split parents and children -->
                    <?php
                    $parent_comments = [];
                    $child_comments = [];

                    foreach ($comments as $comment) {
                        if (empty($comment['parent_id'])) {
                            $parent_comments[] = $comment;
                        } else {
                            $child_comments[$comment['parent_id']][] = $comment;
                        }
                    }
                    ?>
                    <?php foreach($parent_comments as $comment): ?>
                        <div class="comment-item" data-comment-id="<?php echo $comment['id']; ?>">
                            <div class="comment-avatar">
                                <?php echo strtoupper(substr($comment['username'], 0, 1)); ?>
                            </div>
                            <div class="comment-body">
                                <div class="comment-header">
                                    <span class="comment-username">
                                        <?php echo htmlspecialchars($comment['username']); ?>
                                    </span>
                                    <span class="comment-time">
                                    <?php
                                    $time_diff = time() - strtotime($comment['created_at']);
                                    if($time_diff < 60) echo "just now";
                                    else if($time_diff < 3600) echo floor($time_diff / 60) . " min ago";
                                    else if($time_diff < 86400) echo floor($time_diff / 3600) . " hours ago";
                                    else echo floor($time_diff / 86400) . " days ago";
                                    ?>
                                    </span>
                                </div>
                                <div class="comment-content">
                                    <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                </div>
                                <div class="comment-actions">
                                    <div class="vote-group">
                                        <button type="button" class="vote-btn upvote" onclick="voteComment(<?php echo $comment['id']; ?>, 'upvote')">▲</button>
                                        <span class="vote-count" id="comment-upvotes-<?php echo $comment['id']; ?>">
                                            <?php echo $comment['upvotes']; ?>
                                        </span>

                                        <span style="width:1px; height: 20px; background: rgba(255,255,255,.25)"></span>

                                        <span class="vote-count" id="comment-downvotes-<?php echo $comment['id']; ?>">
                                            <?php echo $comment['downvotes']; ?>
                                        </span>
                                        <button type="button" class="vote-btn downvote" onclick="voteComment(<?php echo $comment['id']; ?>, 'downvote')">▼</button>
                                    </div>

                                    <button type="button" class="comment-reply-btn" onclick="showReplyBox(<?php echo $comment['id']; ?>, 
                                    '<?php echo htmlspecialchars($comment['username']); ?>')">Reply</button>

                                    <div class="comment-menu">
                                        <button type="button" class="comment-menu-btn" onclick="toggleCommentMenu(this)">
                                            &#8943;
                                        </button>
                                    
                                        <div class="comment-dropdown">
                                            <a href="#" onclick="openCommentReportModal(<?php echo $comment['id']; ?>); return false;">Report</a>
                                            <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $comment['user_id'] || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'))): ?>
                                                <a href="#" onclick="openDeleteCommentModal(<?php echo $comment['id']; ?>); return false;">Delete</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div id="reply-container-<?php echo $comment['id']; ?>"></div>

                                <!-- Child comments -->
                                <?php
                                if(isset($child_comments[$comment['id']])) : ?>
                                    <div class="child-comments">
                                    <?php foreach($child_comments[$comment['id']] as $child): ?>
                                        <div class="comment-item" data-comment-id="<?php echo $child['id']; ?>"
                                        data-parent-id="<?php echo $child['parent_id']; ?>">
                                            <div class="comment-avatar">
                                                <?php
                                                echo strtoupper(substr($child['username'], 0, 1));
                                                ?>
                                            </div>

                                            <div class="comment-body">
                                                <div class="comment-header">
                                                    <span class="comment-username">
                                                        <?php echo htmlspecialchars($child['username']); ?>
                                                    </span>

                                                    <span class="comment-time">
                                                        <?php
                                                            $time_diff = time() - strtotime($child['created_at']);
                                                            if ($time_diff < 60) echo "just now";
                                                            else if ($time_diff < 3600) echo floor($time_diff / 60) . " min ago";
                                                            else if ($time_diff < 86400) echo floor($time_diff / 3600) . " hours ago";
                                                            else echo floor($time_diff / 86400) . " days ago";
                                                            ?>
                                                    </span>
                                                </div>

                                                <div class="comment-content">
                                                    <?php echo nl2br(htmlspecialchars($child['content'])); ?>
                                                </div>

                                                <div class="comment-actions">
                                                    <div class="vote-group">
                                                        <button type="button" class="vote-btn upvote" onclick="voteComment(<?php echo $child['id']; ?>, 'upvote')">▲</button>
                                                        <span class="vote-count" id="comment-upvotes-<?php echo $child['id']; ?>">
                                                            <?php echo $child['upvotes']; ?>
                                                        </span>

                                                        <span style="width:1px; height: 20px; background: rgba(255,255,255,.25)"></span>

                                                        <span class="vote-count" id="comment-downvotes-<?php echo $child['id']; ?>">
                                                            <?php echo $child['downvotes']; ?>
                                                        </span>
                                                        <button type="button" class="vote-btn downvote" onclick="voteComment(<?php echo $child['id']; ?>, 'downvote')">▼</button>   
                                                    </div>
                                                    <button type="button" class="comment-reply-btn" onclick="showReplyBox(
                                                        <?php echo $child['id']; ?>,
                                                        '<?php echo htmlspecialchars($child['username']); ?>'
                                                        )">Reply</button>

                                                        <div class="comment-menu">
                                                            <button type="button" class="comment-menu-btn" onclick="toggleCommentMenu(this)">
                                                                &#8943
                                                            </button>

                                                            <div class="comment-dropdown">
                                                                <a href="#" onclick="openCommentReportModal(<?php echo $child['id']; ?>); return false;">
                                                                    Report
                                                                </a>

                                                                <?php if(isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $child['user_id'] || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'))): ?>
                                                                    <a href="#" onclick="openDeleteCommentModal(<?php echo $child['id']; ?>); return false;">
                                                                        Delete
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                </div>
                                                <div id="reply-container-<?php echo $child['id']; ?>"></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div> 
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                        
            <!-- Add Comment Form -->
            <?php if(isset($_SESSION['user_id'])): ?>
                <form action="add_comment.php" method="POST" class="add-comment-form">
                    <textarea name="content" placeholder="Add a comment..." required style="min-height: 40px;"></textarea>
                    <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                    <input type="hidden" name="parent_id" value="">
                    <button type="submit">Post</button>
                </form>
            <?php else: ?>
                <div style="padding: 16px 20px; border-top: 1px solid var(--card-border); text-align: center; color: var(--text-muted); font-size: 13px;">
                    <a href="login.php" style="color: var(--accent); text-decoration: none;">Log in</a> to comment
                </div>
            <?php endif; ?>
         </div>
        </div>
    </div>

    <div id="deleteCommentModal" class="report-modal">
        <div class="report-content">
            <h2>Delete Comment</h2>
            <p>Are you sure you want to delete this comment?</p>
            <input type="hidden" id="deleteCommentId">
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button class="confirm-btn" onclick="deleteComment()">
                    Delete
                </button>
                <button class="confirm-btn" onclick="closeDeleteCommentModal()">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        function vote(postId, voteType) {
            <?php if(!isset($_SESSION['user_id'])): ?>
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
                    alert(data.message);
                }
            })
            .catch(error => console.error(error));
        }

        function voteComment(commentId, voteType)
        {
            <?php if(!isset($_SESSION['user_id'])): ?>
                window.location.href = 'login.php';
                return;
            <?php endif; ?>

            fetch("vote_comment.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body:
                    "comment_id=" + encodeURIComponent(commentId) +
                    "&vote_type=" + encodeURIComponent(voteType)
            })
            .then(response => response.json())
            .then(data => {

            if(data.success)
            {
                document.getElementById(
                    "comment-upvotes-" + commentId
                ).textContent = data.upvotes;

                document.getElementById(
                    "comment-downvotes-" + commentId
                ).textContent = data.downvotes;
            }
            else
            {
                alert(data.message);
            }
            })
            .catch(error => {
                console.error(error);
            });
        }

        function showReplyBox(commentId, username)
        {
            document.querySelectorAll('.reply-box').forEach(box => box.remove());
            const container = document.getElementById('reply-container-' + commentId);
            container.innerHTML =
            `
            <form class="reply-box" action="add_comment.php" method="POST">
            <textarea name="content" placeholder="Reply to @${username}" required>@${username} </textarea>
            <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
            <input type="hidden" name="parent_id" value="${commentId}">
            <div class="reply-actions">
            <button type="button" class="reply-cancel">
            Cancel
            </button>

            <button class="reply-submit">
            Comment
            </button>
            </div>
            </form>
            `;

            container.querySelector('.reply-cancel').onclick = function() {container.innerHTML = '';};
        }

        function toggleCommentMenu(button)
        {
            const menu = button.nextElementSibling;

            document.querySelectorAll(".comment-dropdown").forEach(m => {
                if(m !== menu)
                {
                    m.style.display = "none";
                }
            });

            menu.style.display = menu.style.display === "block" ? "none" : "block";
        }

        function openDeleteCommentModal(commentId)
        {
            document.getElementById("deleteCommentId").value = commentId;
            document.getElementById("deleteCommentModal").style.display = "flex";
        }

        function closeDeleteCommentModal()
        {
            document.getElementById("deleteCommentModal").style.display = "none";
        }

        function deleteComment()
        {
            const id = document.getElementById("deleteCommentId").value;

            fetch("delete_comment.php", 
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body:
                "comment_id=" + encodeURIComponent(id)
            })
            .then(response => response.json())
            .then(data=>{
                if(data.success)
                {
                    closeDeleteCommentModal();
                    document.querySelector(`[data-comment-id="${id}"]`)?.remove();

                    // Update comment counter
                    const commentBtn = document.querySelector(".action-btn");

                    if(commentBtn)
                    {
                        commentBtn.innerHTML = `💬 ${data.comment_count} Comments`;
                    }
                }
                else
                {
                    alert(data);
                }
            })
            .catch(error=>{
                console.error(error);
            });
        }
    </script>
</body>
</html>