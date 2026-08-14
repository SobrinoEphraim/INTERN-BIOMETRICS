-- ============================================================
-- PDS (Personal Data Sheet) Module - Additional Tables
-- Run this AFTER grading_schema.sql (same database: trainee_eval_system)
-- Import via phpMyAdmin > trainee_eval_system > Import
-- ============================================================

USE trainee_eval_system;

-- ------------------------------------------------------------
-- Table: pds_forms
-- One row per PDS template created by an admin.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pds_forms (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200) NOT NULL,
    description  TEXT NULL,
    status       ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    created_by   INT NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Table: pds_fields
-- The information fields admin wants to collect.
-- field_type: text (short answer), textarea (long answer),
-- date, number, dropdown (single choice from pds_field_options)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pds_fields (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    form_id      INT NOT NULL,
    field_label  VARCHAR(300) NOT NULL,
    field_type   ENUM('text', 'textarea', 'date', 'number', 'dropdown') NOT NULL DEFAULT 'text',
    is_required  TINYINT(1) NOT NULL DEFAULT 1,
    sort_order   INT NOT NULL DEFAULT 0,
    FOREIGN KEY (form_id) REFERENCES pds_forms(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Table: pds_field_options
-- Choices for dropdown-type fields (e.g. Civil Status, Sex)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pds_field_options (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    field_id     INT NOT NULL,
    option_text  VARCHAR(300) NOT NULL,
    sort_order   INT NOT NULL DEFAULT 0,
    FOREIGN KEY (field_id) REFERENCES pds_fields(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Table: pds_submissions
-- One row per user's completed PDS for a given form.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pds_submissions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    form_id       INT NOT NULL,
    user_id       INT NOT NULL,
    status        ENUM('submitted') NOT NULL DEFAULT 'submitted',
    submitted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES pds_forms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pds_submission (form_id, user_id)
);

-- ------------------------------------------------------------
-- Table: pds_answers
-- The actual values a user entered per field.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pds_answers (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    submission_id  INT NOT NULL,
    field_id       INT NOT NULL,
    answer_value   TEXT NULL,
    FOREIGN KEY (submission_id) REFERENCES pds_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES pds_fields(id) ON DELETE CASCADE
);
