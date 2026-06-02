-- Run in phpMyAdmin or MySQL to set up the database :D tak faham tanya

CREATE DATABASE IF NOT EXISTS scholarspace;
USE scholarspace;

-- Users table 
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('student', 'graduate') NOT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Student profile
CREATE TABLE IF NOT EXISTS student_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    matric_number VARCHAR(50) NOT NULL UNIQUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Graduate profile
CREATE TABLE IF NOT EXISTS graduate_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    job_status ENUM('employed', 'self-employed', 'freelance', 'unemployed', 'further_study') NOT NULL,
    company VARCHAR(200) DEFAULT NULL,
    job_title VARCHAR(150) DEFAULT NULL,
    salary_range VARCHAR(50) DEFAULT NULL,
    education_level ENUM('bachelor', 'master', 'phd', 'diploma', 'other') NOT NULL,
    field_of_study VARCHAR(150) DEFAULT NULL,
    graduation_year YEAR DEFAULT NULL,
    linkedin_url VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    skills TEXT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Subcommunities
CREATE TABLE IF NOT EXISTS subcommunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    member_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Recent visits
CREATE TABLE IF NOT EXISTS recent_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sub_id INT NOT NULL,
    visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sub_id) REFERENCES subcommunities(id) ON DELETE CASCADE
);

-- Posts
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sub_id INT NOT NULL,
    title VARCHAR(300) NOT NULL,
    content TEXT,
    image_url VARCHAR(255) DEFAULT NULL,
    upvotes INT DEFAULT 0,
    downvotes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sub_id) REFERENCES subcommunities(id) ON DELETE CASCADE
);

-- Seed for subcommunities
INSERT IGNORE INTO subcommunities (name, description, member_count) VALUES
('/Sub - Programming Y1', 'Sub community on sharing notes, ideas around year 1 DCG', 234),
('/Sub - Ranting', 'Let it all out, no one cares', 891),
('/Sub - Cats', 'Kitty where?', 1200),
('/Sub - WhatIsThisThing?', 'Sub to understand whatever you found and can\'t understand where it\'s from', 456),
('/Sub - ComputerScience', 'Discuss CS concepts, papers and theories', 2400),
('/Sub - MachineLearning', 'ML research and projects', 1800);

CREATE TABLE IF NOT EXISTS sub_bans (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    sub_id     INT NOT NULL,
    user_id    INT NOT NULL,
    banned_by  INT NOT NULL,
    reason     VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sub_ban (sub_id, user_id),
    FOREIGN KEY (sub_id)    REFERENCES subcommunities(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS recent_searches (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    query       VARCHAR(200) NOT NULL,
    searched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_search (user_id, query),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    post_id    INT NOT NULL,
    user_id    INT NOT NULL,
    parent_id  INT DEFAULT NULL,
    content    TEXT NOT NULL,
    upvotes    INT DEFAULT 0,
    is_removed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id)   REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
);


ALTER TABLE subcommunities ADD COLUMN IF NOT EXISTS banner_image VARCHAR(255) DEFAULT NULL;
ALTER TABLE subcommunities ADD COLUMN IF NOT EXISTS rules TEXT DEFAULT NULL;
