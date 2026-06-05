<?php
session_start();
include "db.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'error'=>'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data    = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success'=>false,'error'=>'Invalid data']);
    exit();
}

$title      = trim($data['title']       ?? 'Untitled Document');
$content    = $data['content']          ?? '';
$doc_id     = (int)($data['id']         ?? 0);
$post_to_sub= (int)($data['post_to_sub']?? 0);

//limit title length
if (strlen($title) > 100) $title = substr($title, 0, 100);

if ($doc_id) {
    //verfy ownership
    $chk = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id, share_token, post_id FROM documents WHERE id=$doc_id AND owner_id=$user_id LIMIT 1"));
    if (!$chk) {
        echo json_encode(['success'=>false,'error'=>'Document not found or access denied']);
        exit();
    }
    mysqli_query($conn,
        "UPDATE documents SET title='".mysqli_real_escape_string($conn,$title)."',
         content='".mysqli_real_escape_string($conn,$content)."',
         updated_at=NOW() WHERE id=$doc_id");
    $token = $chk['share_token'];
    $post_id = $chk['post_id'];
} else {// Create new
    $token = bin2hex(random_bytes(32));
    $ins = mysqli_prepare($conn,
        "INSERT INTO documents (owner_id, title, content, share_token) VALUES (?,?,?,?)");
    mysqli_stmt_bind_param($ins, "isss", $user_id, $title, $content, $token);
    if (!mysqli_stmt_execute($ins)) {
        echo json_encode(['success'=>false,'error'=>mysqli_error($conn)]);
        exit();
    }
    $doc_id  = mysqli_insert_id($conn);
    $post_id = null;
}

//post sub
if ($post_to_sub) {
//check membership
    $mem = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id FROM sub_memberships WHERE user_id=$user_id AND sub_id=$post_to_sub LIMIT 1"));
    if (!$mem && $_SESSION['user_type'] !== 'admin') {
        echo json_encode(['success'=>false,'error'=>'You are not a member of that community']);
        exit();
    }
//build share URL
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
              . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
              . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/';
    $doc_url  = $base_url . 'view_doc.php?token=' . $token;
//snippet from content
    $snippet = mb_substr(strip_tags($content), 0, 300);

    if (!$post_id) {
//create new post
        $ins2 = mysqli_prepare($conn,
            "INSERT INTO posts (user_id, sub_id, title, content, link_url)
             VALUES (?,?,?,?,?)");
        mysqli_stmt_bind_param($ins2, "iisss",
            $user_id, $post_to_sub, $title, $snippet, $doc_url);
        mysqli_stmt_execute($ins2);
        $post_id = mysqli_insert_id($conn);
//link post back to document
        mysqli_query($conn,
            "UPDATE documents SET sub_id=$post_to_sub, post_id=$post_id,
             share_mode='view' WHERE id=$doc_id");
    }

    echo json_encode([
        'success'  => true,
        'doc_id'   => $doc_id,
        'post_id'  => $post_id,
        'token'    => $token,
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'doc_id'  => $doc_id,
    'token'   => $token,
]);
?>
