-- Extend staff roles without changing existing account rows.
ALTER TABLE users
    MODIFY COLUMN role ENUM('student','admin','secretary') NOT NULL DEFAULT 'student';
