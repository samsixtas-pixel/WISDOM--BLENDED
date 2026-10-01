<?php
declare(strict_types=1);

namespace Wisdom\Reports;

/** Average result and pass rate per subject, for published exams. */
final class ExamPerformanceReport extends AbstractReport
{
    private const PASS_MARK = 50.0;

    public function key(): string
    {
        return 'exam_performance';
    }

    public function title(): string
    {
        return 'Exam performance by subject';
    }

    public function headers(): array
    {
        return ['Subject', 'Entries', 'Average result', 'Pass rate'];
    }

    public function rows(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT subject_name, COUNT(*) AS entries, AVG(result) AS average_result, "
            . "SUM(result >= ?) AS passed FROM exams "
            . "WHERE status = 'published' AND result IS NOT NULL GROUP BY subject_name ORDER BY subject_name",
            [self::PASS_MARK]
        );

        return array_map(
            static function (array $row): array {
                $entries = (int) $row['entries'];
                $passRate = $entries > 0 ? round(((int) $row['passed'] / $entries) * 100, 1) : 0.0;

                return [(string) $row['subject_name'], $entries, round((float) $row['average_result'], 1), $passRate . '%'];
            },
            $rows
        );
    }
}
