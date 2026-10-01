<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Core\AppException;
use Wisdom\Core\Database;
use Wisdom\Models\User;
use Wisdom\Repositories\AuditLogRepository;
use Wisdom\Repositories\ExamImportRepository;
use Wisdom\Repositories\ExamRepository;
use Wisdom\Repositories\UserRepository;

/** Validates XLSX result rows and sends unresolved matches to a review queue. */
final class ExcelImportService
{
    private const HEADER_MAP = [
        'exam_number' => ['exam_number', 'exam_no', 'examnumber', 'number_exam'],
        'subject_code' => ['subject_code', 'code', 'subjectcode'],
        'subject_name' => ['subject_name', 'subject', 'subjectname'],
        'weight' => ['weight', 'weight_percent', 'weightage'],
        'result' => ['result', 'results', 'score', 'marks'],
        'grade' => ['grade', 'letter_grade'],
        'status' => ['status', 'exam_status'],
        'email' => ['email', 'student_email', 'candidate_email'],
        'student_id' => ['student_id', 'id', 'user_id'],
        'student_name' => ['student_name', 'name', 'candidate_name', 'full_name'],
    ];

    public function __construct(
        private Database $db,
        private UserRepository $users,
        private ExamRepository $exams,
        private ExamImportRepository $imports,
        private AuditLogRepository $audit,
    ) {
    }

    /** @return array{batch_id:int,total:int,matched:int,pending:int,rejected:int} */
    public function ingest(string $filePath, string $originalFilename, User $admin): array
    {
        if (!is_file($filePath) || !is_readable($filePath) || filesize($filePath) === 0) {
            throw new AppException('The uploaded workbook is empty or unreadable.');
        }
        $rows = (new XlsxReader($filePath))->load()->rowsWithHeader();
        if ($rows === []) throw new AppException('The workbook contains no data rows.');
        if (count($rows) > 5000) throw new AppException('The workbook can contain at most 5,000 data rows.');

        $batchId = $this->imports->createBatch($admin->getId(), basename($originalFilename), count($rows));
        $matched = 0;
        $pending = 0;
        $rejected = 0;
        foreach ($rows as $index => $rawRow) {
            $rowNumber = $index + 2;
            $normalized = $this->normalizeRow($rawRow);
            if ($normalized['error'] !== '') {
                $this->imports->addPending($batchId, $rowNumber, $rawRow, $normalized['error']);
                $pending++;
                continue;
            }

            $match = $this->matchStudent($normalized);
            $student = $match['user'];
            if ($student === null) {
                $this->imports->addPending($batchId, $rowNumber, $rawRow, $match['reason'], $match['suggested_id']);
                $pending++;
                continue;
            }
            if (!$this->isEnrolledInSubject($student, $normalized['subject_name'])) {
                $this->imports->addPending(
                    $batchId,
                    $rowNumber,
                    $rawRow,
                    'Subject is not in ' . $student->getName() . "'s registered subjects.",
                    $student->getId()
                );
                $pending++;
                continue;
            }

            try {
                $this->exams->create(
                    $student->getId(),
                    $normalized['subject_code'],
                    $normalized['exam_number'],
                    $normalized['subject_name'],
                    $normalized['weight'],
                    $normalized['result'],
                    $normalized['grade'],
                    $normalized['status'],
                );
                $matched++;
            } catch (\Throwable $exception) {
                // Keep database details in logs, never in the student's/admin UI.
                error_log('Excel row import failed (batch ' . $batchId . ', row ' . $rowNumber . '): ' . $exception->getMessage());
                $this->imports->addPending($batchId, $rowNumber, $rawRow, 'The row could not be saved; review the values and try again.', $student->getId());
                $pending++;
            }
        }

        $this->imports->updateBatchCounts($batchId, $matched, $pending, $rejected);
        $this->audit->record($admin->getId(), 'exam.import_batch', "batch #$batchId: $matched matched, $pending pending");
        return ['batch_id' => $batchId, 'total' => count($rows), 'matched' => $matched, 'pending' => $pending, 'rejected' => $rejected];
    }

    /** @param array<string,string> $row @return array{error:string,exam_number:string,subject_code:string,subject_name:string,weight:float,result:?float,grade:?string,status:string,email:string,student_id:string,student_name:string} */
    private function normalizeRow(array $row): array
    {
        $pick = static function (string $key) use ($row): string {
            foreach (self::HEADER_MAP[$key] as $variant) {
                if (isset($row[$variant]) && trim($row[$variant]) !== '') return trim($row[$variant]);
            }
            return '';
        };
        $examNumber = $pick('exam_number');
        $subjectName = $pick('subject_name');
        $subjectCode = $pick('subject_code');
        $weightRaw = $pick('weight');
        $resultRaw = $pick('result');
        $status = strtolower($pick('status'));
        $weight = $weightRaw === '' ? 100.0 : (is_numeric($weightRaw) ? (float) $weightRaw : -1.0);
        $result = $resultRaw === '' ? null : (is_numeric($resultRaw) ? (float) $resultRaw : -1.0);
        $error = '';
        if ($examNumber === '') $error = 'Exam number is missing.';
        elseif (strlen($examNumber) > 50) $error = 'Exam number exceeds 50 characters.';
        elseif ($subjectName === '') $error = 'Subject name is missing.';
        elseif (strlen($subjectName) > 255) $error = 'Subject name exceeds 255 characters.';
        elseif ($weight < 0 || $weight > 1000) $error = 'Weight must be between 0 and 1000.';
        elseif ($result !== null && ($result < 0 || $result > 100)) $error = 'Result must be numeric and between 0 and 100.';
        if ($status === '') $status = 'published';
        if (!in_array($status, ['scheduled', 'completed', 'published'], true)) $error = $error ?: 'Status is invalid.';
        if ($subjectCode === '') $subjectCode = $subjectName;
        if (strlen($subjectCode) > 30) $error = $error ?: 'Subject code exceeds 30 characters.';
        $grade = $pick('grade');

        return [
            'error' => $error,
            'exam_number' => $examNumber,
            'subject_code' => $subjectCode,
            'subject_name' => $subjectName,
            'weight' => $weight,
            'result' => $result,
            'grade' => $grade === '' ? null : substr($grade, 0, 5),
            'status' => $status,
            'email' => strtolower($pick('email')),
            'student_id' => $pick('student_id'),
            'student_name' => $pick('student_name'),
        ];
    }

    /** @param array<string,mixed> $row @return array{user:?User,reason:string,suggested_id:?int} */
    private function matchStudent(array $row): array
    {
        if ($row['email'] !== '') {
            if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) return ['user' => null, 'reason' => 'Student email is invalid.', 'suggested_id' => null];
            $user = $this->users->findByEmail($row['email']);
            if ($user !== null && !$user->isStaff()) return ['user' => $user, 'reason' => '', 'suggested_id' => $user->getId()];
            return ['user' => null, 'reason' => 'No student found with that email.', 'suggested_id' => null];
        }
        if ($row['student_id'] !== '') {
            if (!ctype_digit($row['student_id'])) return ['user' => null, 'reason' => 'Student ID must be numeric.', 'suggested_id' => null];
            $user = $this->users->find((int) $row['student_id']);
            if ($user !== null && !$user->isStaff()) return ['user' => $user, 'reason' => '', 'suggested_id' => $user->getId()];
            return ['user' => null, 'reason' => 'No student found with that ID.', 'suggested_id' => null];
        }
        $existingExam = $this->exams->findByExamNumber($row['exam_number']);
        if ($existingExam !== null) {
            $existingStudent = $this->users->find($existingExam->getUserId());
            if ($existingStudent !== null && !$existingStudent->isStaff()) {
                return ['user' => $existingStudent, 'reason' => '', 'suggested_id' => $existingStudent->getId()];
            }
        }
        if ($row['student_name'] !== '') {
            $matches = array_values(array_filter(
                $this->users->search($row['student_name'], 20),
                static fn (User $user): bool => !$user->isStaff() && strcasecmp(trim($user->getName()), trim($row['student_name'])) === 0
            ));
            if (count($matches) === 1) return ['user' => $matches[0], 'reason' => '', 'suggested_id' => $matches[0]->getId()];
            if (count($matches) > 1) return ['user' => null, 'reason' => 'Multiple students have that exact name.', 'suggested_id' => null];
            return ['user' => null, 'reason' => 'No student found with that name.', 'suggested_id' => null];
        }
        return ['user' => null, 'reason' => 'Include a student email, student ID, or student name.', 'suggested_id' => null];
    }

    private function isEnrolledInSubject(User $student, string $subjectName): bool
    {
        foreach ($student->getSubjects() as $subject) {
            if (strcasecmp(trim($subject), trim($subjectName)) === 0) return true;
        }
        return false;
    }

    /** @return list<array<string,mixed>> */
    public function pendingReviews(int $limit = 200): array { return $this->imports->pendingReviews($limit); }
    public function pendingCount(): int { return $this->imports->pendingCount(); }
    /** @return list<array<string,mixed>> */
    public function recentBatches(int $limit = 20): array { return $this->imports->recentBatches($limit); }

    public function approvePending(User $admin, int $reviewId, int $userId): void
    {
        $row = $this->imports->findPending($reviewId);
        if ($row === null) throw new AppException('That review is no longer pending.');
        $student = $this->users->find($userId);
        if ($student === null || $student->isStaff()) throw new AppException('Choose a valid student.');
        $raw = json_decode((string) $row['raw_data'], true);
        if (!is_array($raw)) throw new AppException('The stored import row is invalid.');
        $normalized = $this->normalizeRow($raw);
        if ($normalized['error'] !== '' || !$this->isEnrolledInSubject($student, $normalized['subject_name'])) {
            throw new AppException('The chosen student must be enrolled in the imported subject, and the row must be valid.');
        }
        $this->db->transaction(function () use ($student, $normalized, $reviewId, $admin): void {
            $this->exams->create($student->getId(), $normalized['subject_code'], $normalized['exam_number'], $normalized['subject_name'], $normalized['weight'], $normalized['result'], $normalized['grade'], $normalized['status']);
            if (!$this->imports->markPendingApproved($reviewId, $admin->getId())) {
                throw new AppException('That review was just processed; refresh and verify its status.');
            }
        });
        $this->audit->record($admin->getId(), 'exam.import_approved', "review #$reviewId -> user #{$student->getId()}");
    }

    public function rejectPending(User $admin, int $reviewId): void
    {
        $updated = $this->db->transaction(fn (): bool => $this->imports->markPendingRejected($reviewId, $admin->getId()));
        if (!$updated) throw new AppException('That review is no longer pending.');
        $this->audit->record($admin->getId(), 'exam.import_rejected', "review #$reviewId");
    }
}
