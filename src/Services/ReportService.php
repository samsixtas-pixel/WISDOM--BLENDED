<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Core\Database;
use Wisdom\Reports\AuditReport;
use Wisdom\Reports\EnrollmentReport;
use Wisdom\Reports\ExamPerformanceReport;
use Wisdom\Reports\RevenueReport;

/** Builds the available reports (each implements ReportInterface — polymorphism at work). */
final class ReportService
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,\Wisdom\Contracts\ReportInterface> keyed by report key */
    public function available(): array
    {
        $reports = [
            new EnrollmentReport($this->db),
            new RevenueReport($this->db),
            new ExamPerformanceReport($this->db),
            new AuditReport($this->db),
        ];

        $keyed = [];
        foreach ($reports as $report) {
            $keyed[$report->key()] = $report;
        }

        return $keyed;
    }

    public function get(string $key): ?\Wisdom\Contracts\ReportInterface
    {
        return $this->available()[$key] ?? null;
    }
}
