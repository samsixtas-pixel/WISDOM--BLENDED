<?php
declare(strict_types=1);

namespace Wisdom\Reports;

use Wisdom\Contracts\ReportInterface;
use Wisdom\Core\Database;

/**
 * Shared plumbing for every report: only toCsv() is implemented here,
 * everything else is left abstract so each concrete report defines its
 * own title/headers/rows (abstraction + polymorphism).
 */
abstract class AbstractReport implements ReportInterface
{
    public function __construct(protected Database $db)
    {
    }

    public function toCsv(): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $this->headers());
        foreach ($this->rows() as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }
}
