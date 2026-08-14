-- ============================================================
-- Super Admin + Intern roles, and intern-specific fields
-- Run this AFTER dtr_schema.sql (same database: trainee_eval_system)
-- Import via phpMyAdmin > trainee_eval_system > Import
-- ============================================================

USE trainee_eval_system;

ALTER TABLE users
    MODIFY COLUMN role ENUM('admin', 'trainee', 'consultant', 'rater', 'super_admin', 'intern') NOT NULL DEFAULT 'trainee',
    ADD COLUMN required_hours DECIMAL(6,2) NULL COMMENT 'total internship hours required (interns only)',
    ADD COLUMN school_name VARCHAR(150) NULL COMMENT 'interns only',
    ADD COLUMN course VARCHAR(150) NULL COMMENT 'interns only';
