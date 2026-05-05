-- ============================================================
--  ScholarSpace — Full Database Setup
--  Run this entire file in phpMyAdmin > scholarspace > SQL tab
-- ============================================================

CREATE DATABASE IF NOT EXISTS scholarspace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scholarspace;

-- ── USERS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100) NOT NULL UNIQUE,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    user_type     ENUM('student','graduate','admin') NOT NULL DEFAULT 'student',
    profile_photo VARCHAR(255) DEFAULT 'default.png',
    is_verified   TINYINT(1)  DEFAULT 0,
    is_suspended  TINYINT(1)  DEFAULT 0,
    is_banned     TINYINT(1)  DEFAULT 0,
    created_at    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
);

-- ── OTP CODES ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS otp_codes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(150) NOT NULL,
    otp        VARCHAR(6)   NOT NULL,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ── PENDING REGISTRATIONS ────────────────────────────────────
CREATE TABLE IF NOT EXISTS pending_registrations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    user_type     ENUM('student','graduate') NOT NULL,
    profile_photo VARCHAR(255) DEFAULT 'default.png',
    extra_data    TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── STUDENT PROFILES ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS student_profiles (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL UNIQUE,
    matric_number VARCHAR(50) NOT NULL UNIQUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── GRADUATE PROFILES ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS graduate_profiles (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT NOT NULL UNIQUE,
    job_status       ENUM('employed','self-employed','freelance','unemployed','further_study') NOT NULL,
    company          VARCHAR(200) DEFAULT NULL,
    job_title        VARCHAR(150) DEFAULT NULL,
    salary_range     VARCHAR(50)  DEFAULT NULL,
    education_level  ENUM('diploma','bachelor','master','phd','other') NOT NULL,
    field_of_study   VARCHAR(150) DEFAULT NULL,
    graduation_year  YEAR         DEFAULT NULL,
    linkedin_url     VARCHAR(255) DEFAULT NULL,
    bio              TEXT         DEFAULT NULL,
    skills           TEXT         DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── SUBCOMMUNITIES ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS subcommunities (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL UNIQUE,
    slug          VARCHAR(110) NOT NULL UNIQUE,
    description   TEXT,
    topic         VARCHAR(80)  DEFAULT NULL,
    profile_photo VARCHAR(255) DEFAULT 'default_sub.png',
    creator_id    INT          DEFAULT NULL,
    member_count  INT          DEFAULT 1,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ── SUB MEMBERSHIPS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sub_memberships (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id   INT NOT NULL,
    sub_id    INT NOT NULL,
    role      ENUM('member','moderator') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_member (user_id, sub_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sub_id)  REFERENCES subcommunities(id) ON DELETE CASCADE
);

-- ── RECENT VISITS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS recent_visits (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    sub_id     INT NOT NULL,
    visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_visit (user_id, sub_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sub_id)  REFERENCES subcommunities(id) ON DELETE CASCADE
);

-- ── POSTS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS posts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    sub_id     INT NOT NULL,
    title      VARCHAR(300) NOT NULL,
    content    TEXT,
    image_url  VARCHAR(255) DEFAULT NULL,
    link_url   VARCHAR(500) DEFAULT NULL,
    file_url   VARCHAR(255) DEFAULT NULL,
    upvotes    INT DEFAULT 0,
    downvotes  INT DEFAULT 0,
    is_removed TINYINT(1)  DEFAULT 0,
    created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sub_id)  REFERENCES subcommunities(id) ON DELETE CASCADE
);

-- ── REPORTS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reports (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    target_type ENUM('post','user') NOT NULL,
    target_id   INT NOT NULL,
    reason      VARCHAR(255) NOT NULL,
    status      ENUM('pending','resolved','dismissed') DEFAULT 'pending',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── SEED: Default subcommunities ─────────────────────────────
INSERT IGNORE INTO subcommunities (name, slug, description, topic, member_count) VALUES
('/Sub - Programming Y1', 'programming-y1',   'Sub community on sharing notes and ideas around Year 1', 'Study',  234),
('/Sub - Ranting',        'ranting',           'Let it all out, no one cares',                           'Hobby',  891),
('/Sub - Cats',           'cats',              'Kitty where?',                                            'Hobby', 1200),
('/Sub - WhatIsThisThing','whatisthis',        'Sub to understand whatever you found',                   'Hobby',  456),
('/Sub - ComputerScience','computerscience',   'Discuss CS concepts, papers and theories',               'Study', 2400),
('/Sub - MachineLearning','machinelearning',   'ML research, projects and breakthroughs',                'Study', 1800);

-- ── SEED: Admin account ──────────────────────────────────────
-- Username : admin
-- Password : Admin@1234   <-- CHANGE AFTER FIRST LOGIN
INSERT IGNORE INTO users (username, email, password, user_type, is_verified)
VALUES (
    'admin',
    'admin@scholarspace.local',
    '$2y$12$eImiTXuWVxfM37uY4JANjQ==eImiTXuWVxfM37uY4JANjQ==',
    'admin',
    1
);

-- Re-set with a proper bcrypt hash of "Admin@1234"
UPDATE users
SET password = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE username = 'admin';

-- NOTE: The hash above = "password". 
-- To set it to Admin@1234 properly, after importing run admin_reset.php once.
