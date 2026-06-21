<?php
session_start();
include "db.php";

header("Content-Type: application/json");

if(!isset($_SESSION['user_id']))
{
    echo json_encode([
        "success" => false,
        "message" => "Login required"
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$comment_id = intval($_POST['comment_id']);
$vote_type = $_POST['vote_type'];

$voteType_query = "SELECT vote_type FROM comment_votes WHERE user_id = ? AND comment_id = ?";
$voteType_stmt = mysqli_prepare($conn, $voteType_query);
mysqli_stmt_bind_param($voteType_stmt, "ii", $user_id, $comment_id);
mysqli_stmt_execute($voteType_stmt);

$voteType_result = mysqli_stmt_get_result($voteType_stmt);

if($row = mysqli_fetch_assoc($voteType_result))
{
    if($row['vote_type'] == $vote_type)
    {
        echo json_encode([
            "success" => false,
            "message" => "Already voted"
        ]);
        exit();
    }

    $updateVote_query = "UPDATE comment_votes SET vote_type = ? WHERE user_id = ? AND comment_id = ?";
    $updateVote_stmt = mysqli_prepare($conn, $updateVote_query);
    mysqli_stmt_bind_param($updateVote_stmt, "sii", $vote_type, $user_id, $comment_id);
    mysqli_stmt_execute($updateVote_stmt);
}
else
{
    $insertVote_query = "INSERT INTO comment_votes (user_id, comment_id, vote_type) VALUES (?, ?, ?)";
    $insertVote_stmt = mysqli_prepare($conn, $insertVote_query);
    mysqli_stmt_bind_param($insertVote_stmt, "iis", $user_id, $comment_id, $vote_type);
    mysqli_stmt_execute($insertVote_stmt);
}

$upvote_query = "SELECT COUNT(*) FROM comment_votes WHERE comment_id = ? AND vote_type = 'upvote'";
$upvote_stmt = mysqli_prepare($conn, $upvote_query);
mysqli_stmt_bind_param($upvote_stmt, "i", $comment_id);
mysqli_stmt_execute($upvote_stmt);
mysqli_stmt_bind_result($upvote_stmt, $upvotes);
mysqli_stmt_fetch($upvote_stmt);
mysqli_stmt_close($upvote_stmt);

$downvote_query = "SELECT COUNT(*) FROM comment_votes WHERE comment_id = ? AND vote_type = 'downvote'";
$downvote_stmt = mysqli_prepare($conn, $downvote_query);
mysqli_stmt_bind_param($downvote_stmt, "i", $comment_id);
mysqli_stmt_execute($downvote_stmt);
mysqli_stmt_bind_result($downvote_stmt, $downvotes);
mysqli_stmt_fetch($downvote_stmt);
mysqli_stmt_close($downvote_stmt);

$updateComment = "UPDATE comments SET upvotes = ?, downvotes = ? WHERE id = ?";
$updateComment_stmt = mysqli_prepare($conn, $updateComment);
mysqli_stmt_bind_param($updateComment_stmt, "iii", $upvotes, $downvotes, $comment_id);
mysqli_stmt_execute($updateComment_stmt);

echo json_encode([
    "success" => true,
    "upvotes" => $upvotes,
    "downvotes" => $downvotes
]);