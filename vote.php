<?php
session_start();
include "db.php";

header("Content-Type: application/json");

if(!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Login required"
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$post_id = intval($_POST['post_id']);
$vote_type = $_POST['vote_type'];

if($vote_type != "upvote" && $vote_type != "downvote")
    {
    echo json_encode([
        "success" => false,
        "message" => "Invalid vote type"
    ]);
    exit();
    }
    
// Check if user already voted
$check_query = "SELECT vote_type FROM post_votes WHERE post_id = ? AND user_id = ?";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "ii", $post_id, $user_id);
mysqli_stmt_execute($check_stmt);
$checkResult = mysqli_stmt_get_result($check_stmt);
$existingVote = mysqli_fetch_assoc($checkResult);


// User never voted
if(!$existingVote)
    {
    $postVote_query = "INSERT INTO post_votes(post_id, user_id, vote_type) VALUES (?,?,?)";
    $postVote_stmt = mysqli_prepare($conn, $postVote_query);
    mysqli_stmt_bind_param($postVote_stmt, "iis", $post_id, $user_id, $vote_type);
    mysqli_stmt_execute($postVote_stmt);

    if($vote_type == "upvote")
        {
           mysqli_query(
            $conn,
            "UPDATE posts
             SET upvotes = upvotes + 1
             WHERE id = $post_id"
        );
        }
    else
        {
            mysqli_query(
            $conn,
            "UPDATE posts
             SET downvotes = downvotes + 1
             WHERE id = $post_id"
        );
        }
    }

// Same vote clicked again
elseif($existingVote['vote_type'] == $vote_type)
    {
    echo json_encode([
        "success" => false,
        "message" => "Already voted"
    ]);
    exit();
    }


// Change Upvote to Downvote
elseif($existingVote['vote_type'] == "upvote" && $vote_type == "downvote")
    {
    $updateVote_query = "UPDATE post_votes SET vote_type = 'downvote' WHERE post_id = ? AND user_id = ?";
    $updateVote_stmt = mysqli_prepare($conn, $updateVote_query);
    mysqli_stmt_bind_param($updateVote_stmt, "ii", $post_id, $user_id);
    mysqli_stmt_execute($updateVote_stmt);
    mysqli_query($conn, "UPDATE posts SET upvotes = upvotes - 1, downvotes = downvotes + 1 WHERE id = $post_id");
    }

// Change Downvote to Upvote
elseif($existingVote['vote_type'] == "downvote" && $vote_type == "upvote")
    {
    $updateVote_query = "UPDATE post_votes SET vote_type = 'upvote' WHERE post_id = ? AND user_id = ?";
    $updateVote_stmt = mysqli_prepare($conn, $updateVote_query);
    mysqli_stmt_bind_param($updateVote_stmt, "ii", $post_id, $user_id);
    mysqli_stmt_execute($updateVote_stmt);
    mysqli_query($conn, "UPDATE posts SET downvotes = downvotes - 1, upvotes = upvotes + 1 WHERE id = $post_id");
}

// Get latest counts
$getVotes_query = "SELECT upvotes, downvotes FROM posts WHERE id = ?";
$getVotes_stmt = mysqli_prepare($conn, $getVotes_query);
mysqli_stmt_bind_param($getVotes_stmt, "i", $post_id);
mysqli_stmt_execute($getVotes_stmt);
$result = mysqli_stmt_get_result($getVotes_stmt);
$row = mysqli_fetch_assoc($result);

echo json_encode([
    "success" => true,
    "upvotes" => $row['upvotes'],
    "downvotes" => $row['downvotes']
]);
?>