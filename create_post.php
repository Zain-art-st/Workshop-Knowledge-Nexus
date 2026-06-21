<?php
session_start();
include "db.php";

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = false;

// Get subcommunity info if sub_id provided
$sub_id = isset($_GET['sub_id']) ? intval($_GET['sub_id']) : (isset($_POST['sub_id']) ? intval($_POST['sub_id']) : 0);

if ($sub_id) {
    $sub_query = "SELECT * FROM subcommunities WHERE id = ?";
    $sub_stmt = mysqli_prepare($conn, $sub_query);
    mysqli_stmt_bind_param($sub_stmt, "i", $sub_id);
    mysqli_stmt_execute($sub_stmt);
    $sub_result = mysqli_stmt_get_result($sub_stmt);
    $subcommunity = mysqli_fetch_assoc($sub_result);
    
    if (!$subcommunity) {
        die("Subcommunity not found.");
    }
    
    // Check if user is a member
    $member_query = "SELECT id FROM sub_memberships WHERE user_id = ? AND sub_id = ?";
    $member_stmt = mysqli_prepare($conn, $member_query);
    mysqli_stmt_bind_param($member_stmt, "ii", $user_id, $sub_id);
    mysqli_stmt_execute($member_stmt);
    $member_result = mysqli_stmt_get_result($member_stmt);
    
    if (mysqli_num_rows($member_result) === 0) {
        die("You must be a member of this subcommunity to post.");
    }
} else {
    die("No subcommunity specified.");
}

// Handle post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $link_url = trim($_POST['link_url'] ?? '');

    if($link_url === '')
        {
        $link_url = null;
        }
    
    // Validation
    if (empty($title)) {
        $error = "❌ Title is required.";
    } elseif (strlen($title) < 3) {
        $error = "❌ Title must be at least 3 characters. You have " . strlen($title) . " character(s).";
    } elseif (strlen($title) > 300) {
        $error = "❌ Title must not exceed 300 characters.";
        $title = "";
    } else {
        $image_url = null;
        
        // Handle image upload
        if (!empty($_FILES['image_url']['name'])) {
            $file = $_FILES['image_url'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (!in_array($file['type'], $allowed)) {
                $error = "❌ Invalid image format. Use JPEG, PNG, GIF, or WebP.";
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = "❌ Image file is too large. Max 5 MB.";
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $error = "❌ Error uploading image.";
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'post_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = __DIR__ . '/uploads/posts/' . $filename;
                
                if (!is_dir(__DIR__ . '/uploads/posts')) {
                    mkdir(__DIR__ . '/uploads/posts', 0755, true);
                }
                
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $image_url = 'uploads/posts/' . $filename;
                } else {
                    $error = "❌ Failed to save image.";
                }
            }
        }
        
        if (empty($error)) {
            // Insert post
            $insert_query = "INSERT INTO posts (user_id, sub_id, title, content, image_url, link_url) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($insert_stmt, "iissss", $user_id, $sub_id, $title, $content, $image_url, $link_url);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $post_id = mysqli_insert_id($conn);
                $success = true;
                $_SESSION['success_msg'] = "✅ Post created successfully!";
                header("Location: subcommunity.php?id=" . urlencode($subcommunity['id']));
                exit();
            } else {
                $error = "❌ Failed to create post. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post – ScholarSpace</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .create-post-form { max-width: 700px; margin: 0 auto; }
        .tab-group { display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--card-border); }
        .tab-btn { background: none; border: none; padding: 12px 16px; color: var(--text-muted); cursor: pointer; font-family: var(--font-body); font-size: 14px; border-bottom: 2px solid transparent; transition: all 0.2s; }
        .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .upload-area { border: 2px dashed var(--card-border); border-radius: 10px; padding: 40px 20px; text-align: center; cursor: pointer; transition: all 0.2s; }
        .upload-area:hover { border-color: var(--accent); background: rgba(79,142,247,.05); }
        .upload-area.dragover { border-color: var(--accent); background: rgba(79,142,247,.1); }
        .upload-preview { max-width: 100%; max-height: 300px; border-radius: 10px; margin: 16px 0; }

        .preview-wrapper {
            position: relative;
            display: inline-block;
            margin-top: 16px;
        }

        .upload-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            display: block;
        }

        .remove-image-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 50%;
            background: rgba(0,0,0,.7);
            color: white;
            font-size: 18px;
            cursor: pointer;
            opacity: 0;
            transition: .2s
        }

        .preview-wrapper:hover .remove-image-btn{
            opacity: 1;
        }

        .remove-image-btn:hover{
            background: rgba(255,80,80,.9);
        }
    </style>
</head>
<body>
    <div class="stars-bg"></div>
    <div class="sunset-bg"></div>

    <header class="navbar">
        <a href="index.php" class="nav-logo">ScholarSpace</a>
        <div class="nav-search">
            <input type="text" class="search-input" placeholder="Search...">
        </div>
        <div class="nav-right">
            <span class="nav-welcome">Welcome <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
            <input type="checkbox" id="profile-toggle" class="profile-toggle-checkbox">
            <label for="profile-toggle" class="profile-avatar">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
            </label>
            <div class="profile-menu">
                <a href="logout.php">Log Out</a>
            </div>
        </div>
    </header>

    <div class="page-wrapper">
        <div class="create-post-form" style="padding: 0 20px;">
            <div class="card" style="margin-bottom: 20px;">
                <div style="padding: 20px; border-bottom: 1px solid var(--card-border);">
                    <h1 style="font-family: var(--font-display); font-size: 24px; font-weight: 700; margin-bottom: 8px;">
                        Create a post in <?php echo htmlspecialchars($subcommunity['name']); ?>
                    </h1>
                    <p style="color: var(--text-muted); font-size: 14px;">
                        <?php echo htmlspecialchars($subcommunity['name']); ?>
                    </p>
                </div>

                <?php if ($error): ?>
                    <div style="background: rgba(255,79,106,.25); border: 2px solid #ff4f6a; color: #ff7a8a; padding: 16px 20px; border-radius: 10px; font-size: 15px; margin: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">⚠️</span>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <div style="padding: 20px;">
                    <form method="POST" action="create_post.php?sub_id=<?php echo $sub_id; ?>" enctype="multipart/form-data">
                        <!-- Tab Group -->
                        <div class="tab-group">
                            <button type="button" class="tab-btn active" onclick="switchTab('text')">📝 Text</button>
                            <button type="button" class="tab-btn" onclick="switchTab('image')">🖼️ Image</button>
                            <button type="button" class="tab-btn" onclick="switchTab('link')">🔗 Link</button>
                        </div>

                        <!-- Text Tab (always visible, shown first) -->
                        <div id="text-tab" class="tab-content active">
                            <div class="form-group">
                                <label>Post Title <span style="color: var(--danger);">*</span> (min 3 chars)</label>
                                <input type="text" name="title" class="form-group input" 
                                       placeholder="Enter an interesting title..."
                                       value="<?php echo htmlspecialchars($title ?? ''); ?>"
                                       required>
                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                                    <span id="title-count">0</span>/300 characters
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Content (Optional)</label>
                                <textarea name="content" class="form-group textarea" 
                                          placeholder="Share your thoughts, ideas, or discussion..."
                                          style="min-height: 150px;"><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Image Tab -->
                        <div id="image-tab" class="tab-content">
                            <div class="form-group">
                                <label>Add Image</label>
                                <div class="upload-area" id="uploadArea" onclick="document.getElementById('imageInput').click()">
                                    <div style="font-size: 32px; margin-bottom: 10px;">🖼️</div>
                                    <p style="color: var(--text-muted);">Click to upload or drag and drop</p>
                                    <p style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">JPG, PNG, GIF, or WebP (Max 5 MB)</p>
                                </div>
                                <input type="file" id="imageInput" name="image_url" accept="image/*" 
                                       style="display: none;" onchange="previewImage(this)">

                                <div class="preview-wrapper" id="previewWrapper" style="display: none;">
                                    <img id="imagePreview" class="upload-preview" style="display: none;">
                                    <button type="button" class="remove-image-btn" onclick="removeImage(event)">✕</button>
                                </div>
                            </div>
                        </div>

                        <!-- Link Tab -->
                        <div id="link-tab" class="tab-content">
                            <div class="form-group">
                                <label>URL</label>
                                <input type="url" name="link_url" class="form-group input" 
                                       placeholder="https://example.com">
                            </div>
                        </div>

                        <input type="hidden" name="sub_id" value="<?php echo $sub_id; ?>">

                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="submit" name="create_post" class="btn btn-primary" style="flex: 1;">
                                Post
                            </button>
                            <a href="subcommunity.php?id=<?php echo urlencode($subcommunity['id']); ?>" 
                               class="btn btn-secondary" style="flex: 1; text-decoration: none; text-align: center;">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tab + '-tab').classList.add('active');
            event.target.classList.add('active');
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    const wrapper = document.getElementById("previewWrapper");
                    preview.src = e.target.result;

                    // Show preview
                    wrapper.style.display = "inline-block";
                    preview.style.display = 'block';
                    document.getElementById("uploadArea").style.display = "none";
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage(event)
        {
            event.stopPropagation();

            const input = document.getElementById("imageInput");
            const preview = document.getElementById("imagePreview");
            const wrapper = document.getElementById("previewWrapper");

            input.value = "";
            preview.src = "";

            preview.style.display = "none";
            wrapper.style.display = "none";
            document.getElementById("uploadArea").style.display = "block";
        }

        // Drag and drop
        const uploadArea = document.getElementById('uploadArea');
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => uploadArea.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => uploadArea.classList.remove('dragover'), false);
        });

        uploadArea.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('imageInput').files = files;
            previewImage(document.getElementById('imageInput'));
        }, false);

        // Character counter
        const titleInput = document.querySelector('input[name="title"]');
        const titleCount = document.getElementById('title-count');
        
        titleInput.addEventListener('input', () => {
            titleCount.textContent = titleInput.value.length;
        });
    </script>
</body>
</html>