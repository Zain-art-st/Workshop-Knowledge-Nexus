<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About ScholarSpace</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .about-page { max-width:700px; margin:0 auto; padding:0 20px 60px; }
    .about-hero {
      text-align:center; padding:48px 20px 36px;
    }
    .about-logo {
      font-family:var(--font-display); font-size:40px; font-weight:800;
      background:linear-gradient(135deg,#fff 0%,var(--accent) 60%,var(--accent2) 100%);
      -webkit-background-clip:text; -webkit-text-fill-color:transparent;
      background-clip:text; letter-spacing:-1px; margin-bottom:12px;
    }
    .about-tagline { font-size:16px; color:var(--text-muted); line-height:1.7; }
    .about-card {
      background:var(--bg-card); backdrop-filter:blur(16px);
      border:1px solid var(--card-border); border-radius:16px;
      padding:24px 28px; margin-bottom:16px;
    }
    .about-card h3 {
      font-family:var(--font-display); font-size:17px; font-weight:700;
      margin-bottom:12px; display:flex; align-items:center; gap:10px;
    }
    .about-card p { font-size:14px; color:var(--text-muted); line-height:1.8; }
    .about-card ul { list-style:none; padding:0; }
    .about-card ul li { font-size:14px; color:var(--text-muted); padding:6px 0;
                        border-bottom:1px solid rgba(255,255,255,.04); display:flex; align-items:center; gap:10px; }
    .about-card ul li:last-child { border-bottom:none; }
    .about-version { text-align:center; font-size:12px; color:var(--text-muted); margin-top:24px; }
  </style>
</head>
<body>
<div class="stars-bg"></div>
<div class="sunset-bg"></div>
<header class="navbar">
  <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
  <div class="nav-right" style="margin-left:auto;">
    <a href="dashboard.php" style="font-size:13px;color:var(--text-muted);text-decoration:none;">← Back</a>
  </div>
</header>
<div class="page-wrapper">
  <div class="about-page">

    <div class="about-hero">
      <div class="about-logo">ScholarSpace</div>
      <p class="about-tagline">
        The academic social platform that's LinkedIn<br>but make it actually fun.
      </p>
    </div>

    <div class="about-card">
      <h3> What is ScholarSpace?</h3>
      <p>
        ScholarSpace is a community-driven platform built for students and graduates.
        Think Reddit's community structure combined with LinkedIn's professional profile system —
        a space where you can discuss anything, share knowledge, and build your professional
        identity without the corporate stuffiness.
      </p>
    </div>

    <div class="about-card">
      <h3>Who is it for?</h3>
      <ul>
        <li><strong>Students</strong> — Share notes, ask questions, find your people</li>
        <li><strong>Graduates</strong> — Showcase your experience, connect with juniors, give back</li>
        <li><strong>Everyone</strong> — Build communities around anything you care about</li>
      </ul>
    </div>

    <div class="about-card">
      <h3>Built With</h3>
      <ul>
        <li> PHP + MySQL (XAMPP)</li>
        <li> Pure CSS with glassmorphism design</li>
        <li> PHPMailer for email OTP verification</li>
      </ul>
    </div>

    <div class="about-version">
      ScholarSpace beta 12.67.73 &nbsp;•&nbsp;
    </div>

  </div>
</div>
</body>
</html>
