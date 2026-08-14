-- ============================================================
-- Exam Module - Additional Tables
-- Run this AFTER schema.sql and evaluations_schema.sql
-- (same database: trainee_eval_system)
-- Import via phpMyAdmin > trainee_eval_system > Import
-- ============================================================

USE trainee_eval_system;

-- ------------------------------------------------------------
-- Table: exams
-- One row per exam/quiz created by an admin.
-- target_role controls who can see and take it.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exams (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200) NOT NULL,
    description  TEXT NULL,
    target_role  ENUM('trainee', 'consultant', 'rater', 'all') NOT NULL DEFAULT 'trainee',
    status       ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    created_by   INT NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Table: exam_questions
-- question_type: essay (free text), multiple_choice (pick one),
-- checkbox (pick multiple)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_questions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    exam_id       INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('essay', 'multiple_choice', 'checkbox') NOT NULL DEFAULT 'essay',
    sort_order    INT NOT NULL DEFAULT 0,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Table: exam_options
-- Answer choices for multiple_choice / checkbox questions.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_options (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    question_id  INT NOT NULL,
    option_text  VARCHAR(500) NOT NULL,
    sort_order   INT NOT NULL DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES exam_questions(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Table: exam_submissions
-- One row per user attempt of an exam.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_submissions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    exam_id       INT NOT NULL,
    user_id       INT NOT NULL,
    status        ENUM('in_progress', 'submitted') NOT NULL DEFAULT 'in_progress',
    submitted_at  DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attempt (exam_id, user_id)
);

-- ------------------------------------------------------------
-- Table: exam_answers
-- Free text answers (for essay questions).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_answers (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    submission_id  INT NOT NULL,
    question_id    INT NOT NULL,
    answer_text    TEXT NULL,
    FOREIGN KEY (submission_id) REFERENCES exam_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES exam_questions(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Table: exam_answer_options
-- Selected option(s) for multiple_choice / checkbox questions.
-- One row per selected option (checkbox can have several rows).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_answer_options (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    submission_id  INT NOT NULL,
    question_id    INT NOT NULL,
    option_id      INT NOT NULL,
    FOREIGN KEY (submission_id) REFERENCES exam_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES exam_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES exam_options(id) ON DELETE CASCADE
);
