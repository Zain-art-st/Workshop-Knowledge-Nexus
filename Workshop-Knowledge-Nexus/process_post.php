<?php
session_start(); //Start session and to connect to database
include "db.php";

if (isset($_POST["submit-post"])) { //Check if the user clicked the POST button
    //To identify who is logged in
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php"); //Redirect them back to login page if they are not logged in
        exit();
    } else {
        $user_id = $_SESSION["user_id"];
    }

    //Capture and clean the text inputs from the form
    $sub_id = intval($_POST['sub_id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']); //to get what the user typed in the title field, grab $_POST['title']
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
        //to see the file size of an uploaded image, check the $_FILES['image_file']['size']
        if ($_FILES['image_file']['size'] <= 10485760) //10485760 is 10MB in binary
            $unique_img = time() . '_img_' . basename($_FILES['image_file']['name']);
            if (move_uploaded_file($_FILES['image_file']['tmp_name'],$upload_dir . $unique_img)) {
                $image_url = $upload_dir . $unique_img;
            }
    }
}

//Handle file/document upload procressing
if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] == 0) {
    if ($_FILES['doc_file']['size'] <= 10485760) {
        $unique_doc = time() . '_doc_' . basename($_FILES['doc_file']['name']);
        if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $upload_dir . $unique_doc)) {
            $file_url = $upload_dir . $unique_doc;
        }
    }
}

//Insert the post data into the database using a Secured Prepared Statement to prevent SQL injection
$query = "INSERT INTO posts (user_id, sub_id, title, content, image_url, link_url, file_url) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    //"iisssss" tells PHP the data types: integer, integer and 5 strings
    mysqli_stmt_bind_param($stmt, "iisssss", $user_id, $sub_id, $title, $content, $image_url, $link_url, $file_url);

    if (mysqli_stmt_execute($stmt)) {
        //Send the user back to the dashboard after successful post creation
        header("Location: dashboard.php?success=+post_created");
        exit();
    }else{
        echo "Database Error: " . mysqli_error($conn);
    }
}else{
    //if someone tries to access this page without submitting the form, send them back to the dashboard
    header("Location: create_post.php");
    exit();
}
?>
