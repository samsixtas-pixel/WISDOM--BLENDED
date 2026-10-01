<?php
declare(strict_types=1);

namespace Wisdom\Reports;

/** Approved payment revenue grouped by fee category. */
final class RevenueReport extends AbstractReport
{
    public function key(): string
    {
        return 'revenue';
    }

    public function title(): string
    {
        return 'Approved fee revenue';
    }

    public function headers(): array
    {
        return ['Category', 'Payments', 'Total collected'];
    }

    public function rows(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT category, COUNT(*) AS payments, COALESCE(SUM(amount), 0) AS total "
            . "FROM payments WHERE status = 'approved' GROUP BY category ORDER BY category"
        );

        return array_map(
            static fn (array $row): array => [ucfirst((string) $row['category']), (int) $row['payments'], (int) $row['total']],
            $rows
        );
    }
}
