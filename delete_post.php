<?php

session_start();
include "db.php";

if(!isset($_SESSION['user_id']))
{
    exit("Login required");
}

$user_id = $_SESSION['user_id'];
$post_id = intval($_POST['post_id']);

$post_query = "SELECT user_id FROM posts WHERE id=?";
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

if(!$isOwner && !$isAdmin)
{
    exit("Unauthorized");
}

/*
Soft delete
*/

$delete_query = "UPDATE posts SET is_removed = 1 WHERE id=?";
$delete_stmt = mysqli_prepare($conn, $delete_query);
mysqli_stmt_bind_param($delete_stmt, "i", $post_id);

if(mysqli_stmt_execute($delete_stmt))
{
    echo "success";
}
else
{
    echo "Delete failed";
}