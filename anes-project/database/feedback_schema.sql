-- ============================================================
-- Exam Feedback Module - Additional Table
-- Run this AFTER pds_schema.sql (same database: trainee_eval_system)
-- Import via phpMyAdmin > trainee_eval_system > Import
-- ============================================================

USE trainee_eval_system;

-- ------------------------------------------------------------
-- Table: exam_feedback
-- Comments left by a consultant or admin on a trainee's exam
-- submission. Multiple entries allowed (acts like a thread).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_feedback (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    submission_id  INT NOT NULL,
    reviewer_id    INT NOT NULL,
    feedback_text  TEXT NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES exam_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
);
