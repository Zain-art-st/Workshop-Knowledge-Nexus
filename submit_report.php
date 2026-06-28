<?php
session_start();
include "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid data"]);
    exit();
}

$allowed_reasons = [
    "Unwanted commercial content or spam",
    "Pornography or sexually explicit material",
    "Hate speech or graphic violence",
    "Harassment or bullying",
    "Misinformation"
];

$reporter_id = $_SESSION['user_id'];
$target_type = strtolower(trim($data['target_type'] ?? ''));
$target_id   = (int)($data['target_id'] ?? 0);
$reason      = trim($data['reason'] ?? '');

if (!in_array($target_type, ['post', 'comment', 'user'])) {
    echo json_encode(["status" => "error", "message" => "Invalid target type"]);
    exit();
}

if (!$target_id || empty($reason)) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit();
}

if (!in_array($reason, $allowed_reasons)) {
    echo json_encode(["status" => "error", "message" => "Invalid report reason selected"]);
    exit();
}
$check_stmt = mysqli_prepare($conn, "SELECT id FROM reports WHERE reporter_id = ? AND target_type = ? AND target_id = ? LIMIT 1");
mysqli_stmt_bind_param($check_stmt, "isi", $reporter_id, $target_type, $target_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) > 0) {
    echo json_encode(["status" => "error", "message" => "You have already reported this."]);
    mysqli_stmt_close($check_stmt);
    exit();
}
mysqli_stmt_close($check_stmt);
$stmt = mysqli_prepare($conn, "INSERT INTO reports (reporter_id, target_type, target_id, reason) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "isis", $reporter_id, $target_type, $target_id, $reason);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "A database error occurred. Please try again."]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>