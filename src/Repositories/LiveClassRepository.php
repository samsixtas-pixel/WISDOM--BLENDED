<?php
declare(strict_types=1);

namespace Wisdom\Repositories;

use Wisdom\Core\Database;
use Wisdom\Models\LiveClass;

final class LiveClassRepository
{
    public function __construct(private Database $db)
    {
    }

    public function create(string $level, string $subjectName, string $link, string $scheduledAt, ?int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO live_classes (level, subject_name, link, scheduled_at, created_by) VALUES (?, ?, ?, ?, ?)',
            [$level, $subjectName, $link, $scheduledAt, $createdBy]
        );
    }

    /** @return list<array<string,mixed>> Admin table view. */
    public function all(int $limit = 200): array
    {
        return $this->db->fetchAll(
            'SELECT live_classes.*, users.name AS creator_name FROM live_classes '
            . 'LEFT JOIN users ON users.id = live_classes.created_by '
            . 'ORDER BY live_classes.scheduled_at DESC LIMIT ?',
            [$limit]
        );
    }

    /**
     * Student view: every live class whose subject name matches one of the
     * student's enrolled subjects (regardless of the class's own level —
     * this is what allows a CPSP II student to see a CPSP I class).
     *
     * @param list<string> $subjectNames
     * @return list<LiveClass>
     */
    public function forSubjects(array $subjectNames): array
    {
        $subjectNames = array_values(array_filter($subjectNames, 'is_string'));
        if ($subjectNames === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($subjectNames), '?'));
        $rows = $this->db->fetchAll(
            'SELECT * FROM live_classes WHERE subject_name IN (' . $placeholders . ') '
            . 'ORDER BY scheduled_at DESC',
            $subjectNames
        );

        return array_map(static fn (array $row): LiveClass => LiveClass::fromRow($row), $rows);
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM live_classes WHERE id = ?', [$id]) > 0;
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM live_classes');
    }
}