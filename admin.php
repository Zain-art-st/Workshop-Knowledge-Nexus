<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

//moderation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action    = $_POST['action'];
    $target_id = (int)($_POST['target_id'] ?? 0);
    $type      = $_POST['target_type'] ?? '';
    $report_id = (int)($_POST['report_id'] ?? 0);

    //eKYC approval
    if ($action === 'kyc_approve' && $target_id) {
        mysqli_query($conn, "UPDATE users SET kyc_status='approved' WHERE id=$target_id");
        // Notify user via email
        $urow = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT email, username FROM users WHERE id=$target_id LIMIT 1"));
        if ($urow) {
            include_once "mailer.php";
            $subject = "ScholarSpace — Identity Verified ✅";
            $body    = "
            <div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;background:#0d0d1a;color:#f0eff5;border-radius:16px;overflow:hidden;'>
              <div style='background:linear-gradient(135deg,#4f8ef7,#c06de8);padding:28px;text-align:center;'>
                <h1 style='margin:0;font-size:24px;'>ScholarSpace</h1>
              </div>
              <div style='padding:28px;text-align:center;'>
                <div style='font-size:48px;margin-bottom:12px;'>✅</div>
                <h2 style='margin-bottom:8px;'>Identity Verified!</h2>
                <p style='color:#9b9ab0;font-size:14px;'>Hi <strong style='color:#f0eff5;'>{$urow['username']}</strong>, your matric card has been verified by an admin. You now have full access to ScholarSpace.</p>
              </div>
            </div>";
            //reusing sendOTPEmail 
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP(); $mail->Host='smtp.gmail.com'; $mail->SMTPAuth=true;
                $mail->Username=MAIL_FROM; $mail->Password=MAIL_PASSWORD;
                $mail->SMTPSecure=\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; $mail->Port=587;
                $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                $mail->addAddress($urow['email'], $urow['username']);
                $mail->isHTML(true); $mail->Subject=$subject; $mail->Body=$body;
                $mail->send();
            } catch (\Exception $e) { /* silent fail */ }
        }
        header("Location: admin.php?tab=kyc"); exit();
    }

    if ($action === 'kyc_reject' && $target_id) {
        $reason = trim($_POST['reject_reason'] ?? 'Image unclear or invalid.');
        $reason_safe = mysqli_real_escape_string($conn, $reason);
        mysqli_query($conn,
            "UPDATE users SET kyc_status='rejected', kyc_reason='$reason_safe' WHERE id=$target_id");
        // Notify user
        $urow = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT email, username FROM users WHERE id=$target_id LIMIT 1"));
        if ($urow) {
            include_once "mailer.php";
            $subject = "ScholarSpace — Identity Verification Failed";
            $body    = "
            <div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;background:#0d0d1a;color:#f0eff5;border-radius:16px;overflow:hidden;'>
              <div style='background:linear-gradient(135deg,#4f8ef7,#c06de8);padding:28px;text-align:center;'>
                <h1 style='margin:0;font-size:24px;'>ScholarSpace</h1>
              </div>
              <div style='padding:28px;text-align:center;'>
                <div style='font-size:48px;margin-bottom:12px;'>❌</div>
                <h2 style='margin-bottom:8px;'>Verification Failed</h2>
                <p style='color:#9b9ab0;font-size:14px;'>Hi <strong style='color:#f0eff5;'>{$urow['username']}</strong>, your matric card verification was rejected.</p>
                <p style='color:#ff7a8a;font-size:13px;margin-top:12px;'>Reason: <em>$reason</em></p>
                <p style='color:#9b9ab0;font-size:13px;margin-top:12px;'>Please log in and resubmit a clearer photo of your matric card.</p>
              </div>
            </div>";
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP(); $mail->Host='smtp.gmail.com'; $mail->SMTPAuth=true;
                $mail->Username=MAIL_FROM; $mail->Password=MAIL_PASSWORD;
                $mail->SMTPSecure=\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; $mail->Port=587;
                $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                $mail->addAddress($urow['email'], $urow['username']);
                $mail->isHTML(true); $mail->Subject=$subject; $mail->Body=$body;
                $mail->send();
            } catch (\Exception $e) { /* silent fail */ }
        }
        header("Location: admin.php?tab=kyc"); exit();
    }

    if ($type === 'user' && $target_id) {
        if ($action === 'suspend') {
            mysqli_query($conn, "UPDATE users SET is_suspended=1 WHERE id=$target_id AND user_type!='admin'");
        } elseif ($action === 'unsuspend') {
            mysqli_query($conn, "UPDATE users SET is_suspended=0 WHERE id=$target_id");
        } elseif ($action === 'ban') {
            mysqli_query($conn, "UPDATE users SET is_banned=1, is_suspended=0 WHERE id=$target_id AND user_type!='admin'");
        } elseif ($action === 'unban') {
            mysqli_query($conn, "UPDATE users SET is_banned=0 WHERE id=$target_id");
        }
    } elseif ($type === 'post' && $target_id) {
        if ($action === 'remove') {
            mysqli_query($conn, "UPDATE posts SET is_removed=1 WHERE id=$target_id");
        } elseif ($action === 'restore') {
            mysqli_query($conn, "UPDATE posts SET is_removed=0 WHERE id=$target_id");
        }
    }

    if ($report_id) {
        $status = ($action === 'dismiss') ? 'dismissed' : 'resolved';
        mysqli_query($conn, "UPDATE reports SET status='$status' WHERE id=$report_id");
    }

    header("Location: admin.php?tab=" . ($_POST['tab'] ?? 'dashboard'));
    exit();
}

//Stats on dashboard amdin (Added safe fallbacks)
$total_users   = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE user_type!='admin'"))['c'] ?? 0;
$today_signups = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE DATE(created_at)=CURDATE() AND user_type!='admin'"))['c'] ?? 0;
$last_month_u  = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND user_type!='admin'"))['c'] ?? 0;
$total_posts   = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM posts"))['c'] ?? 0;
$today_posts   = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM posts WHERE DATE(created_at)=CURDATE()"))['c'] ?? 0;
$pending_reports = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports WHERE status='pending'"))['c'] ?? 0;
$pending_kyc     = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE kyc_status='pending'"))['c'] ?? 0;

//KYC list 
$kyc_res = mysqli_query($conn,
    "SELECT id, username, email, user_type, kyc_image, kyc_status, kyc_reason, created_at
     FROM users WHERE kyc_status='pending'
     ORDER BY created_at ASC");
$kyc_list = $kyc_res ? mysqli_fetch_all($kyc_res, MYSQLI_ASSOC) : [];

//KYC resolved 
$kyc_done_res = mysqli_query($conn,
    "SELECT id, username, email, user_type, kyc_status, kyc_reason, created_at
     FROM users WHERE kyc_status IN ('approved','rejected')
     ORDER BY created_at DESC LIMIT 20");
$kyc_done = $kyc_done_res ? mysqli_fetch_all($kyc_done_res, MYSQLI_ASSOC) : [];

//Reports
$reports_res = mysqli_query($conn,
    "SELECT r.*, u.username AS reporter_name,
            IF(r.target_type='user',
               (SELECT username FROM users WHERE id=r.target_id),
               (SELECT title FROM posts WHERE id=r.target_id)) AS target_name,
            IF(r.target_type='user',
               (SELECT is_suspended FROM users WHERE id=r.target_id), NULL) AS is_suspended,
            IF(r.target_type='user',
               (SELECT is_banned FROM users WHERE id=r.target_id), NULL) AS is_banned
     FROM reports r
     JOIN users u ON r.reporter_id=u.id
     WHERE r.status='pending'
     ORDER BY r.created_at DESC");
$reports = $reports_res ? mysqli_fetch_all($reports_res, MYSQLI_ASSOC) : [];

//moderation tab
$users_res = mysqli_query($conn,
    "SELECT id, username, email, user_type, is_suspended, is_banned, created_at
     FROM users WHERE user_type != 'admin'
     ORDER BY created_at DESC LIMIT 50");
$all_users = $users_res ? mysqli_fetch_all($users_res, MYSQLI_ASSOC) : [];

$active_tab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard – ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .admin-layout { display:flex; min-height:100vh; padding-top:58px; }

    /* Left sidebar */
    .admin-sidebar {
      width:200px; flex-shrink:0; position:fixed; top:58px; left:0;
      height:calc(100vh - 58px);
      background:rgba(13,13,26,.95); backdrop-filter:blur(20px);
      border-right:1px solid var(--card-border);
      display:flex; flex-direction:column; padding:20px 0;
      z-index:100;
    }
    .admin-brand {
      font-family:var(--font-display); font-size:16px; font-weight:800;
      padding:0 20px 20px; border-bottom:1px solid var(--card-border);
      background:linear-gradient(135deg,#fff,var(--accent));
      -webkit-background-clip:text; -webkit-text-fill-color:transparent;
      background-clip:text;
    }
    .admin-nav { flex:1; padding-top:12px; }
    .admin-nav-item {
      display:flex; align-items:center; gap:10px; padding:12px 20px;
      font-size:14px; font-weight:500; color:var(--text-muted);
      text-decoration:none; transition:all .2s; cursor:pointer;
      border-left:3px solid transparent; background:none; border-top:none;
      border-right:none; border-bottom:none; width:100%; font-family:var(--font-body);
    }
    .admin-nav-item:hover { color:var(--text-main); background:rgba(255,255,255,.05); }
    .admin-nav-item.active { color:var(--accent); background:rgba(79,142,247,.08); border-left-color:var(--accent); }
    .admin-logout {
      padding:20px; border-top:1px solid var(--card-border);
    }
    .admin-logout a { font-size:13px; color:var(--danger); text-decoration:none; }

    /* Main content */
    .admin-main { margin-left:200px; flex:1; padding:32px; }
    .admin-top { display:flex; align-items:center; gap:16px; margin-bottom:28px; }
    .admin-top h1 { font-family:var(--font-display); font-size:24px; font-weight:800; }

    /* Stats grid */
    .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px; }
    .stat-card {
      background:var(--bg-card); backdrop-filter:blur(16px);
      border:1px solid var(--card-border); border-radius:16px; padding:20px 24px;
    }
    .stat-card-label { font-size:13px; color:var(--text-muted); margin-bottom:8px; }
    .stat-card-value { font-family:var(--font-display); font-size:32px; font-weight:800; margin-bottom:6px; }
    .stat-card-change { font-size:12px; }
    .stat-card-change.up   { color:var(--success); }
    .stat-card-change.warn { color:var(--warning); }

    /* Section */
    .admin-section {
      background:var(--bg-card); backdrop-filter:blur(16px);
      border:1px solid var(--card-border); border-radius:16px; overflow:hidden; margin-bottom:20px;
    }
    .admin-section-header {
      padding:16px 20px; border-bottom:1px solid var(--card-border);
      font-family:var(--font-display); font-size:15px; font-weight:700;
      display:flex; align-items:center; gap:10px;
    }
    .badge-count {
      background:var(--danger); color:#fff; font-size:11px; font-weight:700;
      padding:2px 8px; border-radius:10px;
    }

    /* Report card */
    .report-item {
      padding:16px 20px; border-bottom:1px solid var(--card-border);
      display:flex; gap:16px; align-items:flex-start;
    }
    .report-item:last-child { border-bottom:none; }
    .report-type-badge {
      padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;
      flex-shrink:0; margin-top:2px;
    }
    .report-type-badge.user { background:rgba(245,158,11,.15); color:var(--warning); border:1px solid rgba(245,158,11,.3); }
    .report-type-badge.post { background:rgba(255,79,106,.15); color:var(--danger); border:1px solid rgba(255,79,106,.3); }
    .report-info { flex:1; }
    .report-target { font-weight:700; font-size:14px; margin-bottom:4px; }
    .report-reason { font-size:13px; color:var(--text-muted); margin-bottom:4px; }
    .report-by    { font-size:11px; color:var(--text-muted); }
    .report-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }

    /* Action buttons */
    .btn-action {
      padding:6px 14px; border-radius:8px; font-size:12px; font-weight:600;
      border:none; cursor:pointer; font-family:var(--font-body); transition:opacity .2s;
    }
    .btn-action:hover { opacity:.85; }
    .btn-suspend { background:rgba(245,158,11,.2); color:var(--warning); border:1px solid rgba(245,158,11,.4); }
    .btn-ban     { background:rgba(255,79,106,.2); color:var(--danger);  border:1px solid rgba(255,79,106,.4); }
    .btn-remove  { background:rgba(255,79,106,.2); color:var(--danger);  border:1px solid rgba(255,79,106,.4); }
    .btn-dismiss { background:rgba(255,255,255,.08); color:var(--text-muted); border:1px solid var(--card-border); }
    .btn-restore { background:rgba(62,207,142,.15); color:var(--success); border:1px solid rgba(62,207,142,.3); }

    /* Users table */
    .users-table { width:100%; border-collapse:collapse; }
    .users-table th { padding:10px 16px; text-align:left; font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid var(--card-border); }
    .users-table td { padding:12px 16px; font-size:13px; border-bottom:1px solid rgba(255,255,255,.04); }
    .users-table tr:last-child td { border-bottom:none; }
    .user-status { padding:3px 8px; border-radius:10px; font-size:11px; font-weight:600; }
    .user-status.active    { background:rgba(62,207,142,.15);color:var(--success); }
    .user-status.suspended { background:rgba(245,158,11,.15); color:var(--warning); }
    .user-status.banned    { background:rgba(255,79,106,.15);  color:var(--danger); }

    /* Empty state */
    .admin-empty { text-align:center; padding:60px 20px; }
    .admin-empty-icon  { font-size:48px; margin-bottom:12px; }
    .admin-empty-title { font-family:var(--font-display); font-size:16px; margin-bottom:6px; }
    .admin-empty-sub   { font-size:13px; color:var(--text-muted); }

    /* Tabs */
    .admin-content-tab { display:none; }
    .admin-content-tab.active { display:block; }
  </style>
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div> <header class="navbar">
  <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-right" style="margin-left:auto;">
    <a href="dashboard.php" style="font-size:13px;color:var(--text-muted);text-decoration:none;">← Back to Feed</a>
  </div>
</header>

<div class="admin-layout">
<aside class="admin-sidebar">
    <div class="admin-brand">ScholarSpace<br><span style="font-size:11px;font-weight:400;opacity:.6;">Admin Panel</span></div>
    <nav class="admin-nav">
      <button type="button" class="admin-nav-item <?php echo $active_tab==='dashboard'?'active':''; ?>" data-target="dashboard" onclick="switchAdminTab('dashboard')">
        🏠 Dashboard
      </button>
      <button type="button" class="admin-nav-item <?php echo $active_tab==='moderation'?'active':''; ?>" data-target="moderation" onclick="switchAdminTab('moderation')">
        🚩 Moderation
        <?php if($pending_reports>0): ?>
        <span class="badge-count"><?php echo $pending_reports; ?></span>
        <?php endif; ?>
      </button>
      <button type="button" class="admin-nav-item <?php echo $active_tab==='users'?'active':''; ?>" data-target="users" onclick="switchAdminTab('users')">
        👥 Users
      </button>
      <button type="button" class="admin-nav-item <?php echo $active_tab==='kyc'?'active':''; ?>" data-target="kyc" onclick="switchAdminTab('kyc')">
        🪪 eKYC Approvals
        <?php if ($pending_kyc > 0): ?>
        <span class="badge-count"><?php echo $pending_kyc; ?></span>
        <?php endif; ?>
      </button>
    </nav>
    <div class="admin-logout">
      <a href="logout.php">🚪 Log Out</a>
    </div>
  </aside>

<main class="admin-main">

  <div class="admin-content-tab <?php echo $active_tab==='dashboard'?'active':''; ?>" id="tab-dashboard">
      <div class="admin-top">
        <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:22px;">⚙️</div>
        <div>
          <h1>Administrator</h1>
          <div style="font-size:13px;color:var(--text-muted);">ScholarSpace Control Panel</div>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-label">Total Users</div>
          <div class="stat-card-value"><?php echo number_format($total_users); ?></div>
          <div class="stat-card-change up">+<?php echo $last_month_u; ?> this month</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-label">Today's Signups</div>
          <div class="stat-card-value"><?php echo $today_signups; ?></div>
          <div class="stat-card-change <?php echo $today_signups>0?'up':''; ?>">
            <?php echo $today_signups>0 ? 'New members today' : 'No signups yet today'; ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-label">Total Posts</div>
          <div class="stat-card-value"><?php echo number_format($total_posts); ?></div>
          <div class="stat-card-change up">+<?php echo $today_posts; ?> today</div>
        </div>
        <div class="stat-card" style="border-left:3px solid var(--warning);cursor:pointer;" onclick="switchAdminTab('kyc')">
          <div class="stat-card-label">⏳ Pending eKYC</div>
          <div class="stat-card-value" style="color:var(--warning);"><?php echo $pending_kyc; ?></div>
          <div class="stat-card-change warn"><?php echo $pending_kyc > 0 ? 'Awaiting review' : 'All clear'; ?></div>
        </div>
      </div>

      <div class="admin-section">
        <div class="admin-section-header">
          🚩 Pending Reports
          <?php if($pending_reports>0): ?><span class="badge-count"><?php echo $pending_reports; ?></span><?php endif; ?>
        </div>
        <?php if(empty($reports)): ?>
        <div class="admin-empty">
          <div class="admin-empty-icon">🏳️</div>
          <div class="admin-empty-title">No reported content</div>
          <div class="admin-empty-sub">No reported content so far</div>
        </div>
        <?php else: ?>
        <?php foreach(array_slice($reports,0,5) as $r): ?>
        <div class="report-item">
          <span class="report-type-badge <?php echo $r['target_type']; ?>">
            <?php echo strtoupper($r['target_type']); ?>
          </span>
          <div class="report-info">
            <div class="report-target"><?php echo htmlspecialchars($r['target_name']??'[deleted]'); ?></div>
            <div class="report-reason">"<?php echo htmlspecialchars($r['reason']); ?>"</div>
            <div class="report-by">Reported by <?php echo htmlspecialchars($r['reporter_name']); ?> • <?php echo date('M j, Y', strtotime($r['created_at'])); ?></div>
            <div class="report-actions">
              <?php if($r['target_type']==='user'): ?>
                <?php if(!$r['is_suspended']&&!$r['is_banned']): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="suspend">
                  <input type="hidden" name="target_type" value="user">
                  <input type="hidden" name="target_id" value="<?php echo $r['target_id']; ?>">
                  <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                  <input type="hidden" name="tab" value="dashboard">
                  <button type="submit" class="btn-action btn-suspend">⏸ Suspend</button>
                </form>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="ban">
                  <input type="hidden" name="target_type" value="user">
                  <input type="hidden" name="target_id" value="<?php echo $r['target_id']; ?>">
                  <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                  <input type="hidden" name="tab" value="dashboard">
                  <button type="submit" class="btn-action btn-ban" onclick="return confirm('Ban this user permanently?')">🚫 Ban</button>
                </form>
                <?php else: ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="<?php echo $r['is_banned']?'unban':'unsuspend'; ?>">
                  <input type="hidden" name="target_type" value="user">
                  <input type="hidden" name="target_id" value="<?php echo $r['target_id']; ?>">
                  <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                  <input type="hidden" name="tab" value="dashboard">
                  <button type="submit" class="btn-action btn-restore">✅ Continue as Normal</button>
                </form>
                <?php endif; ?>
              <?php else: ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="target_type" value="post">
                  <input type="hidden" name="target_id" value="<?php echo $r['target_id']; ?>">
                  <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                  <input type="hidden" name="tab" value="dashboard">
                  <button type="submit" class="btn-action btn-remove">🗑 Remove Post</button>
                </form>
              <?php endif; ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="dismiss">
                <input type="hidden" name="target_type" value="<?php echo $r['target_type']; ?>">
                <input type="hidden" name="target_id" value="<?php echo $r['target_id']; ?>">
                <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                <input type="hidden" name="tab" value="dashboard">
                <button type="submit" class="btn-action btn-dismiss">✕ Dismiss</button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(count($reports)>5): ?>
        <div style="padding:12px 20px;text-align:center;">
          <button type="button" class="btn-action btn-dismiss" onclick="switchAdminTab('moderation')">
            View all <?php echo count($reports); ?> reports →
          </button>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

  <div class="admin-content-tab <?php echo $active_tab==='moderation'?'active':''; ?>" id="tab-moderation">
      <div class="admin-top">
        <h1>Moderation</h1>
      </div>
      <div class="admin-section">
        <div class="admin-section-header">
          All Pending Reports
          <?php if($pending_reports>0): ?><span class="badge-count"><?php echo $pending_reports; ?></span><?php endif; ?>
        </div>
        <?php if(empty($reports)): ?>
        <div class="admin-empty">
          <div class="admin-empty-icon">🏳️</div>
          <div class="admin-empty-title">All clear!</div>
          <div class="admin-empty-sub">No reported content so far</div>
        </div>
        <?php else: ?>
        <?php foreach($reports as $r): ?>
        <div class="report-item">
          <span class="report-type-badge <?php echo $r['target_type']; ?>"><?php echo strtoupper($r['target_type']); ?></span>
          <div class="report-info">
            <div class="report-target"><?php echo htmlspecialchars($r['target_name']??'[deleted]'); ?></div>
            <div class="report-reason">"<?php echo htmlspecialchars($r['reason']); ?>"</div>
            <div class="report-by">Reported by <?php echo htmlspecialchars($r['reporter_name']); ?> • <?php echo date('M j, g:i a', strtotime($r['created_at'])); ?></div>
            <div class="report-actions">
              <?php if($r['target_type']==='user'): ?>
                <?php if(!$r['is_suspended']&&!$r['is_banned']): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="suspend"><input type="hidden" name="target_type" value="user">
                  <input type="hidden" name="target_id" value="<?php echo $r['target_id']; ?>"><input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                  <input type="hidden" name="tab" value="moderation">
                  <button type="submit" class="btn-action btn-suspend">⏸ Suspend</button>
                </form>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="ban"><input type="hidden" name="target_type" value="user">
                  <input type="hidden" name="target_id" value="<?php echo $r['target_id']; ?>"><input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                  <input type="hidden" name="tab" value="moderation">
                  <button type="submit" class="btn-action btn-ban" onclick="return confirm('Permanently ban this user?')">🚫 Ban</button>
                </form>
                <?php else: ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="<?php echo $r['is_banned']?'unban':'unsuspend'; ?>"><input type="hidden" name="target_type" value="user">
                  <input type="hidden" name="target_id" value="<?php echo $r['target_id']; ?>"><input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                  <input type="hidden" name="tab" value="moderation">
                  <button type="submit" class="btn-action btn-restore">✅ Continue as Normal</button>
                </form>
                <?php endif; ?>
              <?php else: ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="remove"><input type="hidden" name="target_type" value="post">
                  <input type="hidden" name="target_id" value="<?php echo $r['target_id']; ?>"><input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                  <input type="hidden" name="tab" value="moderation">
                  <button type="submit" class="btn-action btn-remove">🗑 Remove Post</button>
                </form>
              <?php endif; ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="dismiss"><input type="hidden" name="target_type" value="<?php echo $r['target_type']; ?>">
                <input type="hidden" name="target_id" value="<?php echo $r['target_id']; ?>"><input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                <input type="hidden" name="tab" value="moderation">
                <button type="submit" class="btn-action btn-dismiss">✕ Dismiss</button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  <div class="admin-content-tab <?php echo $active_tab==='users'?'active':''; ?>" id="tab-users">
      <div class="admin-top"><h1>User Management</h1></div>
      <div class="admin-section">
        <div class="admin-section-header">All Users (latest 50)</div>
        <table class="users-table">
          <thead>
            <tr>
              <th>Username</th><th>Email</th><th>Type</th><th>Joined</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($all_users as $u): ?>
          <tr>
            <td><a href="profile.php?id=<?php echo $u['id']; ?>" style="color:var(--accent);text-decoration:none;"><?php echo htmlspecialchars($u['username']); ?></a></td>
            <td style="color:var(--text-muted);"><?php echo htmlspecialchars($u['email']); ?></td>
            <td><span class="status-badge <?php echo $u['user_type']; ?>" style="font-size:11px;"><?php echo $u['user_type']; ?></span></td>
            <td style="color:var(--text-muted);"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
            <td>
              <?php if($u['is_banned']): ?>
                <span class="user-status banned">Banned</span>
              <?php elseif($u['is_suspended']): ?>
                <span class="user-status suspended">Suspended</span>
              <?php else: ?>
                <span class="user-status active">Active</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if(!$u['is_banned']&&!$u['is_suspended']): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="suspend"><input type="hidden" name="target_type" value="user">
                  <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>"><input type="hidden" name="tab" value="users">
                  <button type="submit" class="btn-action btn-suspend">⏸</button>
                </form>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="ban"><input type="hidden" name="target_type" value="user">
                  <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>"><input type="hidden" name="tab" value="users">
                  <button type="submit" class="btn-action btn-ban" onclick="return confirm('Ban <?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>?')">🚫</button>
                </form>
                <?php else: ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="<?php echo $u['is_banned']?'unban':'unsuspend'; ?>"><input type="hidden" name="target_type" value="user">
                  <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>"><input type="hidden" name="tab" value="users">
                  <button type="submit" class="btn-action btn-restore">✅</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  <div class="admin-content-tab <?php echo $active_tab==='kyc'?'active':''; ?>" id="tab-kyc">
      <div class="admin-top"><h1>🪪 eKYC Approvals</h1></div>

    <div class="admin-section" style="margin-bottom:24px;">
        <div class="admin-section-header">
          ⏳ Pending Verification
          <?php if ($pending_kyc > 0): ?>
          <span class="badge-count"><?php echo $pending_kyc; ?></span>
          <?php endif; ?>
        </div>

        <?php if (empty($kyc_list)): ?>
        <div class="admin-empty">
          <div class="admin-empty-icon">🎉</div>
          <div class="admin-empty-title">All clear!</div>
          <div class="admin-empty-sub">No pending eKYC submissions.</div>
        </div>
        <?php else: ?>
        <?php foreach ($kyc_list as $k): ?>
        <div style="padding:20px;border-bottom:1px solid var(--card-border);display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

        <div>
            <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">Submitted Matric Card</div>
            <?php if (!empty($k['kyc_image']) && file_exists(__DIR__.'/'.$k['kyc_image'])): ?>
            <img src="<?php echo htmlspecialchars($k['kyc_image']); ?>"
                 alt="Matric card"
                 style="width:100%;max-height:260px;object-fit:contain;border-radius:10px;border:1px solid var(--card-border);background:#000;cursor:pointer;"
                 onclick="this.style.maxHeight=this.style.maxHeight==='none'?'260px':'none'">
            <div style="font-size:11px;color:var(--text-muted);margin-top:6px;">Click image to expand</div>
            <?php else: ?>
            <div style="padding:40px;text-align:center;background:rgba(255,255,255,.04);border:2px dashed var(--card-border);border-radius:10px;color:var(--text-muted);font-size:13px;">
              No image uploaded
            </div>
            <?php endif; ?>
          </div>

        <div>
            <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">Submitted Information</div>

            <div style="background:rgba(255,255,255,.05);border:1px solid var(--card-border);border-radius:10px;padding:16px;margin-bottom:16px;">
              <div style="display:flex;gap:12px;margin-bottom:10px;">
                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:#fff;flex-shrink:0;">
                  <?php echo strtoupper(substr($k['username'],0,1)); ?>
                </div>
                <div>
                  <div style="font-weight:700;font-size:15px;"><?php echo htmlspecialchars($k['username']); ?></div>
                  <div style="font-size:12px;color:var(--text-muted);"><?php echo htmlspecialchars($k['email']); ?></div>
                </div>
              </div>
              <?php
              $matric = '—';
              $q_student = mysqli_query($conn, "SELECT matric_number FROM student_profiles WHERE user_id=" . intval($k['id']) . " LIMIT 1");
              
              if ($q_student && mysqli_num_rows($q_student) > 0) {
                  $matric_row = mysqli_fetch_assoc($q_student);
                  $matric = $matric_row['matric_number'];
              } else {
                  $q_grad = mysqli_query($conn, "SELECT matric_number FROM graduate_profiles WHERE user_id=" . intval($k['id']) . " LIMIT 1");
                  if ($q_grad && mysqli_num_rows($q_grad) > 0) {
                      $matric_row = mysqli_fetch_assoc($q_grad);
                      $matric = $matric_row['matric_number'];
                  }
              }
              // ------------------------------------------------------------------------
              ?>
              <div style="display:flex;justify-content:space-between;padding:8px 0;border-top:1px solid var(--card-border);font-size:13px;">
                <span style="color:var(--text-muted);">Account Type</span>
                <span class="status-badge <?php echo $k['user_type']; ?>" style="font-size:11px;">
                  <?php echo ucfirst($k['user_type']); ?>
                </span>
              </div>
              <div style="display:flex;justify-content:space-between;padding:8px 0;border-top:1px solid var(--card-border);font-size:13px;">
                <span style="color:var(--text-muted);">Matric Number</span>
                <strong style="font-size:15px;letter-spacing:1px;color:var(--accent);"><?php echo htmlspecialchars($matric); ?></strong>
              </div>
              <div style="display:flex;justify-content:space-between;padding:8px 0;border-top:1px solid var(--card-border);font-size:13px;">
                <span style="color:var(--text-muted);">Submitted</span>
                <span><?php echo date('M j, Y g:i a', strtotime($k['created_at'])); ?></span>
              </div>
            </div>

          <form method="POST" style="margin-bottom:10px;">
              <input type="hidden" name="action" value="kyc_approve">
              <input type="hidden" name="target_id" value="<?php echo $k['id']; ?>">
              <input type="hidden" name="tab" value="kyc">
              <button type="submit" class="btn-action btn-restore"
                      style="width:100%;padding:10px;font-size:14px;border-radius:10px;"
                      onclick="return confirm('Approve <?php echo htmlspecialchars($k['username'], ENT_QUOTES); ?>\'s identity?')">
                ✅ Approve
              </button>
            </form>

          <div style="background:rgba(255,79,106,.06);border:1px solid rgba(255,79,106,.2);border-radius:10px;padding:14px;">
              <div style="font-size:12px;font-weight:600;color:var(--danger);margin-bottom:8px;">❌ Reject Submission</div>
              <form method="POST">
                <input type="hidden" name="action" value="kyc_reject">
                <input type="hidden" name="target_id" value="<?php echo $k['id']; ?>">
                <input type="hidden" name="tab" value="kyc">
                <input type="text" name="reject_reason"
                       placeholder="Reason for rejection (sent to user)"
                       style="width:100%;padding:8px 12px;background:rgba(255,255,255,.07);border:1px solid rgba(255,79,106,.3);border-radius:8px;color:var(--text-main);font-family:var(--font-body);font-size:13px;outline:none;margin-bottom:8px;">
                <button type="submit" class="btn-action btn-ban"
                        style="width:100%;padding:8px;border-radius:8px;font-size:13px;">
                  Reject &amp; Notify User
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

    <?php if (!empty($kyc_done)): ?>
      <div class="admin-section">
        <div class="admin-section-header">Recently Resolved</div>
        <table class="users-table">
          <thead>
            <tr>
              <th>Username</th>
              <th>Email</th>
              <th>Type</th>
              <th>Result</th>
              <th>Reason</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($kyc_done as $kd): ?>
          <tr>
            <td><a href="profile.php?id=<?php echo $kd['id']; ?>" style="color:var(--accent);text-decoration:none;"><?php echo htmlspecialchars($kd['username']); ?></a></td>
            <td style="color:var(--text-muted);font-size:12px;"><?php echo htmlspecialchars($kd['email']); ?></td>
            <td><span class="status-badge <?php echo $kd['user_type']; ?>" style="font-size:11px;"><?php echo $kd['user_type']; ?></span></td>
            <td>
              <?php if ($kd['kyc_status']==='approved'): ?>
                <span class="user-status active">✅ Approved</span>
              <?php else: ?>
                <span class="user-status banned">❌ Rejected</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--text-muted);">
              <?php echo !empty($kd['kyc_reason']) ? htmlspecialchars($kd['kyc_reason']) : '—'; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    </div>

  </main>
</div>

<script>
function switchAdminTab(name) {
  document.querySelectorAll('.admin-content-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.admin-nav-item').forEach(b => b.classList.remove('active'));
  
  const tab = document.getElementById('tab-' + name);
  if (tab) tab.classList.add('active');
  
  const btn = document.querySelector(`.admin-nav-item[data-target="${name}"]`);
  if (btn) btn.classList.add('active');
  
  const url = new URL(window.location.href);
  url.searchParams.set('tab', name);
  window.history.pushState({}, '', url);
}
</script>
</body> </html>