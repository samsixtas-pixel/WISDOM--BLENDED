<?php
declare(strict_types=1);

namespace Wisdom\Repositories;

use Wisdom\Core\Database;

final class PasswordResetRequestRepository
{
    public function __construct(private Database $db)
    {
    }

    public function create(int $userId, string $note): int
    {
        $existing = $this->db->fetchValue("SELECT id FROM password_reset_requests WHERE user_id = ? AND status = 'pending' LIMIT 1", [$userId]);
        if ($existing !== null) {
            return (int) $existing;
        }
        return $this->db->insert('INSERT INTO password_reset_requests (user_id, note) VALUES (?, ?)', [$userId, $note]);
    }

    public function pending(int $limit = 100): array
    {
        return $this->db->fetchAll('SELECT prr.*, u.name AS user_name, u.email AS user_email, u.avatar AS user_avatar FROM password_reset_requests prr JOIN users u ON u.id = prr.user_id WHERE prr.status = \'pending\' ORDER BY prr.created_at DESC LIMIT ?', [$limit]);
    }

    public function countPending(): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM password_reset_requests WHERE status = 'pending'");
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchRow('SELECT * FROM password_reset_requests WHERE id = ? LIMIT 1', [$id]);
    }

    public function markApproved(int $id, int $adminId): void
    {
        $this->db->execute("UPDATE password_reset_requests SET status = 'approved', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'pending'", [$adminId, $id]);
    }

    public function markRejected(int $id, int $adminId): void
    {
        $this->db->execute("UPDATE password_reset_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'pending'", [$adminId, $id]);
    }
}
