<?php
declare(strict_types=1);

namespace Wisdom\Reports;

/** Student headcount and approval status, grouped by programme. */
final class EnrollmentReport extends AbstractReport
{
    public function key(): string
    {
        return 'enrollment';
    }

    public function title(): string
    {
        return 'Enrollment by programme';
    }

    public function headers(): array
    {
        return ['Programme', 'Total students', 'Approved', 'Pending'];
    }

    public function rows(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT level, COUNT(*) AS total, SUM(is_approved) AS approved "
            . "FROM users WHERE role = 'student' GROUP BY level ORDER BY level"
        );

        return array_map(
            static fn (array $row): array => [
                (string) $row['level'],
                (int) $row['total'],
                (int) $row['approved'],
                (int) $row['total'] - (int) $row['approved'],
            ],
            $rows
        );
    }
}
