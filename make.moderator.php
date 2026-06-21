<?php
session_start();
include "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$target_user = (int)($data['user_id'] ?? 0);
$sub_id = (int)($data['sub_id'] ?? 0);
$my_id = $_SESSION['user_id'];

if (!$target_user || !$sub_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing data.']);
    exit();
}

// Ensure the requesting user actually has moderator privileges for this specific sub
$chk_query = "SELECT role FROM sub_memberships WHERE user_id=$my_id AND sub_id=$sub_id LIMIT 1";
$chk_res = mysqli_query($conn, $chk_query);
$my_role = mysqli_fetch_assoc($chk_res);

if (!$my_role || $my_role['role'] !== 'moderator') {
    echo json_encode(['status' => 'error', 'message' => 'You do not have permission to modify roles in this community.']);
    exit();
}

// Update the target user as a moderator
$ins = mysqli_prepare($conn, 
    "INSERT INTO sub_memberships (user_id, sub_id, role) 
     VALUES (?, ?, 'moderator') 
     ON DUPLICATE KEY UPDATE role='moderator'"
);
mysqli_stmt_bind_param($ins, "ii", $target_user, $sub_id);

if (mysqli_stmt_execute($ins)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
}
?>