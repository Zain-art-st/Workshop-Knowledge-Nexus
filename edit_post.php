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
$link = trim($_POST["link_url"] ?? "");

$remove_image = isset($_POST["remove_image"]) && $_POST["remove_image"] == 1;


if($post_id <= 0 || empty($title))
{
    echo json_encode([
        "success" => false,
        "message" => "Missing data"
    ]);
    exit();
}

// Verify ownership (also fetch current image so we know what's on disk)
$post_query = "SELECT user_id, image_url FROM posts WHERE id = ? AND is_removed = 0 LIMIT 1";
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

/* Start from the post's actual current image */

$image = $post["image_url"];

/* Remove image, if requested */

if ($remove_image) {
    if (!empty($image) && file_exists($image)) {
        unlink($image);
    }
    $image = null;
}

/* Upload new image (replaces whatever was there, including a just-removed one) */

if (isset($_FILES["image_url"]) && $_FILES["image_url"]["error"] === 0) 
    {
    if (!empty($image) && file_exists($image)) 
    {
        unlink($image);
    }

    $folder = "uploads/";

    if (!is_dir($folder)) 
    {
        mkdir($folder, 0777, true);
    }

    $ext = pathinfo($_FILES["image_url"]["name"], PATHINFO_EXTENSION);

    $filename = uniqid().".".$ext;

    $path =$folder.$filename;

    if (move_uploaded_file($_FILES["image_url"]["tmp_name"], $path)) 
    {
        $image = $path;
    }
}

// Plain assignment now (no COALESCE) since $image already reflects
// keep / remove / replace correctly.

$update_query = "UPDATE posts SET title = ?, content = ?, image_url = ?, link_url = ? WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($update_stmt, "ssssi", $title, $content, $image, $link, $post_id);

if(mysqli_stmt_execute($update_stmt))
{
    echo json_encode([
        "success" => true,
        "title" => $title,
        "content" => $content,
        "link" => $link,
        "image" => $image
    ]);
}
else
{
    echo json_encode([
        "success" => false,
        "message" => "Update failed"
    ]);
}

mysqli_close($conn);
?>