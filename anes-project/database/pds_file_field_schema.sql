-- ============================================================
-- PDS Module - Add "File Attachment" field type
-- Run this AFTER class_record_schema.sql (same database: trainee_eval_system)
-- Import via phpMyAdmin > trainee_eval_system > Import
-- ============================================================

USE trainee_eval_system;

ALTER TABLE pds_fields
    MODIFY COLUMN field_type ENUM('text', 'textarea', 'date', 'number', 'dropdown', 'file') NOT NULL DEFAULT 'text';
