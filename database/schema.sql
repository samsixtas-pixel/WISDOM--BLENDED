-- WISDOM database schema.
-- Import with: mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS wisdom_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wisdom_db;

-- Accounts. `role` distinguishes student vs admin (maps to the Student/Admin
-- classes in src/Models); `subjects` stores the student's chosen subjects.
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255)    NOT NULL,
    email         VARCHAR(255)    NOT NULL,
    avatar        VARCHAR(255)    NULL,
    sex           VARCHAR(30)     NOT NULL,
    password      VARCHAR(255)    NOT NULL,
    level         VARCHAR(20)     NOT NULL,
    subjects      JSON            NOT NULL,
    role          ENUM('student','admin','secretary') NOT NULL DEFAULT 'student',
    is_approved   TINYINT(1)      NOT NULL DEFAULT 0,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    terms_accepted_at TIMESTAMP   NULL DEFAULT NULL,
    results_ack_at TIMESTAMP NULL DEFAULT NULL,
    force_password_reset TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_name (name),
    KEY idx_users_level (level),
    KEY idx_users_role (role),
    KEY idx_users_status_created (is_active, is_approved, created_at),
    KEY idx_users_role_level (role, level),
    FULLTEXT KEY ft_users_search (name, email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  CHAR(64) NOT NULL,
    expires_at  TIMESTAMP NOT NULL,
    used_at     TIMESTAMP NULL DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pr_token (token_hash),
    KEY idx_pr_user (user_id, used_at),
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_requests (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    note         VARCHAR(500) NOT NULL DEFAULT '',
    status       ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
    reviewed_by  INT UNSIGNED NULL,
    reviewed_at  TIMESTAMP NULL DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_prr_status (status, created_at),
    CONSTRAINT fk_prr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_prr_admin FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Subject catalogue per programme, used to validate registration/fee choices
-- instead of hard-coding the list inside PHP.
CREATE TABLE IF NOT EXISTS subjects (
    id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(20)  NOT NULL,
    name  VARCHAR(255) NOT NULL,
    UNIQUE KEY uq_subjects_level_name (level, name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference     VARCHAR(32)    NULL,
    user_id       INT UNSIGNED NOT NULL,
    proof_path    VARCHAR(255) NOT NULL,
    proof_deleted_at TIMESTAMP NULL DEFAULT NULL,
    proof_hash    CHAR(64)      NULL,
    amount        INT UNSIGNED NOT NULL,
    amount_declared INT UNSIGNED NULL,
    payment_type  ENUM('half','full') NOT NULL,
    category      ENUM('programme','examination') NOT NULL DEFAULT 'programme',
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_remarks VARCHAR(500) NULL,
    receipt_number VARCHAR(32) NULL,
    submitted_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at   TIMESTAMP    NULL DEFAULT NULL,
    KEY idx_payments_status (status),
    KEY idx_payments_user (user_id),
    UNIQUE KEY uq_payments_reference (reference),
    KEY idx_payments_proof_hash (proof_hash),
    KEY idx_payments_cleanup (status, reviewed_at, proof_deleted_at),
    KEY idx_payments_status_submitted (status, submitted_at),
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exams (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    subject_code  VARCHAR(30)  NOT NULL,
    exam_number   VARCHAR(50)  NOT NULL,
    subject_name  VARCHAR(255) NOT NULL,
    weight        DECIMAL(6,2) NOT NULL DEFAULT 100.00,
    result        DECIMAL(6,2) NULL,
    grade         VARCHAR(5)   NULL,
    status        ENUM('scheduled','completed','published') NOT NULL DEFAULT 'scheduled',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_exams_user (user_id),
    KEY idx_exams_user_status (user_id, status),
    KEY idx_exams_subject_name (subject_name),
    CONSTRAINT fk_exams_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Brute-force throttling for the login form (per email and per IP).
CREATE TABLE IF NOT EXISTS login_attempts (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email          VARCHAR(255) NOT NULL,
    ip_address     VARCHAR(45)  NOT NULL,
    was_successful TINYINT(1)   NOT NULL,
    attempted_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_email_time (email, attempted_at),
    KEY idx_attempts_ip_time (ip_address, attempted_at),
    KEY idx_attempts_email_success (email, was_successful, attempted_at)
) ENGINE=InnoDB;

-- Append-only activity trail, read by the Audit report.
CREATE TABLE IF NOT EXISTS audit_logs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id   INT UNSIGNED NULL,
    action     VARCHAR(100) NOT NULL,
    details    VARCHAR(255) NOT NULL DEFAULT '',
    ip_address VARCHAR(45)  NOT NULL DEFAULT '',
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_created (created_at),
    KEY idx_audit_action_created (action, created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Live classes for Wisdom's online learning 
CREATE TABLE IF NOT EXISTS live_classes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level         VARCHAR(20)  NOT NULL,
    subject_name  VARCHAR(255) NOT NULL,
    link          VARCHAR(500) NOT NULL,
    scheduled_at  DATETIME     NOT NULL,
    created_by    INT UNSIGNED NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_live_classes_subject (subject_name),
    KEY idx_live_classes_level (level),
    CONSTRAINT fk_live_classes_creator
        FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(64)  NOT NULL PRIMARY KEY,
    setting_value TEXT         NOT NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('payment.bank_number', ''),
    ('payment.lipa_number', '');

CREATE TABLE IF NOT EXISTS notices (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(150) NOT NULL,
    body        TEXT         NOT NULL,
    created_by  INT UNSIGNED NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    expires_at  TIMESTAMP NULL DEFAULT NULL,
    KEY idx_notices_active (is_active, created_at),
    KEY idx_notices_active_expires (is_active, expires_at),
    CONSTRAINT fk_notices_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_import_batches (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id          INT UNSIGNED NULL,
    original_filename VARCHAR(255) NOT NULL,
    total_rows        INT UNSIGNED NOT NULL DEFAULT 0,
    matched_rows      INT UNSIGNED NOT NULL DEFAULT 0,
    pending_rows      INT UNSIGNED NOT NULL DEFAULT 0,
    rejected_rows     INT UNSIGNED NOT NULL DEFAULT 0,
    rejected_rows     INT UNSIGNED NOT NULL DEFAULT 0,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_import_admin FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_pending_reviews (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id    INT UNSIGNED NULL,
    row_number  INT UNSIGNED NOT NULL,
    raw_data    JSON         NOT NULL,
    reason      VARCHAR(255) NOT NULL,
    suggested_user_id INT UNSIGNED NULL,
    suggested_user_id INT UNSIGNED NULL,
    status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at TIMESTAMP    NULL DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pending_status (status, created_at),
    CONSTRAINT fk_pending_batch FOREIGN KEY (batch_id) REFERENCES exam_import_batches (id) ON DELETE SET NULL,
    CONSTRAINT fk_pending_admin FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_pending_suggested_user FOREIGN KEY (suggested_user_id) REFERENCES users (id) ON DELETE SET NULL
    CONSTRAINT fk_pending_suggested_user FOREIGN KEY (suggested_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO subjects (level, name) VALUES
    ('CPSP I', 'Fleet and Logistics Management'),
    ('CPSP I', 'Consultancy and Entrepreneurship'),
    ('CPSP I', 'Strategic Supply Chain Management'),
    ('CPSP I', 'Project Management'),
    ('CPSP I', 'Business Negotiation'),
    ('CPSP I', 'Procurement and Contract Management'),
    ('CPSP II', 'Management Supply Chain Risks'),
    ('CPSP II', 'Global Strategic Procurement'),
    ('CPSP II', 'Strategic Assets Management'),
    ('CPSP II', 'Leadership and Governance'),
    ('CPSP II', 'Procurement and Supply Chain Audit'),
    ('PD I', 'Information and Communication Technology'),
    ('PD I', 'Business Communication and Report Writing'),
    ('PD I', 'Warehouse Operations'),
    ('PD I', 'Business Mathematics and Statistics'),
    ('PD I', 'Procurement Principles'),
    ('PD II', 'Inventory Control'),
    ('PD II', 'Entrepreneurship and Communication Knowledge'),
    ('PD II', 'Principles of Asset Management'),
    ('PD II', 'Fundamentals of Procurement and Contract Management'),
    ('PD II', 'Tendering Process and Techniques'),
    ('PD II', 'Principles of Office Records Management');
