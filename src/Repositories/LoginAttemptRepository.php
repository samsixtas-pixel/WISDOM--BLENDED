<?php
declare(strict_types=1);

namespace Wisdom\Repositories;

use Wisdom\Core\Database;

/** Tracks login attempts so brute-force guessing can be throttled. */
final class LoginAttemptRepository
{
    public function __construct(private Database $db)
    {
    }

    public function record(string $email, string $ip, bool $success): void
    {
        $this->db->execute(
            'INSERT INTO login_attempts (email, ip_address, was_successful) VALUES (?, ?, ?)',
            [$email, $ip, (int) $success]
        );
    }

    public function recentFailuresForEmail(string $email, int $minutes): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM login_attempts WHERE email = ? AND was_successful = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)",
            [$email, $minutes]
        );
    }

    public function recentFailuresForIp(string $ip, int $minutes): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND was_successful = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)",
            [$ip, $minutes]
        );
    }

        public function distinctFailuresForEmail(string $email, int $minutes): int
        {
            return (int) $this->db->fetchValue(
                'SELECT COUNT(DISTINCT ip_address) FROM login_attempts '
                . 'WHERE email = ? AND was_successful = 0 '
                . 'AND attempted_at > (NOW() - INTERVAL ? MINUTE)',
                [$email, $minutes]
            );
        }
}
