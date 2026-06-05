<?php
session_start();
include "db.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { 
    echo json_encode([]); 
    exit(); 
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { 
    echo json_encode([]); 
    exit(); 
}

$safe = '%' . mysqli_real_escape_string($conn, $q) . '%';
$results = [];

//look up matching forums
$subs_query = "SELECT id, name, description FROM subcommunities WHERE name LIKE '$safe' OR description LIKE '$safe' LIMIT 4";
$subs = mysqli_query($conn, $subs_query);
while ($row = mysqli_fetch_assoc($subs)) {
    $results[] = [
        'icon' => '🗂️',
        'title' => $row['name'],
        'subtitle' => $row['description'] ?? 'Subcommunity',
        'url' => 'sub.php?id=' . $row['id']
    ];
}

//look up matching threads
$posts_query = "SELECT p.id, p.title, s.name AS sub_name FROM posts p JOIN subcommunities s ON p.sub_id = s.id WHERE p.title LIKE '$safe' AND p.is_removed = 0 LIMIT 4";
$posts = mysqli_query($conn, $posts_query);
while ($row = mysqli_fetch_assoc($posts)) {
    $results[] = [
        'icon' => '📝',
        'title' => $row['title'],
        'subtitle' => $row['sub_name'],
        'url' => 'post.php?id=' . $row['id']
    ];
}

//look up matching users
$users_query = "SELECT id, username, user_type FROM users WHERE username LIKE '$safe' LIMIT 3";
$users = mysqli_query($conn, $users_query);
while ($row = mysqli_fetch_assoc($users)) {
    $results[] = [
        'icon' => ($row['user_type'] === 'student') ? '🎓' : '💼',
        'title' => $row['username'],
        'subtitle' => ucfirst($row['user_type']),
        'url' => 'profile.php?id=' . $row['id']
    ];
}

echo json_encode($results);
?>