<?php
declare(strict_types=1);

namespace Wisdom\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Wisdom\Core\AppException;
use ZipArchive;

/** Bounded first-sheet XLSX reader using ext-zip and ext-dom. */
final class XlsxReader
{
    /** @var list<string> */
    private array $sharedStrings = [];
    private string $sheetXml = '';

    public function __construct(private string $path)
    {
    }

    public function load(?string $sheetName = null): self
    {
        if (!class_exists(ZipArchive::class) || !class_exists(DOMDocument::class)) {
            throw new AppException('XLSX import requires the ZIP and DOM PHP extensions.');
        }
        if (!is_file($this->path) || !is_readable($this->path) || filesize($this->path) > 8 * 1024 * 1024) {
            throw new AppException('The workbook is missing, unreadable, or larger than 8 MB.');
        }

        $zip = new ZipArchive();
        if ($zip->open($this->path) !== true) {
            throw new AppException('The file is not a valid XLSX workbook.');
        }
        try {
            if ($zip->numFiles > 2000) {
                throw new AppException('The workbook contains too many archive entries.');
            }
            $sharedRaw = $zip->getFromName('xl/sharedStrings.xml');
            $this->sharedStrings = $sharedRaw === false ? [] : $this->parseSharedStrings($sharedRaw);
            $sheetPath = $sheetName === null ? 'xl/worksheets/sheet1.xml' : ($this->resolveSheetPath($zip, $sheetName) ?? 'xl/worksheets/sheet1.xml');
            if (!str_starts_with($sheetPath, 'xl/worksheets/') || str_contains($sheetPath, '..')) {
                throw new AppException('The selected workbook sheet has an invalid path.');
            }
            $this->sheetXml = (string) ($zip->getFromName($sheetPath) ?: '');
        } finally {
            $zip->close();
        }
        if ($this->sheetXml === '' || strlen($this->sheetXml) > 16 * 1024 * 1024) {
            throw new AppException('The workbook has no readable data sheet or exceeds the sheet size limit.');
        }
        return $this;
    }

    /** @return list<list<string>> */
    public function rows(): array
    {
        $doc = new DOMDocument();
        if (!@$doc->loadXML($this->sheetXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new AppException('The workbook contains unreadable sheet data.');
        }
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $nodes = $xpath->query('//m:sheetData/m:row');
        if ($nodes === false || $nodes->length > 5001) {
            throw new AppException('The workbook can contain at most 5,000 data rows.');
        }
        $rows = [];
        foreach ($nodes as $rowNode) {
            $line = [];
            foreach ($xpath->query('m:c', $rowNode) ?: [] as $cell) {
                if (!$cell instanceof DOMElement) continue;
                $column = $this->columnIndex($cell->getAttribute('r'));
                if ($column < 0 || $column > 99) continue;
                $line[$column] = $this->cellValue($xpath, $cell, $cell->getAttribute('t'));
            }
            if ($line === []) continue;
            $max = max(array_keys($line));
            for ($i = 0; $i <= $max; $i++) $line[$i] ??= '';
            ksort($line);
            $rows[] = array_values($line);
        }
        return $rows;
    }

    /** @return list<array<string,string>> */
    public function rowsWithHeader(): array
    {
        $rows = $this->rows();
        if (count($rows) < 2) return [];
        $headers = array_map(fn (string $header): string => $this->normaliseHeader($header), $rows[0]);
        $out = [];
        foreach (array_slice($rows, 1) as $row) {
            $assoc = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') $assoc[$header] = trim(substr((string) ($row[$index] ?? ''), 0, 1000));
            }
            if (implode('', $assoc) !== '') $out[] = $assoc;
        }
        return $out;
    }

    private function normaliseHeader(string $header): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($header))) ?? '', '_');
    }

    private function columnIndex(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)/i', $reference, $match)) return -1;
        $number = 0;
        foreach (str_split(strtoupper($match[1])) as $letter) $number = $number * 26 + ord($letter) - 64;
        return $number - 1;
    }

    private function cellValue(DOMXPath $xpath, DOMElement $cell, string $type): string
    {
        if ($type === 's') {
            $value = $xpath->query('m:v', $cell)?->item(0)?->textContent;
            return $this->sharedStrings[(int) $value] ?? '';
        }
        if ($type === 'inlineStr') return (string) ($xpath->query('m:is//m:t', $cell)?->item(0)?->textContent ?? '');
        return (string) ($xpath->query('m:v', $cell)?->item(0)?->textContent ?? '');
    }

    /** @return list<string> */
    private function parseSharedStrings(string $xml): array
    {
        if (strlen($xml) > 16 * 1024 * 1024) throw new AppException('Workbook shared strings exceed the size limit.');
        $doc = new DOMDocument();
        if (!@$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) return [];
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];
        foreach ($xpath->query('//m:si') ?: [] as $item) {
            $text = '';
            foreach ($xpath->query('.//m:t', $item) ?: [] as $part) $text .= $part->textContent;
            $strings[] = substr($text, 0, 1000);
            if (count($strings) > 100000) throw new AppException('Workbook has too many shared strings.');
        }
        return $strings;
    }

    private function resolveSheetPath(ZipArchive $zip, string $sheetName): ?string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relations = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $relations === false) return null;
        $doc = new DOMDocument();
        if (!@$doc->loadXML($workbook, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) return null;
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relationId = null;
        foreach ($xpath->query('//m:sheet') ?: [] as $sheet) {
            if (!$sheet instanceof DOMElement) continue;
            if (strcasecmp(trim($sheet->getAttribute('name')), trim($sheetName)) === 0) {
                $relationId = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                break;
            }
        }
        if ($relationId === null) return null;
        $doc = new DOMDocument();
        if (!@$doc->loadXML($relations, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) return null;
        foreach ($doc->getElementsByTagName('Relationship') as $relation) {
            if ($relation->getAttribute('Id') === $relationId) {
                $target = ltrim($relation->getAttribute('Target'), '/');
                return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }
        return null;
    }
}
