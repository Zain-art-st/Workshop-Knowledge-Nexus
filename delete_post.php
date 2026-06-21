<?php
session_start();
include "db.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$post_id   = (int)($_GET['id'] ?? 0);
$action    = $_GET['action'] ?? 'delete'; 

if (!$post_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
    exit();
}

$post = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT id, user_id FROM posts WHERE id = $post_id AND is_removed = 0 LIMIT 1"));

if (!$post) {
    echo json_encode(['success' => false, 'error' => 'Post not found']);
    exit();
}

if ($action === 'delete') {
    if ((int)$post['user_id'] !== (int)$user_id) {
        echo json_encode(['success' => false, 'error' => 'You can only delete your own posts']);
        exit();
    }
    $del = mysqli_query($conn, "DELETE FROM posts WHERE id = $post_id AND user_id = $user_id");
    if ($del) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }

} elseif ($action === 'remove') {
    if ($user_type !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit();
    }
    $rem = mysqli_query($conn, "UPDATE posts SET is_removed = 1 WHERE id = $post_id");
    if ($rem) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>