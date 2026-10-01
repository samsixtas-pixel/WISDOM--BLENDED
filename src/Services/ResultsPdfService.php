<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Core\App;
use Wisdom\Core\AppException;
use Wisdom\Models\Exam;
use Wisdom\Models\User;
use Wisdom\Repositories\ExamRepository;

/** Print-ready WISDOM results document, built with the bundled FPDF library. */
final class ResultsPdfService
{
    private const NAVY = [13, 43, 69];
    private const TEAL = [45, 106, 122];
    private const GOLD = [201, 162, 39];
    private const CREAM = [253, 251, 246];
    private const INK = [14, 21, 32];
    private const MUTED = [90, 106, 125];
    private const LINE = [227, 224, 214];

    public function __construct(private ExamRepository $exams)
    {
    }

    public function stream(User $user, bool $download = true): never
    {
        $library = WISDOM_ROOT . '/vendor/fpdf/fpdf.php';
        if (!class_exists('FPDF') && is_file($library)) require_once $library;
        if (!class_exists('FPDF')) throw new AppException('PDF library is not installed. Please contact an administrator.');

        $rows = array_values(array_filter($this->exams->forUser($user->getId()), static fn (Exam $exam): bool => $exam->isPublished()));
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetTitle('WISDOM Examination Results - ' . $user->getName());
        $pdf->SetAuthor('WISDOM Blended Classes');
        $pdf->SetMargins(18, 14, 18);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->AddPage();
        $this->letterhead($pdf);
        $this->studentCard($pdf, $user);
        $this->tableHeading($pdf);
        $this->resultsTable($pdf, $rows);
        $this->summary($pdf, $rows);
        $this->attestation($pdf);
        $this->footer($pdf, $user, $rows);

        $filename = 'WISDOM-results-' . (preg_replace('/[^A-Za-z0-9]+/', '-', $user->getName()) ?: 'student') . '-' . date('Ymd') . '.pdf';
        $pdf->Output($download ? 'D' : 'I', $filename);
        exit;
    }

    private function letterhead(\FPDF $pdf): void
    {
        $y = 12;
        $logo = (string) App::config('pdf.logo_path', '');
        $width = min(32.0, max(18.0, (float) App::config('pdf.logo_width_mm', 30)));
        $drawn = false;
        if ($logo !== '' && is_file($logo) && is_readable($logo) && @getimagesize($logo) !== false) {
            $info = getimagesize($logo);
            $height = $width * ((int) $info[1] / max(1, (int) $info[0]));
            $pdf->Image($logo, (210 - $width) / 2, $y, $width, $height);
            $y += $height + 2;
            $drawn = true;
        }
        if (!$drawn) {
            $pdf->SetFillColor(...self::NAVY);
            $pdf->SetDrawColor(...self::GOLD);
            $pdf->SetLineWidth(0.6);
            $pdf->SetXY(95, $y);
            $pdf->Cell(20, 20, '', 1, 0, 'C', true);
            $pdf->SetXY(95, $y + 3);
            $pdf->SetTextColor(...self::GOLD);
            $pdf->SetFont('Times', 'B', 18);
            $pdf->Cell(20, 13, 'W', 0, 0, 'C');
            $y += 23;
        }
        $pdf->SetY($y + 1);
        $pdf->SetTextColor(...self::NAVY);
        $pdf->SetFont('Times', 'B', 27);
        $pdf->Cell(0, 10, 'WISDOM', 0, 1, 'C');
        $pdf->SetTextColor(...self::TEAL);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(0, 5, 'B L E N D E D   C L A S S E S', 0, 1, 'C');
        $lineY = $pdf->GetY() + 3;
        $pdf->SetDrawColor(...self::GOLD);
        $pdf->SetLineWidth(0.55);
        $pdf->Line(55, $lineY, 96, $lineY);
        $pdf->Line(114, $lineY, 155, $lineY);
        $pdf->SetFillColor(...self::GOLD);
        $pdf->SetXY(103, $lineY - 1.2);
        $pdf->Cell(4, 4, '', 0, 0, 'C', true);
        $pdf->SetY($lineY + 4);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->Cell(0, 4, 'L E A R N   ·   T H I N K   ·   G R O W', 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFillColor(...self::NAVY);
        $pdf->Cell(174, 1, '', 0, 1, 'L', true);
        $pdf->Ln(6);
    }

    private function studentCard(\FPDF $pdf, User $user): void
    {
        $pdf->SetTextColor(...self::NAVY);
        $pdf->SetFont('Times', 'B', 17);
        $pdf->Cell(0, 9, 'Examination Results', 0, 1);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell(0, 5, 'Issued ' . date('j F Y') . ' at ' . date('H:i') . ' EAT', 0, 1);
        $pdf->Ln(3);

        $top = $pdf->GetY();
        $pdf->SetFillColor(247, 242, 230);
        $pdf->Rect(18, $top, 174, 32, 'F');
        $pdf->SetFillColor(...self::GOLD);
        $pdf->Rect(18, $top, 1.3, 32, 'F');
        $pdf->SetXY(24, $top + 3);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetFont('Helvetica', 'B', 7);
        $pdf->Cell(70, 4, 'S T U D E N T   D E T A I L S', 0, 1);
        $this->labelValue($pdf, 24, $top + 9, 'Name', $user->getName(), 80);
        $this->labelValue($pdf, 110, $top + 9, 'Programme', (string) $user->getLevel(), 76);
        $this->labelValue($pdf, 24, $top + 16, 'Email', $user->getEmail(), 80);
        $this->labelValue($pdf, 110, $top + 16, 'Sex', ucfirst((string) $user->getSex()), 76);
        $this->labelValue($pdf, 24, $top + 23, 'Student ID', 'WDB-' . str_pad((string) $user->getId(), 5, '0', STR_PAD_LEFT), 80);
        $this->labelValue($pdf, 110, $top + 23, 'Subjects', (string) count($user->getSubjects()), 76);
        $pdf->SetY($top + 38);
    }

    private function labelValue(\FPDF $pdf, float $x, float $y, string $label, string $value, float $width): void
    {
        $pdf->SetXY($x, $y);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetFont('Helvetica', 'B', 7);
        $pdf->Cell(22, 5, strtoupper($label), 0, 0);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(...self::INK);
        $pdf->Cell($width - 22, 5, $this->fit($pdf, $this->safe($value), $width - 22), 0, 0);
    }

    private function tableHeading(\FPDF $pdf): void
    {
        $pdf->SetTextColor(...self::NAVY);
        $pdf->SetFont('Times', 'B', 13);
        $pdf->Cell(0, 8, 'Results', 0, 1);
        $pdf->SetDrawColor(...self::LINE);
        $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
        $pdf->Ln(2);
        $this->tableHeader($pdf);
    }

    private function tableHeader(\FPDF $pdf): void
    {
        $pdf->SetFillColor(...self::NAVY);
        $pdf->SetTextColor(...self::CREAM);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetX(18);
        foreach ([['#', 9, 'C'], ['Code', 23, 'L'], ['Exam no.', 33, 'L'], ['Subject', 59, 'L'], ['Weight', 14, 'R'], ['Result', 18, 'R'], ['Grade', 18, 'C']] as [$label, $width, $align]) {
            $pdf->Cell($width, 8, strtoupper($label), 0, 0, $align, true);
        }
        $pdf->Ln();
    }

    /** @param list<Exam> $rows */
    private function resultsTable(\FPDF $pdf, array $rows): void
    {
        if ($rows === []) {
            $pdf->SetFont('Helvetica', 'I', 9);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->Cell(0, 10, 'No published results are available yet.', 0, 1);
            return;
        }
        $widths = [9, 23, 33, 59, 14, 18, 18];
        $lineHeight = 5.5;
        $zebra = false;
        foreach ($rows as $index => $exam) {
            $row = $exam->toArray();
            $subject = $this->safe((string) $row['subject_name']);
            $this->setBodyFont($pdf);
            $lines = $this->wrappedLineCount($pdf, $subject, $widths[3] - 2);
            $height = max(1, $lines) * $lineHeight;
            if ($pdf->GetY() + $height > 268) {
                $pdf->AddPage();
                $this->tableHeader($pdf);
                $this->setBodyFont($pdf);
            }
            $y = $pdf->GetY();
            $x = 18;
            $pdf->SetFillColor(...($zebra ? [250, 248, 242] : self::CREAM));
            $pdf->Rect($x, $y, array_sum($widths), $height, 'F');
            $pdf->SetDrawColor(...self::LINE);
            $pdf->Line($x, $y + $height, $x + array_sum($widths), $y + $height);
            $values = [
                (string) ($index + 1),
                $this->fit($pdf, $this->safe((string) $row['subject_code']), $widths[1] - 2),
                $this->fit($pdf, $this->safe((string) $row['exam_number']), $widths[2] - 2),
                null,
                number_format((float) $row['weight'], 0),
                $row['result'] === null ? '-' : number_format((float) $row['result'], 1),
                $this->safe((string) ($row['grade'] ?? '-')),
            ];
            $aligns = ['C', 'L', 'L', 'L', 'R', 'R', 'C'];
            $cx = $x;
            for ($column = 0; $column < count($widths); $column++) {
                if ($column === 3) { $cx += $widths[$column]; continue; }
                $pdf->SetXY($cx + 1, $y);
                $pdf->SetTextColor(...self::INK);
                $pdf->Cell($widths[$column] - 2, $height, (string) $values[$column], 0, 0, $aligns[$column]);
                $cx += $widths[$column];
            }
            $subjectX = $x + $widths[0] + $widths[1] + $widths[2] + 1;
            $pdf->SetXY($subjectX, $y);
            $pdf->MultiCell($widths[3] - 2, $lineHeight, $subject, 0, 'L');
            // Explicitly advance all columns to the measured full row height; avoids overlap after wrapped subjects.
            $pdf->SetXY(18, $y + $height);
            $zebra = !$zebra;
        }
        $pdf->Ln(5);
    }

    /** @param list<Exam> $rows */
    private function summary(\FPDF $pdf, array $rows): void
    {
        if ($rows === []) return;
        if ($pdf->GetY() > 235) $pdf->AddPage();
        $sum = 0.0; $graded = 0; $passed = 0;
        foreach ($rows as $exam) {
            $result = $exam->toArray()['result'];
            if ($result === null) continue;
            $graded++; $sum += (float) $result; if ((float) $result >= 50) $passed++;
        }
        $average = $graded ? $sum / $graded : 0.0;
        $passRate = $graded ? 100 * $passed / $graded : 0.0;
        $pdf->SetTextColor(...self::NAVY); $pdf->SetFont('Times', 'B', 13); $pdf->Cell(0, 8, 'Summary', 0, 1);
        $pdf->Ln(2);
        $boxes = [['Subjects examined', (string) count($rows)], ['Average result', number_format($average, 1) . ' / 100'], ['Pass rate', number_format($passRate, 1) . '%']];
        $boxY = $pdf->GetY();
        foreach ($boxes as $i => [$label, $value]) {
            $x = 18 + $i * 59;
            $pdf->SetFillColor(247, 242, 230); $pdf->SetDrawColor(...self::LINE); $pdf->Rect($x, $boxY, 55, 24, 'DF');
            $pdf->SetFillColor(...self::GOLD); $pdf->Rect($x, $boxY, 55, 1, 'F');
            $pdf->SetXY($x + 3, $boxY + 4); $pdf->SetTextColor(...self::MUTED); $pdf->SetFont('Helvetica', 'B', 6.5); $pdf->Cell(49, 4, strtoupper($label), 0, 0);
            $pdf->SetXY($x + 3, $boxY + 10); $pdf->SetTextColor(...self::NAVY); $pdf->SetFont('Times', 'B', 17); $pdf->Cell(49, 9, $value, 0, 0);
        }
        $pdf->SetY($boxY + 29);
    }

    private function attestation(\FPDF $pdf): void
    {
        if ($pdf->GetY() > 245) $pdf->AddPage();
        $y = $pdf->GetY() + 3;
        $pdf->SetDrawColor(...self::LINE); $pdf->Line(18, $y, 192, $y);
        $pdf->SetXY(18, $y + 3); $pdf->SetTextColor(...self::MUTED); $pdf->SetFont('Helvetica', 'I', 7.5);
        $pdf->MultiCell(174, 4, 'This results statement is issued by WISDOM Blended Classes. The verification reference below identifies this generated copy.', 0, 'L');
        $y = $pdf->GetY() + 10; $pdf->SetDrawColor(...self::INK); $pdf->Line(18, $y, 88, $y); $pdf->Line(122, $y, 192, $y);
        $pdf->SetXY(18, $y + 1); $pdf->SetFont('Helvetica', 'B', 6.5); $pdf->Cell(70, 4, 'EXAMINATIONS OFFICER', 0, 0); $pdf->SetXY(122, $y + 1); $pdf->Cell(70, 4, 'DATE OF ISSUE', 0, 0);
    }

    /** @param list<Exam> $rows */
    private function footer(\FPDF $pdf, User $user, array $rows): void
    {
        $payload = [(string) $user->getId(), $user->getEmail(), (string) $user->getLevel()];
        foreach ($rows as $exam) { $a = $exam->toArray(); $payload[] = implode(':', [$a['id'], $a['subject_code'], $a['exam_number'], (string) $a['result']]); }
        $reference = 'WISDOM-' . strtoupper(substr(hash('sha256', implode('|', $payload)), 0, 16));
        $pdf->SetY(-20); $pdf->SetDrawColor(...self::LINE); $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY()); $pdf->Ln(2);
        $pdf->SetFont('Courier', 'B', 7.5); $pdf->SetTextColor(...self::NAVY); $pdf->Cell(85, 4, $reference, 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 7); $pdf->SetTextColor(...self::MUTED); $pdf->Cell(89, 4, 'Generated ' . date('j M Y H:i') . ' EAT', 0, 1, 'R');
    }

    private function wrappedLineCount(\FPDF $pdf, string $text, float $width): int
    {
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) return 1;
        $lines = 1; $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($pdf->GetStringWidth($candidate) > $width && $line !== '') { $lines++; $line = $word; } else { $line = $candidate; }
        }
        return $lines;
    }

    private function fit(\FPDF $pdf, string $text, float $width): string
    {
        while ($text !== '' && $pdf->GetStringWidth($text) > $width) $text = substr($text, 0, -1);
        return $text;
    }

    private function setBodyFont(\FPDF $pdf): void { $pdf->SetFont('Helvetica', '', 8); }

    private function safe(string $value): string
    {
        $converted = function_exists('iconv') ? @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value) : false;
        return $converted !== false ? $converted : (preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '');
    }
}
