<?php
session_start(); //Start session and to connect to database
include "db.php";

//Check if the user clicked the POST button
if (isset($_POST["submit-post"])) {
    //To identify who is logged in
    if (!isset($_SESSION["user_id"])) {
        //Placeholder for testing if user isn't logged in yet
        $user_id = 1;
    } else {
        $user_id = $_SESSION["user_id"];
    }

    //Capture and clean the text inputs from the form
    $sub_id = intval($_POST['sub_id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    //if link_url is not empty, escape it, otherwise set it to null
    $link_url = !empty($_POST['link_url']) ? mysqli_real_escape_string($conn, $_POST['link_url']) : null; 

    //Set default empty paths for files
    $image_url = null;
    $file_url = null;
    $upload_dir = 'uploads/';
    
    //Create the physcial uploads folder on laptop if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); //0777 gives read, write, and execute permissions to everyone, true allows for recursive directory creation
    }

    //Handle image upload processing (Figma frame 30)
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        if ($_FILES['image_file']['size'] <= 10485760) //10485760 is 10MB in binary
        
    }
}
