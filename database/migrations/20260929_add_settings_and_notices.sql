-- Add the tables consumed by SettingService and NoticeService on existing
-- WISDOM installations. This migration is additive and safe to re-run.
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
    KEY idx_notices_active (is_active, created_at),
    CONSTRAINT fk_notices_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;