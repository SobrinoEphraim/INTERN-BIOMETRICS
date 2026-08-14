-- ============================================================
-- Biometrics / DTR Module
-- Run this AFTER pds_approval_schema.sql (same database: trainee_eval_system)
-- Import via phpMyAdmin > trainee_eval_system > Import
-- ============================================================

USE trainee_eval_system;

-- ------------------------------------------------------------
-- Table: dtr_logs
-- One row per Time In / Time Out tap at the biometrics kiosk.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dtr_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    log_type     ENUM('time_in', 'time_out') NOT NULL,
    log_time     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    photo_path   VARCHAR(255) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
