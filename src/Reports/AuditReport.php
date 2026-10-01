<?php
declare(strict_types=1);

namespace Wisdom\Reports;

/** Most recent administrative and authentication actions. */
final class AuditReport extends AbstractReport
{
    public function key(): string
    {
        return 'audit';
    }

    public function title(): string
    {
        return 'Recent activity';
    }

    public function headers(): array
    {
        return ['When', 'Actor', 'Action', 'Details'];
    }

    public function rows(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT audit_logs.created_at, users.name AS actor_name, audit_logs.action, audit_logs.details '
            . 'FROM audit_logs LEFT JOIN users ON users.id = audit_logs.actor_id '
            . 'ORDER BY audit_logs.created_at DESC LIMIT 100'
        );

        return array_map(
            static fn (array $row): array => [
                (string) $row['created_at'],
                (string) ($row['actor_name'] ?? 'System'),
                (string) $row['action'],
                (string) ($row['details'] ?? ''),
            ],
            $rows
        );
    }
}
