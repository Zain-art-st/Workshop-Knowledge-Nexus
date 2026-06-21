<?php

session_start();

header("Content-Type: application/json");

include "db.php";

if($_SERVER["REQUEST_METHOD"] !== "POST")
{
    echo json_encode([
        "success" => false,
        "message" => "Invalid request"
    ]);
    exit();
}

if(!isset($_SESSION["user_id"]))
{
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);
    exit();
}

$user_id = $_SESSION["user_id"];
$post_id = intval($_POST["post_id"] ?? 0);
$title = trim($_POST["title"] ?? "");
$content = trim($_POST["content"] ?? "");
$image = !empty($_POST['image_url'])? trim($_POST['image_url']) : null;
$link = !empty($_POST['link_url'])? trim($_POST['link_url']) : null;


if($post_id <= 0 || empty($title))
{
    echo json_encode([
        "success" => false,
        "message" => "Missing data"
    ]);
    exit();
}

// Verify ownership
$post_query = "SELECT user_id FROM posts WHERE id = ? AND is_removed = 0 LIMIT 1";
$post_stmt = mysqli_prepare($conn, $post_query);
mysqli_stmt_bind_param($post_stmt, "i", $post_id);
mysqli_stmt_execute($post_stmt);
$result = mysqli_stmt_get_result($post_stmt);
$post = mysqli_fetch_assoc($result);

if(!$post)
{
    echo json_encode([
        "success" => false,
        "message" => "Post not found"
    ]);
    exit();
}

if($post["user_id"] != $user_id)
{
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);
    exit();
}

$update_query = "UPDATE posts SET title = ?, content = ?, image_url = ?, link_url = ? WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($update_stmt, "ssssi", $title, $content, $image, $link, $post_id);

if(mysqli_stmt_execute($update_stmt))
{
    echo json_encode([
        "success" => true,
        "title" => $title,
        "content" => $content
    ]);
}
else
{
    echo json_encode([
        "success" => false,
    ]);
}

mysqli_close($conn);
?>