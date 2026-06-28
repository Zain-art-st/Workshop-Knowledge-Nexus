<?php
session_start();
include "db.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'not logged in']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$post_id = (int)($_GET['post_id'] ?? 0);
$dir     = $_GET['dir'] ?? '';

if (!$post_id || !in_array($dir, ['up', 'down'])) {
    echo json_encode(['error' => 'invalid']);
    exit();
}

// Check user
$chk = mysqli_prepare($conn,
    "SELECT vote_type FROM post_votes WHERE user_id=? AND post_id=? LIMIT 1");
mysqli_stmt_bind_param($chk, "ii", $user_id, $post_id);
mysqli_stmt_execute($chk);
$res      = mysqli_stmt_get_result($chk);
$existing = mysqli_fetch_assoc($res);

if ($existing) {
    if ($existing['vote_type'] === $dir) {
        // Same vote again
        $del = mysqli_prepare($conn,
            "DELETE FROM post_votes WHERE user_id=? AND post_id=?");
        mysqli_stmt_bind_param($del, "ii", $user_id, $post_id);
        mysqli_stmt_execute($del);
        $col = $dir === 'up' ? 'upvotes' : 'downvotes';
        mysqli_query($conn,
            "UPDATE posts SET $col = GREATEST($col - 1, 0) WHERE id = $post_id");
    } else {
        // Switching vote direction
        $upd = mysqli_prepare($conn,
            "UPDATE post_votes SET vote_type=? WHERE user_id=? AND post_id=?");
        mysqli_stmt_bind_param($upd, "sii", $dir, $user_id, $post_id);
        mysqli_stmt_execute($upd);
        if ($dir === 'up') {
            mysqli_query($conn,
                "UPDATE posts SET upvotes=upvotes+1, downvotes=GREATEST(downvotes-1,0) WHERE id=$post_id");
        } else {
            mysqli_query($conn,
                "UPDATE posts SET downvotes=downvotes+1, upvotes=GREATEST(upvotes-1,0) WHERE id=$post_id");
        }
    }
} else {
    // First vote
    $ins = mysqli_prepare($conn,
        "INSERT INTO post_votes (user_id, post_id, vote_type) VALUES (?,?,?)");
    mysqli_stmt_bind_param($ins, "iis", $user_id, $post_id, $dir);
    mysqli_stmt_execute($ins);
    $col = $dir === 'up' ? 'upvotes' : 'downvotes';
    mysqli_query($conn, "UPDATE posts SET $col=$col+1 WHERE id=$post_id");
}

// Return updated counts 
$row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT upvotes, downvotes FROM posts WHERE id=$post_id LIMIT 1"));
$uservote = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT vote_type FROM post_votes WHERE user_id=$user_id AND post_id=$post_id LIMIT 1"));

echo json_encode([
    'votes'     => (int)($row['upvotes']   ?? 0),
    'downvotes' => (int)($row['downvotes'] ?? 0),
    'uservote'  => $uservote['vote_type']  ?? null,
]);
