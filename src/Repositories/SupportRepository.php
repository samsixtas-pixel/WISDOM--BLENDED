<?php
declare(strict_types=1);

namespace Wisdom\Repositories;

use Wisdom\Contracts\RepositoryInterface;
use Wisdom\Core\Database;

/** Messages sent through the public contact form. */
final class SupportRepository implements RepositoryInterface
{
    public function __construct(private Database $db)
    {
    }

    public function create(string $name, string $email, string $subject, string $message, string $ip): int
    {
        return $this->db->insert(
            'INSERT INTO support_messages (name, email, subject, message, ip_address) VALUES (?, ?, ?, ?, ?)',
            [$name, $email, $subject, $message, $ip]
        );
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 100): array
    {
        return $this->db->fetchAll(
            'SELECT id, name, email, subject, message, ip_address, created_at FROM support_messages ORDER BY created_at DESC LIMIT ?',
            [$limit]
        );
    }

    public function find(int $id): ?object
    {
        $row = $this->db->fetchRow('SELECT * FROM support_messages WHERE id = ? LIMIT 1', [$id]);

        return $row === null ? null : (object) $row;
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM support_messages WHERE id = ?', [$id]) > 0;
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM support_messages');
    }
}
