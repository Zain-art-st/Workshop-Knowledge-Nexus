<?php
session_start();
include "db.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['error' => 'not logged in']); 
    exit(); 
}

$post_id = (int)($_GET['post_id'] ?? 0);
$dir = $_GET['dir'] ?? '';

if (!$post_id || !in_array($dir, ['up', 'down'])) {
    echo json_encode(['error' => 'invalid']); 
    exit();
}

if ($dir === 'up') {
    mysqli_query($conn, "UPDATE posts SET upvotes = upvotes + 1 WHERE id = $post_id");
} else {
    mysqli_query($conn, "UPDATE posts SET downvotes = downvotes + 1 WHERE id = $post_id");
}

$votes_query = mysqli_query($conn, "SELECT upvotes FROM posts WHERE id = $post_id");
$row = mysqli_fetch_assoc($votes_query);

echo json_encode([
    'votes' => (int)($row['upvotes'] ?? 0)
]);
?>