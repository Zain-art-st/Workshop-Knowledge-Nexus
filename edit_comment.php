<?php

session_start();

header("Content-Type: application/json");

include "db.php";

if(!isset($_SESSION["user_id"]))
{
    exit(json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]));
}

$id = intval($_POST["comment_id"]);
$content = trim($_POST["content"]);

if(empty($content))
{
    exit(json_encode([
        "success" => false,
        "message" => "Comment cannot be empty"
    ]));
}

$user = $_SESSION["user_id"];

$edit_query = "UPDATE comments SET content = ? WHERE id = ? AND user_id = ? AND is_removed = 0";
$edit_stmt = mysqli_prepare($conn, $edit_query);
mysqli_stmt_bind_param($edit_stmt, "sii", $content, $id, $user);

if(mysqli_stmt_execute($edit_stmt))
{
    echo json_encode([
        "success" => true,
        "message" => "Update successful"
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