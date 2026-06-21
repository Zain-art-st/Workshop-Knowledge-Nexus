<?php

session_start();
include "db.php";

// User must be logged in
if(!isset($_SESSION['user_id']))
    {
    header("Location: login.php");
    exit();
    }

$user_id = $_SESSION['user_id'];
$post_id = intval($_POST['post_id']);
$content = trim($_POST['content']);

// Validate input
if($post_id <= 0 || empty($content))
    {
    die("Invalid comment");
    }

// Check if post exists
$check_post_query = "SELECT id FROM posts WHERE id = ?";
$check_post_stmt = mysqli_prepare($conn, $check_post_query);
mysqli_stmt_bind_param($check_post_stmt, "i", $post_id);
mysqli_stmt_execute($check_post_stmt);
$check_post_result = mysqli_stmt_get_result($check_post_stmt);

if(mysqli_num_rows($check_post_result) == 0)
    {
    die("Post not found");
    }

// Insert comments
$parent_id = NULL;

if(!empty($_POST['parent_id']))
{
    $clicked_id = intval($_POST['parent_id']);

    $check_query = "SELECT parent_id FROM comments WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $clicked_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

    if($result)
    {
        $parent_id = $result['parent_id'] ?: $clicked_id;
    }
}

$comment_query = "INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)";
$comment_stmt = mysqli_prepare($conn, $comment_query);
mysqli_stmt_bind_param($comment_stmt, "iisi", $post_id, $user_id, $content, $parent_id);

if(mysqli_stmt_execute($comment_stmt))
    {
    header("Location: post.php?id=" . $post_id);
    exit();
    }
else
    {
    echo "Failed to add comment";
    }
?>