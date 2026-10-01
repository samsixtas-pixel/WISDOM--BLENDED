<?php
declare(strict_types=1);

namespace Wisdom\Contracts;

/** Something that can present itself as a titled table (and export it). */
interface ReportInterface
{
    public function key(): string;

    public function title(): string;

    /** @return list<string> */
    public function headers(): array;

    /** @return list<list<string|int|float|null>> */
    public function rows(): array;

    public function toCsv(): string;
}
