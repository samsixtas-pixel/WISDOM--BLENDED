<?php
declare(strict_types=1);

/**
 * CLI helper to create (or promote) an administrator account.
 * Usage: php bin/create_admin.php "Jane Doe" jane@example.com "StrongPass1" [admin|secretary]
 */

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Database;

if (PHP_SAPI !== 'cli') {
    exit('This script may only be run from the command line.');
}

[, $name, $email, $password] = $argv + [null, null, null, null];
$role = $argv[4] ?? 'admin';

if (!$name || !$email || !$password) {
    fwrite(STDERR, "Usage: php bin/create_admin.php \"Full Name\" email@example.com Password123 [admin|secretary]\n");
    exit(1);
}
if (!in_array($role, ['admin', 'secretary'], true)) {
    fwrite(STDERR, "Role must be 'admin' or 'secretary'.\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "That is not a valid email address.\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$db = App::get(Database::class);
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$existing = $db->fetchValue('SELECT id FROM users WHERE email = ?', [$email]);

if ($existing !== null) {
    $db->execute(
        'UPDATE users SET role = ?, is_approved = 1, is_active = 1, password = ? WHERE id = ?',
        [$role, $hash, (int) $existing]
    );
    printf("Existing account #%d (%s) promoted to %s.\n", (int) $existing, $email, $role);
} else {
    $id = $db->insert(
        "INSERT INTO users (name, email, sex, password, level, subjects, role, is_approved, is_active) "
        . "VALUES (?, ?, 'prefer not to say', ?, 'CPSP I', '[]', ?, 1, 1)",
        [$name, $email, $hash, $role]
    );
    printf("%s #%d (%s) created.\n", ucfirst($role), $id, $email);
}
