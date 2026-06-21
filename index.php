<?php
session_start();

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}
header("Location: profile.php?id=" . $_SESSION['user_id'] . "#tab-posts");
exit();
?>
