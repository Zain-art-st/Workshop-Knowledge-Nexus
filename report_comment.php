<?php
session_start();

header("Content-Type: application/json");

include "db.php";

// Must Login
if(!isset($_SESSION["user_id"]))
{
    echo json_encode([
        "success" => false,
        "message" => "Please login first"
    ]);
    exit();
}

// Only POST
if($_SERVER["REQUEST_METHOD"] !== "POST")
{
    echo json_encode([
        "success" => false,
        "message" => "Invalid request"
    ]);
    exit();
}

$reporter_id = $_SESSION["user_id"];
$comment_id = intval($_POST["comment_id"] ?? 0);
$reason = trim($_POST["reason"] ?? "");

// Validate
if($comment_id <= 0 || empty($reason))
{
    echo json_encode([
        "success" => false,
        "message" => "Missing information"
    ]);
    exit();
}

// Check comment exists and not removed
$check_query = "SELECT id FROM comments WHERE id = ? AND is_removed = 0 LIMIT 1";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "i", $comment_id);
mysqli_stmt_execute($check_stmt);

$result = mysqli_stmt_get_result($check_stmt);

if(mysqli_num_rows($result) === 0)
{
    echo json_encode([
        "success" => false,
        "message" => "Comment not found"
    ]);
    exit();
}

// Prevent duplicate report
$duplicate_query = "SELECT id FROM reports WHERE reporter_id = ? AND target_type = 'comment' AND target_id = ? LIMIT 1";
$duplicate_stmt = mysqli_prepare($conn, $duplicate_query);
mysqli_stmt_bind_param($duplicate_stmt, "ii", $reporter_id, $comment_id);
mysqli_stmt_execute($duplicate_stmt);

$duplicate_result = mysqli_stmt_get_result($duplicate_stmt);

if(mysqli_num_rows($duplicate_result) > 0)
{
    echo json_encode([
        "success" => false,
        "message" => "You already reported this comment"
    ]);
    exit();
}

// Insert report
$insert_query = "INSERT INTO reports(reporter_id, target_type, target_id, reason) VALUES (?, 'comment', ?, ?)";
$insert_stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($insert_stmt, "iis", $reporter_id, $comment_id, $reason);

if(mysqli_stmt_execute($insert_stmt))
{
    echo json_encode([
        "success" => true,
        "message" => "Comment reported successfully"
    ]);
}
else
{
    echo json_encode([
        "success" => false,
        "message" => "Failed to report comment"
    ]);
}

mysqli_stmt_close($check_stmt);
mysqli_stmt_close($duplicate_stmt);
mysqli_stmt_close($insert_stmt);

mysqli_close($conn);
?>