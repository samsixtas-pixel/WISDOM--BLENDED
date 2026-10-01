-- ============================================================
-- Migration: terms acceptance, forced password reset,
-- support requests, password reset tokens + admin requests.
-- Safe to run on an existing database:  mysql -u root wisdom_db < this_file
-- ============================================================

ALTER TABLE users
    ADD COLUMN terms_accepted_at    TIMESTAMP NULL DEFAULT NULL AFTER is_active,
    ADD COLUMN force_password_reset TINYINT(1) NOT NULL DEFAULT 0 AFTER terms_accepted_at;

-- Allow staff accounts to have no programme (they are not learners).
ALTER TABLE users MODIFY COLUMN level VARCHAR(20) NULL;

-- Remove the placeholder programme from any staff rows created earlier.
UPDATE users SET level = NULL WHERE role IN ('admin','secretary');

CREATE TABLE IF NOT EXISTS support_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(30) NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new','read','closed') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_support_status (status, created_at),
    CONSTRAINT fk_support_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pr_token (token_hash),
    KEY idx_pr_user (user_id, used_at),
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    note VARCHAR(500) NOT NULL DEFAULT '',
    status ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_prr_status (status, created_at),
    CONSTRAINT fk_prr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_prr_admin FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;
