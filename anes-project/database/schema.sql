-- ============================================================
-- Trainee Evaluation System - Database Schema
-- NKTI Department of Anesthesiology
-- Import this via phpMyAdmin (XAMPP) or: mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS trainee_eval_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE trainee_eval_system;

-- ------------------------------------------------------------
-- Table: users
-- Stores everyone who can log in: admins, trainees, consultants
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(150)        NOT NULL,
    email           VARCHAR(150)        NOT NULL UNIQUE,
    password_hash   VARCHAR(255)        NOT NULL,
    role            ENUM('admin', 'trainee', 'consultant', 'rater') NOT NULL DEFAULT 'trainee',
    status          ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    must_reset_password TINYINT(1)      NOT NULL DEFAULT 1, -- forces password change on first login
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at   DATETIME            NULL
);

-- ------------------------------------------------------------
-- Default admin account
-- Email: admin@nkti.gov.ph
-- Default password/access code: NktiAnes2026
-- (hash below corresponds to "NktiAnes2026" using PHP password_hash/BCRYPT)
-- IMPORTANT: log in once and change this password immediately.
-- ------------------------------------------------------------
INSERT INTO users (full_name, email, password_hash, role, status, must_reset_password)
VALUES (
    'System Administrator',
    'admin@nkti.gov.ph',
    '$2y$10$92IXUNpkjO0rOQ5byMi.YeFakeHashPlaceholderReplaceMe123',
    'admin',
    'active',
    1
);

-- NOTE: The hash above is a placeholder. Run generate_admin_hash.php once
-- (included in this project) to generate the REAL hash for "NktiAnes2026",
-- then UPDATE the row above with the generated value. Instructions are
-- in README.txt.
