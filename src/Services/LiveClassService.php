<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Core\AppException;
use Wisdom\Core\Validator;
use Wisdom\Models\User;
use Wisdom\Repositories\AuditLogRepository;
use Wisdom\Repositories\LiveClassRepository;
use Wisdom\Repositories\SubjectRepository;

final class LiveClassService
{
    public function __construct(
        private LiveClassRepository $classes,
        private SubjectRepository $subjects,
        private AuditLogRepository $audit,
    ) {
    }

    /** @param array<string,mixed> $data */
    public function validator(array $data): Validator
    {
        return (new Validator($data))
            ->required('level', 'Class')
            ->required('subject_name', 'Subject')->length('subject_name', 'Subject', 1, 255)
            ->required('link', 'Link')->length('link', 'Link', 5, 500)
            ->required('scheduled_at', 'Schedule');
    }

    /** @throws AppException */
    public function create(User $admin, string $level, string $subjectName, string $link, string $scheduledAt): void
    {
        $catalogue = $this->subjects->forLevel($level);
        if (!in_array($subjectName, $catalogue, true)) {
            throw new AppException('That subject does not belong to the chosen class.');
        }

        if (!filter_var($link, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $link)) {
            throw new AppException('Enter a valid link starting with http:// or https://.');
        }

        $timestamp = strtotime($scheduledAt);
        if ($timestamp === false) {
            throw new AppException('Enter a valid date and time.');
        }
        $normalised = date('Y-m-d H:i:s', $timestamp);

        $id = $this->classes->create($level, $subjectName, $link, $normalised, $admin->getId());
        $this->audit->record($admin->getId(), 'live_class.created', "class #$id, $level, $subjectName");
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->classes->all();
    }

    /** @return list<\Wisdom\Models\LiveClass> */
    public function forStudent(User $student): array
    {
        return $this->classes->forSubjects($student->getSubjects());
    }

    /** @throws AppException */
    public function delete(User $admin, int $id): void
    {
        if (!$this->classes->delete($id)) {
            throw new AppException('That class could not be found.');
        }
        $this->audit->record($admin->getId(), 'live_class.deleted', (string) $id);
    }
}