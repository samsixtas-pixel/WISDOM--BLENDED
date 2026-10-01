<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Core\AppException;
use Wisdom\Core\Validator;
use Wisdom\Models\Exam;
use Wisdom\Models\User;
use Wisdom\Repositories\AuditLogRepository;
use Wisdom\Repositories\ExamRepository;
use Wisdom\Repositories\UserRepository;

/** Administrator management of exam records; student-facing read access. */
final class ExamService
{
    public function __construct(
        private ExamRepository $exams,
        private UserRepository $users,
        private AuditLogRepository $audit,
    ) {
    }

    /** @param array<string,mixed> $data */
    public function validator(array $data): Validator
    {
        return (new Validator($data))
            ->required('user_id', 'Student')
            ->required('subject_code', 'Subject code')->length('subject_code', 'Subject code', 1, 30)
            ->required('exam_number', 'Exam number')->length('exam_number', 'Exam number', 1, 50)
            ->required('subject_name', 'Subject name')->length('subject_name', 'Subject name', 1, 255)
            ->numeric('weight', 'Weight', 0, 1000)
            ->numeric('result', 'Result', 0, 100)
            ->in('exam_status', 'Status', Exam::STATUSES);
    }

    /** @throws AppException */
    public function record(User $admin, int $userId, string $subjectCode, string $examNumber, string $subjectName, float $weight, ?float $result, ?string $grade, string $status): void
    {
        if ($this->users->find($userId) === null) {
            throw new AppException('Select a valid student.');
        }
        $id = $this->exams->create($userId, $subjectCode, $examNumber, $subjectName, $weight, $result, $grade, $status);
        $this->audit->record($admin->getId(), 'exam.recorded', "exam #$id for user #$userId");
    }

    /** Whether the student has an approved examination-fee payment (gate for viewing results). */
    public function canViewResults(User $user, \Wisdom\Repositories\PaymentRepository $payments): bool
    {
        return $payments->hasApprovedFor($user->getId(), 'examination');
    }

    /** @return list<Exam> */
    public function forUser(User $user): array
    {
        return $this->exams->forUser($user->getId());
    }

    /** @return list<array<string,mixed>> */
    public function allWithStudent(): array
    {
        return $this->exams->allWithStudent();
    }
}
