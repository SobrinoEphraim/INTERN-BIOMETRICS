-- ============================================================
-- Grading Module - Schema updates
-- Run this AFTER exams_schema.sql (same database: trainee_eval_system)
-- Import via phpMyAdmin > trainee_eval_system > Import
-- ============================================================

USE trainee_eval_system;

-- Points per question (used for scoring). Default 1 point each.
ALTER TABLE exam_questions
    ADD COLUMN points INT NOT NULL DEFAULT 1;

-- Marks which option(s) are the correct answer for multiple_choice / checkbox
ALTER TABLE exam_options
    ADD COLUMN is_correct TINYINT(1) NOT NULL DEFAULT 0;

-- Tracks overall grading status + total score per submission
ALTER TABLE exam_submissions
    ADD COLUMN total_score DECIMAL(6,2) NULL,
    ADD COLUMN max_score DECIMAL(6,2) NULL,
    ADD COLUMN grading_status ENUM('pending', 'graded') NOT NULL DEFAULT 'pending';

-- ------------------------------------------------------------
-- Table: exam_question_scores
-- Points earned per question, per submission.
-- multiple_choice / checkbox: filled in automatically on submit.
-- essay: NULL until an admin manually grades it.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_question_scores (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    submission_id  INT NOT NULL,
    question_id    INT NOT NULL,
    points_earned  DECIMAL(5,2) NULL,   -- NULL = not graded yet
    graded_at      DATETIME NULL,
    FOREIGN KEY (submission_id) REFERENCES exam_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES exam_questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_score (submission_id, question_id)
);
