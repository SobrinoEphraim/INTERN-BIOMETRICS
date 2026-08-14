-- ============================================================
-- Class Record Module - Schema updates
-- Run this AFTER feedback_schema.sql (same database: trainee_eval_system)
-- Import via phpMyAdmin > trainee_eval_system > Import
-- ============================================================

USE trainee_eval_system;

-- Tags an exam with a quarter, week, and grading category so its
-- graded scores can be pulled automatically into the Class Record.
-- category: written (Written Evaluation, 40%), clinical (Clinical
-- Performance Tasks, 40%), behavioral (Behavioral Assessment, 20%)
ALTER TABLE exams
    ADD COLUMN quarter TINYINT NULL COMMENT '1, 2, 3, or 4 -- leave NULL if not part of the Class Record',
    ADD COLUMN week_number TINYINT NULL COMMENT 'optional, for ordering weekly quizzes',
    ADD COLUMN category ENUM('written', 'clinical', 'behavioral') NOT NULL DEFAULT 'written';
