<?php
session_start();

header("Content-Type: application/json");

include "db.php";

if($_SERVER["REQUEST_METHOD"] !== "POST")
{
    echo json_encode([
        "success" => false,
        "message" => "invalid"
    ]);
    exit();
}

if(!isset($_SESSION["user_id"]))
{
    echo json_encode([
        "success" => false,
        "message" => "unauthorized"
    ]);
    exit();
}

if(!isset($_POST["comment_id"]))
{
    echo json_encode([
        "success" => false,
        "message" => "missing comment id"
    ]);
    exit();
}

$user_id = $_SESSION["user_id"];
$comment_id = intval($_POST["comment_id"]);

// Check ownership, and find which subcommunity this comment's post belongs to
$check_query = "SELECT c.user_id, p.sub_id FROM comments c JOIN posts p ON c.post_id = p.id WHERE c.id = ? LIMIT 1";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "i", $comment_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

$comment = mysqli_fetch_assoc($check_result);

if(!$comment)
{
    echo json_encode([
        "success" => false,
        "message" => "not found"
    ]);
    exit();
}

mysqli_stmt_close($check_stmt);

$isOwner = $comment["user_id"] == $user_id;
$isAdmin = ($_SESSION['user_type'] ?? '') === 'admin';

// Check if user is a moderator of the subcommunity this comment's post belongs to
$isModerator = false;
$mod_query = "SELECT role FROM sub_memberships WHERE user_id = ? AND sub_id = ?";
$mod_stmt = mysqli_prepare($conn, $mod_query);
mysqli_stmt_bind_param($mod_stmt, "ii", $user_id, $comment["sub_id"]);
mysqli_stmt_execute($mod_stmt);
$mod_result = mysqli_stmt_get_result($mod_stmt);
if ($mod_row = mysqli_fetch_assoc($mod_result)) {
    $isModerator = ($mod_row['role'] === 'moderator');
}
mysqli_stmt_close($mod_stmt);

if(!$isOwner && !$isAdmin && !$isModerator)
{
    echo json_encode([
        "success" => false,
        "message" => "unauthorized"
    ]);
    exit();
}

// Soft delete comment AND any replies under it
$remove_query = "UPDATE comments SET is_removed = 1 WHERE id = ? OR parent_id = ?";
$remove_stmt = mysqli_prepare($conn, $remove_query);
mysqli_stmt_bind_param($remove_stmt, "ii", $comment_id, $comment_id);

if(mysqli_stmt_execute($remove_stmt))
{
    // Get post id
    $post_query = "SELECT post_id FROM comments WHERE id = ?";
    $post_stmt = mysqli_prepare($conn, $post_query);
    mysqli_stmt_bind_param($post_stmt, "i", $comment_id);
    mysqli_stmt_execute($post_stmt);

    $post_result = mysqli_stmt_get_result($post_stmt);
    $post = mysqli_fetch_assoc($post_result);

    $post_id = $post["post_id"];

    // Count remaining visible comments
    $count_query = "SELECT COUNT(*) AS total FROM comments WHERE post_id = ? AND is_removed = 0";
    $count_stmt = mysqli_prepare($conn, $count_query);
    mysqli_stmt_bind_param($count_stmt, "i", $post_id);
    mysqli_stmt_execute($count_stmt);

    $count_result = mysqli_stmt_get_result($count_stmt);
    $count = mysqli_fetch_assoc($count_result);

    echo json_encode([
        "success" => true,
        "comment_count" => $count["total"]
    ]);

    mysqli_stmt_close($post_stmt);
    mysqli_stmt_close($count_stmt);
}
else
{
    echo json_encode([
        "success" => false
    ]);
}

mysqli_stmt_close($remove_stmt);
mysqli_close($conn);
?>