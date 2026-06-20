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

// Check ownership
$check_query = "SELECT user_id FROM comments WHERE id = ? LIMIT 1";
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

// Only owner can delete
if($comment["user_id"] != $user_id)
{
    mysqli_stmt_close($check_stmt);

    echo json_encode([
        "success" => false,
        "message" => "unauthorized"
    ]);
    exit();
}

mysqli_stmt_close($check_stmt);

// Soft delete
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