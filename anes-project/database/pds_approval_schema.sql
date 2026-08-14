-- ============================================================
-- PDS Module - Approval workflow
-- Run this AFTER pds_file_field_schema.sql (same database: trainee_eval_system)
-- Import via phpMyAdmin > trainee_eval_system > Import
-- ============================================================

USE trainee_eval_system;

-- Every submission (first-time OR an update) now needs admin sign-off
-- before it counts as the official record.
ALTER TABLE pds_submissions
    ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    ADD COLUMN reviewed_by INT NULL,
    ADD COLUMN reviewed_at DATETIME NULL,
    ADD COLUMN admin_remarks TEXT NULL,
    ADD FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL;
