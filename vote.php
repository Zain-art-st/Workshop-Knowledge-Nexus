<?php
session_start();
include "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Login required"]);
    exit();
}

$user_id = $_SESSION['user_id'];

$post_id = (int) ($_GET['post_id'] ?? 0);
$dir = $_GET['dir'] ?? '';

if (!$post_id || !in_array($dir, ['up', 'down'])) {
    echo json_encode(["error" => "Invalid request"]);
    exit();
}

$q = mysqli_query(
    $conn,
    "SELECT upvote_users, downvote_users
FROM posts
WHERE id=$post_id
LIMIT 1"
);

$post = mysqli_fetch_assoc($q);

if (!$post) {
    echo json_encode(["error" => "Post not found"]);
    exit();
}

$up = json_decode($post['upvote_users'] ?? '[]', true);
$down = json_decode($post['downvote_users'] ?? '[]', true);

$up = is_array($up) ? $up : [];
$down = is_array($down) ? $down : [];

/* TOGGLE LOGIC */
if ($dir === 'up') {

    if (in_array($user_id, $up)) {
        // UNVOTE
        $up = array_values(array_diff($up, [$user_id]));
    } else {
        // UPVOTE
        $up[] = $user_id;

        // REMOVE DOWNVOTE
        $down = array_values(array_diff($down, [$user_id]));
    }

} else {

    if (in_array($user_id, $down)) {
        // UNVOTE
        $down = array_values(array_diff($down, [$user_id]));
    } else {
        // DOWNVOTE
        $down[] = $user_id;

        // REMOVE UPVOTE
        $up = array_values(array_diff($up, [$user_id]));
    }

}

$upvotes = count($up);
$downvotes = count($down);

$up_json = json_encode($up);
$down_json = json_encode($down);

$stmt = mysqli_prepare(
    $conn,
    "UPDATE posts
SET upvote_users=?,
downvote_users=?,
upvotes=?,
downvotes=?
WHERE id=?"
);

mysqli_stmt_bind_param(
    $stmt,
    "ssiii",
    $up_json,
    $down_json,
    $upvotes,
    $downvotes,
    $post_id
);

mysqli_stmt_execute($stmt);

$user_vote = 'none';

if (in_array($user_id, $up)) {
    $user_vote = 'up';
}

if (in_array($user_id, $down)) {
    $user_vote = 'down';
}

echo json_encode([
    "upvotes" => $upvotes,
    "downvotes" => $downvotes,
    "user_vote" => $user_vote
]);