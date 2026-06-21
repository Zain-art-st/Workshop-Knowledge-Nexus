<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$my_id     = $_SESSION['user_id'];
$my_type   = $_SESSION['user_type'];
$view_id   = isset($_GET['id']) ? (int)$_GET['id'] : $my_id;
$is_own    = ($view_id === $my_id);

// Fetch user
$usr = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM users WHERE id=$view_id LIMIT 1"));
if (!$usr) { header("Location: dashboard.php"); exit(); }

// Fetch profile extras
$student  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM student_profiles WHERE user_id=$view_id LIMIT 1"));
$graduate = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM graduate_profiles WHERE user_id=$view_id LIMIT 1"));

// Fetch posts
$posts_res = mysqli_query($conn,
    "SELECT p.*, s.name AS sub_name FROM posts p
     JOIN subcommunities s ON p.sub_id=s.id
     WHERE p.user_id=$view_id AND p.is_removed=0
     ORDER BY p.created_at DESC");
$posts = mysqli_fetch_all($posts_res, MYSQLI_ASSOC);

// Post count
$post_count = count($posts);

// Fetch communities where the current logged-in user is a moderator
$my_subs = [];
if (!$is_own) {
    $my_subs_res = mysqli_query($conn,
        "SELECT s.id, s.name FROM subcommunities s
         JOIN sub_memberships sm ON s.id=sm.sub_id
         WHERE sm.user_id=$my_id AND sm.role='moderator'"
    );
    if ($my_subs_res) {
        $my_subs = mysqli_fetch_all($my_subs_res, MYSQLI_ASSOC);
    }
}

function ava($photo,$name,$size=40){
    $init=strtoupper(substr($name,0,1));$fs=round($size*.45);
    if($photo&&$photo!=='default.png'&&file_exists(__DIR__.'/'.$photo))
        return "<img src='$photo' alt='".htmlspecialchars($name)."' style='width:{$size}px;height:{$size}px;border-radius:50%;object-fit:cover;display:block;'>";
    return "<span style='font-size:{$fs}px;font-weight:700;color:#fff;'>$init</span>";
}
function timeAgo($d){$s=time()-strtotime($d);if($s<60)return $s.'s ago';if($s<3600)return floor($s/60).'m ago';if($s<86400)return floor($s/3600).'h ago';return floor($s/86400).'d ago';}

$skills_arr = !empty($graduate['skills']) ? array_map('trim', explode(',', $graduate['skills'])) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($usr['username']); ?> – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .profile-page { max-width:860px; margin:0 auto; padding:0 20px 60px; }

    /* Banner */
    .profile-banner {
      height:160px; border-radius:16px 16px 0 0; overflow:hidden;
      background:linear-gradient(135deg,#1a0a2e 0%,#2d0f3f 40%,#5a1a4a 70%,#c4562a 100%);
      position:relative;
    }
    .profile-card-box {
      background:var(--bg-card); backdrop-filter:blur(16px);
      border:1px solid var(--card-border); border-top:none;
      border-radius:0 0 16px 16px; padding:0 28px 24px; margin-bottom:20px;
    }
    .profile-avatar-wrap {
      margin-top:-44px; margin-bottom:12px; display:flex;
      justify-content:space-between; align-items:flex-end;
    }
    .profile-big-avatar {
      width:88px; height:88px; border-radius:50%;
      border:4px solid var(--bg-deep);
      background:linear-gradient(135deg,var(--accent),var(--accent2));
      display:flex; align-items:center; justify-content:center;
      overflow:hidden; flex-shrink:0;
    }
    .profile-name { font-family:var(--font-display); font-size:22px; font-weight:800; }
    .profile-badge {
      display:inline-flex; align-items:center; gap:6px;
      padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; margin-top:6px;
    }
    .profile-badge.student  { background:rgba(62,207,142,.15); color:var(--success); border:1px solid rgba(62,207,142,.3); }
    .profile-badge.graduate { background:rgba(79,142,247,.15);  color:var(--accent);  border:1px solid rgba(79,142,247,.3); }
    .profile-badge.admin    { background:rgba(245,158,11,.15);  color:var(--warning); border:1px solid rgba(245,158,11,.3); }
    .profile-stats { display:flex; gap:24px; margin-top:16px; }
    .profile-stat { text-align:center; }
    .profile-stat strong { display:block; font-size:20px; font-weight:800; font-family:var(--font-display); }
    .profile-stat span   { font-size:11px; color:var(--text-muted); }

    /* Tabs */
    .profile-tabs { display:flex; gap:0; border-bottom:1px solid var(--card-border); margin-bottom:20px; }
    .profile-tab  { padding:12px 24px; font-size:14px; font-weight:600; cursor:pointer;
                    color:var(--text-muted); border-bottom:2px solid transparent;
                    transition:color .2s,border-color .2s; background:none; border-top:none;
                    border-left:none; border-right:none; font-family:var(--font-body); }
    .profile-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
    .tab-pane { display:none; }
    .tab-pane.active { display:block; }

    /* Section cards */
    .info-section {
      background:var(--bg-card); backdrop-filter:blur(16px);
      border:1px solid var(--card-border); border-radius:16px;
      padding:20px 24px; margin-bottom:16px;
    }
    .info-section h3 {
      font-family:var(--font-display); font-size:16px; font-weight:700;
      margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--card-border);
      display:flex; align-items:center; gap:8px;
    }
    .info-row { display:flex; gap:12px; margin-bottom:12px; align-items:flex-start; }
    .info-label { font-size:12px; color:var(--text-muted); min-width:130px; padding-top:2px; }
    .info-value { font-size:14px; flex:1; }
    .skill-tag-view {
      display:inline-block; padding:4px 12px; background:rgba(79,142,247,.12);
      border:1px solid rgba(79,142,247,.25); border-radius:20px;
      font-size:12px; color:var(--accent); margin:3px;
    }
    .edit-btn {
      padding:8px 20px; border-radius:8px; font-size:13px; font-weight:600;
      background:rgba(79,142,247,.12); border:1px solid rgba(79,142,247,.25);
      color:var(--accent); cursor:pointer; text-decoration:none;
      font-family:var(--font-body); transition:background .2s;
    }
    .edit-btn:hover { background:rgba(79,142,247,.22); }

    /* Post mini card */
    .mini-post {
      background:var(--bg-card); border:1px solid var(--card-border);
      border-radius:12px; padding:16px; margin-bottom:12px;
      transition:border-color .2s;
    }
    .mini-post:hover { border-color:rgba(79,142,247,.3); }
    .mini-post-sub  { font-size:11px; color:var(--accent); margin-bottom:6px; }
    .mini-post-title{ font-size:14px; font-weight:600; margin-bottom:6px; }
    .mini-post-meta { font-size:11px; color:var(--text-muted); }
  </style>
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div>

<header class="navbar">
<a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-search"><input type="text" placeholder="Search anything…" onclick="location.href='dashboard.php'" readonly></div>
  <div class="nav-right">
    <a href="dashboard.php" style="font-size:13px;color:var(--text-muted);text-decoration:none;margin-right:4px;">← Feed</a>
    <div class="profile-avatar" style="cursor:pointer;" onclick="location.href='dashboard.php'">
      <?php echo ava($_SESSION['profile_photo'] ?? 'default.png', $_SESSION['username'], 36); ?>
    </div>
  </div>
</header>

<div class="page-wrapper">
<div class="profile-page">
    <div class="profile-banner"></div>
    <div class="profile-card-box">
      <div class="profile-avatar-wrap">
        <div class="profile-big-avatar">
          <?php echo ava($usr['profile_photo'], $usr['username'], 88); ?>
        </div>
        <?php if($is_own): ?>
        <a href="edit_profile.php" class="edit-btn">✏️ Edit Profile</a>
        <?php else: ?>
        <div style="position:relative;">
          <button onclick="toggleReportMenu()" id="reportMenuBtn"
                  style="background:rgba(255,255,255,.08);border:1px solid var(--card-border);
                         border-radius:8px;padding:6px 12px;color:var(--text-muted);
                         cursor:pointer;font-size:18px;transition:background .2s;"
                  onmouseover="this.style.background='rgba(255,255,255,.14)'"
                  onmouseout="this.style.background='rgba(255,255,255,.08)'">
            ⋯
          </button>
          <div id="profileReportMenu"
               style="display:none;position:absolute;top:40px;right:0;
                      background:#1e1e35;border:1px solid var(--card-border);
                      border-radius:10px;padding:6px 0;min-width:180px;
                      box-shadow:0 4px 20px rgba(0,0,0,.5);z-index:100;">
            <button onclick="reportUser(<?php echo $view_id; ?>)"
                    style="display:flex;align-items:center;gap:8px;width:100%;
                           padding:10px 16px;font-size:13px;color:var(--danger);
                           background:none;border:none;cursor:pointer;text-align:left;
                           font-family:var(--font-body);transition:background .15s;"
                    onmouseover="this.style.background='rgba(255,79,106,.08)'"
                    onmouseout="this.style.background='none'">
              🚩 Report this user
            </button>
            
            <?php if (!empty($my_subs)): ?>
            <button onclick="openModModal()"
                    style="display:flex;align-items:center;gap:8px;width:100%;
                           padding:10px 16px;font-size:13px;color:var(--warning);
                           background:none;border:none;cursor:pointer;text-align:left;
                           font-family:var(--font-body);transition:background .15s;"
                    onmouseover="this.style.background='rgba(245,158,11,.08)'"
                    onmouseout="this.style.background='none'">
              👑 Add as Moderator
            </button>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="profile-name"><?php echo htmlspecialchars($usr['username']); ?></div>

      <div>
        <span class="profile-badge <?php echo $usr['user_type']; ?>">
          <?php if($usr['user_type']==='student') echo '🎓 Verified Student';
                elseif($usr['user_type']==='admin') echo '⚙️ Administrator';
                else echo '💼 Graduate'; ?>
        </span>
      </div>

      <?php if($usr['user_type']==='graduate' && $graduate): ?>
      <div style="margin-top:10px;font-size:14px;color:var(--text-muted);">
        <?php if(!empty($graduate['job_title'])&&!empty($graduate['company'])): ?>
          💼 <?php echo htmlspecialchars($graduate['job_title']); ?> at <strong style="color:var(--text-main);"><?php echo htmlspecialchars($graduate['company']); ?></strong>
        <?php endif; ?>
        <?php if(!empty($graduate['field_of_study'])): ?>
          &nbsp;•&nbsp; 🎓 <?php echo htmlspecialchars($graduate['field_of_study']); ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if(!empty($graduate['bio'])): ?>
      <p style="margin-top:12px;font-size:14px;line-height:1.7;color:var(--text-muted);"><?php echo htmlspecialchars($graduate['bio']); ?></p>
      <?php endif; ?>

      <?php if(!empty($graduate['linkedin_url'])): ?>
      <a href="<?php echo htmlspecialchars($graduate['linkedin_url']); ?>" target="_blank"
         style="display:inline-flex;align-items:center;gap:6px;margin-top:10px;font-size:13px;color:#0a66c2;text-decoration:none;">
        🔗 LinkedIn Profile
      </a>
      <?php endif; ?>

      <div class="profile-stats">
        <div class="profile-stat">
          <strong><?php echo $post_count; ?></strong>
          <span>Posts</span>
        </div>
        <div class="profile-stat">
          <strong><?php echo date('M Y', strtotime($usr['created_at'])); ?></strong>
          <span>Joined</span>
        </div>
      </div>
    </div>

    <div class="profile-tabs">
      <button class="profile-tab active" onclick="switchTab('info',this)">📋 Profile</button>
      <button class="profile-tab" onclick="switchTab('posts',this)">📝 Posts</button>
    </div>

  <div class="tab-pane active" id="tab-info">

      <?php if($usr['user_type']==='student' && $student): ?>
      <div class="info-section">
        <h3>🎓 Student Information</h3>
        <div class="info-row">
          <span class="info-label">Matric Number</span>
          <span class="info-value"><?php echo htmlspecialchars($student['matric_number']); ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Status</span>
          <span class="info-value" style="color:var(--success);">✅ Currently Enrolled</span>
        </div>
      </div>

      <?php elseif($usr['user_type']==='graduate' && $graduate): ?>
      <?php if(!empty($graduate['company'])||!empty($graduate['job_title'])): ?>
      <div class="info-section">
        <h3>💼 Experience</h3>
        <div style="display:flex;gap:16px;align-items:flex-start;">
          <div style="width:44px;height:44px;border-radius:10px;background:rgba(79,142,247,.15);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">🏢</div>
          <div>
            <div style="font-weight:700;font-size:15px;"><?php echo htmlspecialchars($graduate['job_title']??''); ?></div>
            <div style="color:var(--text-muted);font-size:13px;"><?php echo htmlspecialchars($graduate['company']??''); ?></div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
              <?php
                $status_labels = [
                  'employed'=>'Full-time','self-employed'=>'Self-Employed',
                  'freelance'=>'Freelance','further_study'=>'Further Study','unemployed'=>'Seeking'
                ];
                echo $status_labels[$graduate['job_status']] ?? ucfirst($graduate['job_status']);
              ?>
              <?php if(!empty($graduate['salary_range'])): ?>
                &nbsp;•&nbsp; <?php echo htmlspecialchars($graduate['salary_range']); ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="info-section">
        <h3>🎓 Education</h3>
        <div style="display:flex;gap:16px;align-items:flex-start;">
          <div style="width:44px;height:44px;border-radius:10px;background:rgba(192,109,232,.15);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">🎓</div>
          <div>
            <div style="font-weight:700;font-size:15px;">
              <?php
                $edu = ['diploma'=>'Diploma','bachelor'=>"Bachelor's Degree",'master'=>"Master's Degree",'phd'=>'PhD','other'=>'Other'];
                echo $edu[$graduate['education_level']] ?? ucfirst($graduate['education_level']??'');
              ?>
            </div>
            <?php if(!empty($graduate['field_of_study'])): ?>
            <div style="color:var(--text-muted);font-size:13px;"><?php echo htmlspecialchars($graduate['field_of_study']); ?></div>
            <?php endif; ?>
            <?php if(!empty($graduate['graduation_year'])): ?>
            <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Class of <?php echo $graduate['graduation_year']; ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if(!empty($skills_arr)): ?>
      <div class="info-section">
        <h3>🛠️ Skills</h3>
        <div>
          <?php foreach($skills_arr as $sk): ?>
          <span class="skill-tag-view"><?php echo htmlspecialchars(trim($sk)); ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php elseif($usr['user_type']==='admin'): ?>
      <div class="info-section">
        <h3>⚙️ Administrator</h3>
        <p style="color:var(--text-muted);font-size:14px;">This account has administrative privileges on ScholarSpace.</p>
      </div>
      <?php endif; ?>

      <div class="info-section">
        <h3>📅 Member Since</h3>
        <div class="info-row">
          <span class="info-label">Joined</span>
          <span class="info-value"><?php echo date('F j, Y', strtotime($usr['created_at'])); ?></span>
        </div>
      </div>
    </div>

  <div class="tab-pane" id="tab-posts">
      <?php if(empty($posts)): ?>
      <div style="text-align:center;padding:60px 20px;">
        <div style="font-size:48px;margin-bottom:16px;">📭</div>
        <p style="color:var(--text-muted);font-size:14px;">
          <?php echo $is_own ? "You haven't posted anything yet." : "No posts yet."; ?>
        </p>
      </div>
      <?php else: ?>
      <?php foreach($posts as $p): ?>
      <a href="post.php?id=<?php echo $p['id']; ?>" style="text-decoration:none;display:block;">
        <div class="mini-post">
          <div class="mini-post-sub"><?php echo htmlspecialchars($p['sub_name']); ?></div>
          <div class="mini-post-title"><?php echo htmlspecialchars($p['title']); ?></div>
          <div class="mini-post-meta">
            ▲ <?php echo number_format($p['upvotes']); ?> upvotes
            &nbsp;•&nbsp; <?php echo timeAgo($p['created_at']); ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<div id="addModModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:2000; align-items:center; justify-content:center;">
  <div style="background:#1e1e35; border:1px solid var(--card-border); border-radius:16px; padding:28px; max-width:420px; width:90%;">
    <h3 style="font-family:var(--font-display); font-size:18px; font-weight:700; margin-bottom:8px; color:var(--text-main);">👑 Add as Moderator</h3>
    <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">Select a community to make <strong><?php echo htmlspecialchars($usr['username']); ?></strong> a moderator.</p>
    
    <form id="addModForm">
      <input type="hidden" id="targetUserId" value="<?php echo $view_id; ?>">
      <select id="modSubSelect" style="width:100%; padding:10px; background:rgba(255,255,255,.05); border:1px solid var(--card-border); color:white; border-radius:8px; margin-bottom:16px; font-family:var(--font-body); outline:none;">
        <?php foreach($my_subs as $sub): ?>
        <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <div style="display:flex;gap:10px;">
        <button type="button" onclick="submitAddMod()" style="flex:1; padding:10px; border-radius:8px; border:none; cursor:pointer; background:var(--accent); color:white; font-family:var(--font-body); font-weight:600;">Confirm</button>
        <button type="button" onclick="closeModModal()" style="flex:1; padding:10px; border-radius:8px; border:1px solid var(--card-border); cursor:pointer; background:rgba(255,255,255,.08); color:var(--text-muted); font-family:var(--font-body); font-weight:600;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function switchTab(name, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.profile-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-'+name).classList.add('active');
  btn.classList.add('active');
}

function toggleReportMenu() {
  const menu = document.getElementById('profileReportMenu');
  if (menu.style.display === 'none' || menu.style.display === '') {
    menu.style.display = 'block';
  } else {
    menu.style.display = 'none';
  }
}

document.addEventListener('click', function(event) {
  const menu = document.getElementById('profileReportMenu');
  const btn = document.getElementById('reportMenuBtn');
  
  if (menu && menu.style.display === 'block') {
    if (!menu.contains(event.target) && event.target !== btn) {
      menu.style.display = 'none';
    }
  }});
function reportUser(userId) {
  const reason = prompt("Please provide a reason for reporting this user:");
  
  if (reason !== null && reason.trim() !== "") {
    fetch('submit_report.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ target_type: 'user', target_id: userId, reason: reason })
    })
    .then(res => res.json())
    .then(data => {
      if(data.status === 'success') {
        alert("Thank you. The user has been reported to the administrators.");
      } else {
        alert("Error reporting user: " + data.message);
      }
    })
    .catch(err => {
      alert("An error occurred while reporting the user.");
      console.error(err);
    });
    
    document.getElementById('profileReportMenu').style.display = 'none';
  } else if (reason !== null) {
    alert("Report cancelled: A reason is required.");
  }
}

// Moderator Functions
function openModModal() {
  document.getElementById('addModModal').style.display = 'flex';
  document.getElementById('profileReportMenu').style.display = 'none';
}

function closeModModal() {
  document.getElementById('addModModal').style.display = 'none';
}

function submitAddMod() {
  const userId = document.getElementById('targetUserId').value;
  const subId = document.getElementById('modSubSelect').value;

  fetch('make_moderator.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: userId, sub_id: subId })
  })
  .then(res => res.json())
  .then(data => {
    if(data.status === 'success') {
      alert("Success: User has been made a moderator!");
      closeModModal();
    } else {
      alert("Error: " + data.message);
    }
  })
  .catch(err => {
    alert("An error occurred connecting to the server.");
    console.error(err);
  });
}
</script>
</body>
</html>