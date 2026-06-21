<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid data"]);
    exit();
}

$reporter_id = $_SESSION['user_id'];
$target_type = mysqli_real_escape_string($conn, $data['target_type'] ?? 'user');
$target_id   = (int)($data['target_id'] ?? 0);
$reason      = mysqli_real_escape_string($conn, trim($data['reason'] ?? ''));

if ($target_id && $reason) {
    $stmt = mysqli_prepare($conn, "INSERT INTO reports (reporter_id, target_type, target_id, reason) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isis", $reporter_id, $target_type, $target_id, $reason);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
}
?>