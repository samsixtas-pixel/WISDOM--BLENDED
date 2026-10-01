<?php
declare(strict_types=1);

namespace Wisdom\Repositories;

use Wisdom\Core\Database;
use Wisdom\Models\Notice;

final class NoticeRepository
{
    public function __construct(private Database $db)
    {
    }

    public function create(string $title, string $body, ?int $createdBy, ?string $expiresAt = null): int
    {
        return $this->db->insert(
            'INSERT INTO notices (title, body, created_by, expires_at) VALUES (?, ?, ?, ?)',
            [$title, $body, $createdBy, $expiresAt]
        );
    }

    /** @return list<Notice> */
    public function active(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM notices WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY created_at DESC LIMIT 20'
        );

        return array_map(static fn (array $row): Notice => Notice::fromRow($row), $rows);
    }

    public function sweepExpired(): int
    {
        return $this->db->execute('UPDATE notices SET is_active = 0 WHERE is_active = 1 AND expires_at IS NOT NULL AND expires_at <= NOW()');
    }

    /** @return list<array<string,mixed>> */
    public function all(int $limit = 100): array
    {
        return $this->db->fetchAll(
            'SELECT notices.*, users.name AS creator_name FROM notices '
            . 'LEFT JOIN users ON users.id = notices.created_by '
            . 'ORDER BY notices.created_at DESC LIMIT ?',
            [$limit]
        );
    }

    public function deactivate(int $id): void
    {
        $this->db->execute('UPDATE notices SET is_active = 0 WHERE id = ?', [$id]);
    }
}