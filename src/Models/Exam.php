<?php
declare(strict_types=1);

namespace Wisdom\Models;

final class Exam
{
    public const STATUSES = ['scheduled', 'completed', 'published'];

    public function __construct(
        private int $id,
        private int $userId,
        private string $subjectCode,
        private string $examNumber,
        private string $subjectName,
        private float $weight,
        private ?float $result,
        private ?string $grade,
        private string $status,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['subject_code'],
            (string) $row['exam_number'],
            (string) $row['subject_name'],
            (float) $row['weight'],
            $row['result'] === null ? null : (float) $row['result'],
            $row['grade'] === null ? null : (string) $row['grade'],
            (string) $row['status'],
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** Full record (admin view). */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->userId,
            'subject_code' => $this->subjectCode,
            'exam_number'  => $this->examNumber,
            'subject_name' => $this->subjectName,
            'weight'       => $this->weight,
            'result'       => $this->result,
            'grade'        => $this->grade,
            'status'       => $this->status,
        ];
    }

    /** Student-facing record: result and grade stay hidden until the exam is published. */
    public function toStudentArray(): array
    {
        $row = $this->toArray();
        if (!$this->isPublished()) {
            $row['result'] = null;
            $row['grade'] = null;
        }

        return $row;
    }
}
