<?php
declare(strict_types=1);

namespace Wisdom\Repositories;

use Wisdom\Core\Database;

/** Append-only trail of who did what — used by the reporting module. */
final class AuditLogRepository
{
    public function __construct(private Database $db)
    {
    }

    public function record(?int $actorId, string $action, string $details = ''): void
    {
        $this->db->execute(
            'INSERT INTO audit_logs (actor_id, action, details, ip_address) VALUES (?, ?, ?, ?)',
            [$actorId, $action, $details, \Wisdom\Core\Request::ip()]
        );
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 200): array
    {
        return $this->db->fetchAll(
            'SELECT audit_logs.*, users.name AS actor_name FROM audit_logs '
            . 'LEFT JOIN users ON users.id = audit_logs.actor_id '
            . 'ORDER BY audit_logs.created_at DESC LIMIT ?',
            [$limit]
        );
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM audit_logs');
    }
}
