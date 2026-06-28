<?php
session_start();
include "db.php";

if(!isset($_SESSION["user_id"]))
    {
    exit("Login required");
    }
$allowed_reasons = [
    "Unwanted commercial content or spam",
    "Pornography or sexually explicit material",
    "Hate speech or graphic violence",
    "Harassment or bullying",
    "Misinformation"
];

$reporter_id = $_SESSION['user_id'];
$target_id = intval($_POST['post_id']);
$reason = trim($_POST['reason']);

if(empty($reason))
    {
    echo "error";
    exit();
    }


if (!in_array($reason, $allowed_reasons)) 
    {
    echo "error";
    exit();
    }

// Check if already reported
$checkReport_query = "SELECT id FROM reports WHERE reporter_id = ? AND target_type = 'post' AND target_id = ?";
$checkReport_stmt = mysqli_prepare($conn, $checkReport_query);
mysqli_stmt_bind_param($checkReport_stmt, "ii", $reporter_id, $target_id);
mysqli_stmt_execute($checkReport_stmt);
$resultCheckReport = mysqli_stmt_get_result($checkReport_stmt);
if(mysqli_num_rows($resultCheckReport) > 0)
    {
    echo "already_reported";
    exit();
    }

// Insert report
$report_query = "INSERT INTO reports (reporter_id, target_type, target_id, reason) VALUES (?, 'post', ?, ?)";
$report_stmt = mysqli_prepare($conn, $report_query);
mysqli_stmt_bind_param($report_stmt, "iis", $reporter_id, $target_id, $reason);

if(mysqli_stmt_execute($report_stmt))
    {
    echo "success";
    }
else 
    {
    echo "error"; 
    }
?>