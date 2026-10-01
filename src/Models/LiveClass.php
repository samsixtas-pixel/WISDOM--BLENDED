<?php
declare(strict_types=1);

namespace Wisdom\Models;

final class LiveClass
{
    public function __construct(
        private int $id,
        private string $level,
        private string $subjectName,
        private string $link,
        private string $scheduledAt,
        private string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['level'],
            (string) $row['subject_name'],
            (string) $row['link'],
            (string) $row['scheduled_at'],
            (string) $row['created_at'],
        );
    }

    public function getId(): int { return $this->id; }
    public function getLevel(): string { return $this->level; }
    public function getSubjectName(): string { return $this->subjectName; }
    public function getLink(): string { return $this->link; }
    public function getScheduledAt(): string { return $this->scheduledAt; }
    public function getCreatedAt(): string { return $this->createdAt; }

    public function isUpcoming(): bool
    {
        return strtotime($this->scheduledAt) > time();
    }
}