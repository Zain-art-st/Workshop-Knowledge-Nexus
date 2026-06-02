-- ============================================================
--  ScholarSpace — Voting Tables (CORRECTED)
--  Run this in phpMyAdmin > scholarspace > SQL tab
-- ============================================================
 
-- First, ensure comments table exists with proper structure
CREATE TABLE IF NOT EXISTS comments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT NOT NULL,
    user_id     INT NOT NULL,
    content     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_post (post_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ── POST VOTES ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS post_votes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT NOT NULL,
    user_id     INT NOT NULL,
    vote_type   ENUM('upvote','downvote') NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_post (post_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ── COMMENT VOTES ─────────────────────────────────────────
-- Make sure comments table exists first (done above)
CREATE TABLE IF NOT EXISTS comment_votes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    comment_id  INT NOT NULL,
    user_id     INT NOT NULL,
    vote_type   ENUM('upvote','downvote') NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (comment_id, user_id),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_comment (comment_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
--  SAMPLE DATA (optional - uncomment to test)
-- ════════════════════════════════════════════════════════════════════════════
 
-- Test post creation (if user_id=2 and sub_id=1 exist)
 INSERT IGNORE INTO posts (user_id, sub_id, title, content, upvotes, downvotes)
 VALUES (2, 1, 'Sample Post Title', 'This is a sample post for testing the voting system.', 0, 0);
 
-- Test comment (if post_id=1 exists)
 INSERT IGNORE INTO comments (post_id, user_id, content)
 VALUES (1, 2, 'This is a test comment on the sample post.');
 
-- Test votes
 INSERT IGNORE INTO post_votes (post_id, user_id, vote_type) VALUES (1, 2, 'upvote');



-- Insert sample posts
INSERT INTO posts (user_id, sub_id, title, content, upvotes, downvotes) 
VALUES 
  (1, 1, 'Welcome to Programming Y1', 'This is the first post in our community!', 5, 0),
  (1, 2, 'Best Practices in Ranting', 'How to express yourself effectively', 3, 1),
  (1, 3, 'My Cute Cat', 'Just adopted this adorable kitty', 12, 0),
  (1, 4, 'What is This Thing?', 'Found this weird object, anyone know what it is?', 2, 0),
  (1, 5, 'CS Algorithm Deep Dive', 'Exploring sorting algorithms and their complexity', 8, 1),
  (1, 6, 'Neural Networks Explained', 'A beginner guide to understanding neural nets', 6, 0);



INSERT INTO posts (user_id, sub_id, title, content, upvotes, downvotes) 
VALUES 
  (1, 1, 'Variables and Data Types', 'Understanding different data types in programming', 4, 0),
  (1, 2, 'Rant About Monday Mornings', 'Why does Monday have to come so soon?', 7, 2),
  (1, 3, 'Funny Cat Videos', 'Share your favorite cat videos here!', 15, 1),
  (1, 4, 'Ancient Coins Found', 'Help identify these mysterious coins', 5, 0),
  (1, 5, 'Database Optimization Tips', 'Making queries faster and more efficient', 9, 0),
  (1, 6, 'Computer Vision in 2026', 'Latest breakthroughs in CV technology', 11, 2),
  (1, 1, 'Functions and Modules', 'Breaking code into reusable pieces', 3, 0),
  (1, 2, 'Work Stress Therapy', 'How do you deal with work stress?', 6, 1);



ALTER TABLE post_votes
ADD UNIQUE (post_id, user_id);