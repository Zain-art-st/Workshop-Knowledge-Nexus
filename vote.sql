-- Run this after running setup.sql

ALTER TABLE posts
ADD COLUMN upvote_users TEXT NULL,
ADD COLUMN downvote_users TEXT NULL;


ALTER TABLE comments
ADD upvote_users JSON NULL,
ADD downvote_users JSON NULL,
ADD downvotes INT DEFAULT 0;