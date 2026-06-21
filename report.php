<?php
session_start();
include "db.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['message' => 'Not logged in']); 
    exit(); 
}

$reporter_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? null;
if ($type !== 'post' && $type !== 'user') {
    $type = null;
}

$target_id = (int)($_GET['id'] ?? 0);
$reason = trim($_GET['reason'] ?? '');

if (!$type || !$target_id || empty($reason)) {
    echo json_encode(['message' => 'Invalid report parameters submitted.']); 
    exit();
}

$stmt = mysqli_prepare($conn, "INSERT INTO reports (reporter_id, target_type, target_id, reason) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "isis", $reporter_id, $type, $target_id, $reason);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['message' => 'Report submitted successfully. Thank you.']);
} else {
    echo json_encode(['message' => 'Could not save report. Please try again later.']);
}
?>