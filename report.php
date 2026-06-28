<?php
session_start();
include "db.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['message' => 'Not logged in']); 
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

$type = $_POST['type'] ?? ($_GET['type'] ?? null);
if ($type !== 'post' && $type !== 'user' && $type !== 'comment') {
    $type = null;
}

$target_id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
$reason = trim($_POST['reason'] ?? ($_GET['reason'] ?? ''));

if (!$type || !$target_id || empty($reason)) {
    echo json_encode(['message' => 'Invalid report parameters submitted.']); 
    exit();
}

if (!in_array($reason, $allowed_reasons)) {
    echo json_encode(['message' => 'Invalid report reason selected.']);
    exit();
}

$check_stmt = mysqli_prepare($conn, "SELECT id FROM reports WHERE reporter_id = ? AND target_type = ? AND target_id = ?");
mysqli_stmt_bind_param($check_stmt, "isi", $reporter_id, $type, $target_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if(mysqli_num_rows($check_result) > 0) {
    echo json_encode(['message' => 'You have already reported this.']);
    exit();
}
mysqli_stmt_close($check_stmt);


$stmt = mysqli_prepare($conn, "INSERT INTO reports (reporter_id, target_type, target_id, reason) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "isis", $reporter_id, $type, $target_id, $reason);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['message' => 'Report submitted successfully. Thank you.']);
} else {
    echo json_encode(['message' => 'Could not save report. Please try again later.']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>