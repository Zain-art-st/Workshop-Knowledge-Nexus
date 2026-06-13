<?php
session_start();
include "db.php";

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

// ════════════════════════════════════════════════════════════════════════════
// VOTE ON POST
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'vote_post') {
    $post_id = intval($_POST['post_id'] ?? 0);
    $vote_type = in_array($_POST['vote_type'] ?? '', ['upvote', 'downvote']) ? $_POST['vote_type'] : 'upvote';

    if (!$post_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid post ID']);
        exit();
    }

    // Check if post exists
    $post_check = mysqli_prepare($conn, "SELECT id FROM posts WHERE id = ?");
    mysqli_stmt_bind_param($post_check, "i", $post_id);
    mysqli_stmt_execute($post_check);
    if (mysqli_stmt_get_result($post_check)->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
        exit();
    }

    // Check for existing vote
    $existing_query = "SELECT id, vote_type FROM post_votes WHERE post_id = ? AND user_id = ?";
    $existing_stmt = mysqli_prepare($conn, $existing_query);
    mysqli_stmt_bind_param($existing_stmt, "ii", $post_id, $user_id);
    mysqli_stmt_execute($existing_stmt);
    $existing_result = mysqli_stmt_get_result($existing_stmt);
    $existing_vote = mysqli_fetch_assoc($existing_result);

    if ($existing_vote) {
        if ($existing_vote['vote_type'] === $vote_type) {
            // Remove vote
            $delete_query = "DELETE FROM post_votes WHERE post_id = ? AND user_id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($delete_stmt, "ii", $post_id, $user_id);
            mysqli_stmt_execute($delete_stmt);

            // Update vote counts
            $update_query = "UPDATE posts SET upvotes = (SELECT COUNT(*) FROM post_votes WHERE post_id = ? AND vote_type = 'upvote'),
                                              downvotes = (SELECT COUNT(*) FROM post_votes WHERE post_id = ? AND vote_type = 'downvote')
                             WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "iii", $post_id, $post_id, $post_id);
            mysqli_stmt_execute($update_stmt);

            echo json_encode(['success' => true, 'message' => 'Vote removed']);
        } else {
            // Change vote
            $update_vote_query = "UPDATE post_votes SET vote_type = ? WHERE post_id = ? AND user_id = ?";
            $update_vote_stmt = mysqli_prepare($conn, $update_vote_query);
            mysqli_stmt_bind_param($update_vote_stmt, "sii", $vote_type, $post_id, $user_id);
            mysqli_stmt_execute($update_vote_stmt);

            // Update vote counts
            $update_query = "UPDATE posts SET upvotes = (SELECT COUNT(*) FROM post_votes WHERE post_id = ? AND vote_type = 'upvote'),
                                              downvotes = (SELECT COUNT(*) FROM post_votes WHERE post_id = ? AND vote_type = 'downvote')
                             WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "iii", $post_id, $post_id, $post_id);
            mysqli_stmt_execute($update_stmt);

            echo json_encode(['success' => true, 'message' => 'Vote changed']);
        }
    } else {
        // Add new vote
        $insert_query = "INSERT INTO post_votes (post_id, user_id, vote_type) VALUES (?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "iis", $post_id, $user_id, $vote_type);
        mysqli_stmt_execute($insert_stmt);

        // Update vote counts
        $update_query = "UPDATE posts SET upvotes = (SELECT COUNT(*) FROM post_votes WHERE post_id = ? AND vote_type = 'upvote'),
                                          downvotes = (SELECT COUNT(*) FROM post_votes WHERE post_id = ? AND vote_type = 'downvote')
                         WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "iii", $post_id, $post_id, $post_id);
        mysqli_stmt_execute($update_stmt);

        echo json_encode(['success' => true, 'message' => 'Vote recorded']);
    }
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// CREATE COMMENT
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'create_comment') {
    $post_id = intval($_POST['post_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if (!$post_id || empty($content)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input']);
        exit();
    }

    if (strlen($content) < 2) {
        http_response_code(400);
        echo json_encode(['error' => 'Comment too short']);
        exit();
    }

    // Check if post exists
    $post_check = mysqli_prepare($conn, "SELECT id FROM posts WHERE id = ?");
    mysqli_stmt_bind_param($post_check, "i", $post_id);
    mysqli_stmt_execute($post_check);
    if (mysqli_stmt_get_result($post_check)->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
        exit();
    }

    // Insert comment
    $insert_query = "INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($insert_stmt, "iis", $post_id, $user_id, $content);

    if (mysqli_stmt_execute($insert_stmt)) {
        $comment_id = mysqli_insert_id($conn);

        // Update comment count
        $update_query = "UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "i", $post_id);
        mysqli_stmt_execute($update_stmt);

        echo json_encode(['success' => true, 'comment_id' => $comment_id, 'message' => 'Comment created']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create comment']);
    }
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// GET COMMENTS FOR POST
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'get_comments' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = intval($_POST['post_id'] ?? 0);

    if (!$post_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid post ID']);
        exit();
    }

    $query = "SELECT c.id, c.content, c.created_at, u.username, u.profile_photo,
                     (SELECT COUNT(*) FROM comment_votes WHERE comment_id = c.id AND vote_type = 'upvote') as upvotes,
                     (SELECT COUNT(*) FROM comment_votes WHERE comment_id = c.id AND vote_type = 'downvote') as downvotes
              FROM comments c
              JOIN users u ON c.user_id = u.id
              WHERE c.post_id = ?
              ORDER BY c.created_at DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $post_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $comments = mysqli_fetch_all($result, MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'comments' => $comments]);
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// VOTE ON COMMENT
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'vote_comment') {
    $comment_id = intval($_POST['comment_id'] ?? 0);
    $vote_type = in_array($_POST['vote_type'] ?? '', ['upvote', 'downvote']) ? $_POST['vote_type'] : 'upvote';

    if (!$comment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid comment ID']);
        exit();
    }

    // Check if comment exists
    $comment_check = mysqli_prepare($conn, "SELECT id FROM comments WHERE id = ?");
    mysqli_stmt_bind_param($comment_check, "i", $comment_id);
    mysqli_stmt_execute($comment_check);
    if (mysqli_stmt_get_result($comment_check)->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Comment not found']);
        exit();
    }

    // Check for existing vote
    $existing_query = "SELECT id, vote_type FROM comment_votes WHERE comment_id = ? AND user_id = ?";
    $existing_stmt = mysqli_prepare($conn, $existing_query);
    mysqli_stmt_bind_param($existing_stmt, "ii", $comment_id, $user_id);
    mysqli_stmt_execute($existing_stmt);
    $existing_result = mysqli_stmt_get_result($existing_stmt);
    $existing_vote = mysqli_fetch_assoc($existing_result);

    if ($existing_vote) {
        if ($existing_vote['vote_type'] === $vote_type) {
            // Remove vote
            $delete_query = "DELETE FROM comment_votes WHERE comment_id = ? AND user_id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($delete_stmt, "ii", $comment_id, $user_id);
            mysqli_stmt_execute($delete_stmt);
            echo json_encode(['success' => true, 'message' => 'Vote removed']);
        } else {
            // Change vote
            $update_query = "UPDATE comment_votes SET vote_type = ? WHERE comment_id = ? AND user_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "sii", $vote_type, $comment_id, $user_id);
            mysqli_stmt_execute($update_stmt);
            echo json_encode(['success' => true, 'message' => 'Vote changed']);
        }
    } else {
        // Add new vote
        $insert_query = "INSERT INTO comment_votes (comment_id, user_id, vote_type) VALUES (?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "iis", $comment_id, $user_id, $vote_type);
        mysqli_stmt_execute($insert_stmt);
        echo json_encode(['success' => true, 'message' => 'Vote recorded']);
    }
    exit();
}

// Default error
http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit();
?>