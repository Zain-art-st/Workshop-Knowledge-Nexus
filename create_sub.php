<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

$error = "";

if (isset($_POST['next'])) {
    $topic = trim($_POST['topic'] ?? '');
    if (empty($topic)) {
        $error = "Please choose a topic for your community.";
    } else {
        $_SESSION['new_sub_topic'] = $topic;
        header("Location: create_sub_details.php");
        exit();
    }
}

$topics = [
    ['label' => 'Technology', 'emoji' => '💻'],
    ['label' => 'Study', 'emoji' => '📚'],
    ['label' => 'Gaming', 'emoji' => '🎮'],
    ['label' => 'Creative', 'emoji' => '🎨'],
    ['label' => 'Food', 'emoji' => '🍕'],
    ['label' => 'Music', 'emoji' => '🎵'],
    ['label' => 'Fitness', 'emoji' => '🏋️'],
    ['label' => 'Travel', 'emoji' => '✈️'],
    ['label' => 'Humour', 'emoji' => '😂'],
    ['label' => 'Career', 'emoji' => '💼'],
    ['label' => 'Pets', 'emoji' => '🐾'],
    ['label' => 'Science', 'emoji' => '🔬'],
    ['label' => 'Lifestyle', 'emoji' => '🌱'],
    ['label' => 'News', 'emoji' => '📰'],
    ['label' => 'Anime & Manga', 'emoji' => '🎌'],
    ['label' => 'Rant', 'emoji' => '🗣️'],
    ['label' => 'Hobby', 'emoji' => '🧩'],
    ['label' => 'Other', 'emoji' => '✨']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create a Community – ScholarSpace</title>
  <style>
    :root {
      --font-display: system-ui, sans-serif;
      --font-body: system-ui, sans-serif;
      --text-muted: #888;
      --accent: #4f8ef7;
      --card-border: #333;
      --text-main: #fff;
    }
    body { background: #111; color: var(--text-main); font-family: var(--font-body); margin: 0; }
    .create-page { max-width: 680px; margin: 0 auto; padding: 0 20px 60px; }
    .create-hero { padding: 40px 0 28px; text-align: center; }
    .create-hero h1 { font-family: var(--font-display); font-size: 28px; font-weight: 800; margin-bottom: 8px; }
    .create-hero p { font-size: 14px; color: var(--text-muted); }

    .step-bar { display: flex; align-items: center; justify-content: center; margin-bottom: 32px; gap: 0; }
    .step-node { display: flex; align-items: center; gap: 8px; }
    .step-circle { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
    .step-circle.active { background: var(--accent); color: #fff; }
    .step-circle.idle { background: rgba(255,255,255,.1); color: var(--text-muted); }
    .step-label { font-size: 12px; font-weight: 600; }
    .step-label.active { color: var(--accent); }
    .step-label.idle { color: var(--text-muted); }
    .step-line { width: 48px; height: 2px; background: var(--card-border); margin: 0 4px; }

    .topics-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 28px; }
    @media(max-width:500px){ .topics-grid { grid-template-columns: repeat(2, 1fr); } }

    .topic-pill {
      display: flex; align-items: center; gap: 10px; padding: 13px 16px;
      border: 2px solid var(--card-border); border-radius: 12px;
      background: rgba(255,255,255,.04); cursor: pointer;
      transition: all .2s; font-family: var(--font-body);
      color: var(--text-main); font-size: 14px; font-weight: 500;
      position: relative;
    }
    .topic-pill:hover { border-color: var(--accent); background: rgba(79,142,247,.08); }
    .topic-pill.selected { border-color: var(--accent); background: rgba(79,142,247,.15); color: var(--accent); }
    .topic-pill input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
    .topic-emoji { font-size: 20px; pointer-events: none; }
    .topic-label { pointer-events: none; }
    
    .navbar { display: flex; padding: 16px 20px; border-bottom: 1px solid var(--card-border); }
    .nav-logo { color: #fff; text-decoration: none; font-weight: bold; }
    .btn { padding: 12px 24px; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; width: 100%; }
    .btn-primary { background: var(--accent); color: #fff; }
    .error-msg { color: #ff5555; text-align: center; margin-bottom: 16px; font-size: 14px; }
  </style>
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div>

<header class="navbar">
  <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-right" style="margin-left:auto;">
    <a href="dashboard.php" style="font-size:13px; color:var(--text-muted); text-decoration:none;">✕ Cancel</a>
  </div>
</header>

<div class="page-wrapper">
  <div class="create-page">

    <div class="create-hero">
      <h1>Tell us about your community</h1>
      <p>Choose what your sub is about. You can only pick one.</p>
    </div>

    <div class="step-bar">
      <div class="step-node">
        <div class="step-circle active">1</div>
        <span class="step-label active">Topic</span>
      </div>
      <div class="step-line"></div>
      <div class="step-node">
        <div class="step-circle idle">2</div>
        <span class="step-label idle">Details</span>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="create_sub.php">
      <div class="topics-grid">
        <?php foreach ($topics as $t): ?>
        <label class="topic-pill <?php echo (($_POST['topic'] ?? '') === $t['label']) ? 'selected' : ''; ?>">
          <input type="radio" name="topic" value="<?php echo htmlspecialchars($t['label']); ?>" <?php echo (($_POST['topic'] ?? '') === $t['label']) ? 'checked' : ''; ?> onchange="selectTopic(this.parentElement)">
          <span class="topic-emoji"><?php echo $t['emoji']; ?></span>
          <span class="topic-label"><?php echo $t['label']; ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <button type="submit" name="next" class="btn btn-primary">Next →</button>
    </form>

  </div>
</div>

<script>
function selectTopic(el) {
  document.querySelectorAll('.topic-pill').forEach(p => p.classList.remove('selected'));
  el.classList.add('selected');
}
</script>
</body>
</html>