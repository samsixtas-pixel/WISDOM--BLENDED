<?php
declare(strict_types=1);

namespace Wisdom\Repositories;

use Wisdom\Core\Database;

final class PasswordResetRepository
{
    public function __construct(private Database $db)
    {
    }

    public function create(int $userId, string $tokenHash, string $expiresAt): int
    {
        $this->db->execute('UPDATE password_resets SET used_at = CURRENT_TIMESTAMP WHERE user_id = ? AND used_at IS NULL', [$userId]);
        return $this->db->insert('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)', [$userId, $tokenHash, $expiresAt]);
    }

    public function findValidByHash(string $hash): ?array
    {
        return $this->db->fetchRow('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1', [$hash]);
    }

    public function markUsed(int $id): void
    {
        $this->db->execute('UPDATE password_resets SET used_at = CURRENT_TIMESTAMP WHERE id = ?', [$id]);
    }

    public function countRecentForUser(int $userId, int $minutes): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > (NOW() - INTERVAL ? MINUTE)', [$userId, $minutes]);
    }
}
