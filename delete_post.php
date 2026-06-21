<?php

session_start();
include "db.php";

if(!isset($_SESSION['user_id']))
{
    exit("Login required");
}

$user_id = $_SESSION['user_id'];
$post_id = intval($_POST['post_id']);

$post_query = "SELECT user_id, sub_id FROM posts WHERE id=?";
$post_stmt = mysqli_prepare($conn, $post_query);
mysqli_stmt_bind_param($post_stmt, "i", $post_id);
mysqli_stmt_execute($post_stmt);
$post_result = mysqli_stmt_get_result($post_stmt);
$post = mysqli_fetch_assoc($post_result);

if(!$post)
{
    exit("Post not found");
}

$isOwner = $post['user_id'] == $user_id;
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Check if user is a moderator of this post's subcommunity
$isModerator = false;
$mod_query = "SELECT role FROM sub_memberships WHERE user_id = ? AND sub_id = ?";
$mod_stmt = mysqli_prepare($conn, $mod_query);
mysqli_stmt_bind_param($mod_stmt, "ii", $user_id, $post['sub_id']);
mysqli_stmt_execute($mod_stmt);
$mod_result = mysqli_stmt_get_result($mod_stmt);
if ($mod_row = mysqli_fetch_assoc($mod_result)) {
    $isModerator = ($mod_row['role'] === 'moderator');
}

if(!$isOwner && !$isAdmin && !$isModerator)
{
    exit("Unauthorized");
}

/*
Soft delete post
*/
try {
    $delete_query = "UPDATE posts SET is_removed = 1 WHERE id=?";
    $delete_stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($delete_stmt, "i", $post_id);

    if (mysqli_stmt_execute($delete_stmt)) {
        echo "success";
    } else {
        echo "Delete failed";
    }

    // Soft delete all comments under this post
    $comment_query = "
        UPDATE comments
        SET is_removed = 1
        WHERE post_id = ?
    ";

    $comment_stmt = mysqli_prepare($conn, $comment_query);
    mysqli_stmt_bind_param($comment_stmt, "i", $post_id);

    if (!mysqli_stmt_execute($comment_stmt)) {
        throw new Exception("Failed to delete comments");
    }
} catch (Exception $e) 
{
    mysqli_rollback($conn);
    echo "Delete failed";
}

mysqli_close($conn);