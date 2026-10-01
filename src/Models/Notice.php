<?php
declare(strict_types=1);

namespace Wisdom\Models;

final class Notice
{
    public function __construct(
        private int $id,
        private string $title,
        private string $body,
        private string $createdAt,
        private bool $active,
        private ?string $expiresAt = null,
        private ?int $createdBy = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['title'],
            (string) $row['body'],
            (string) $row['created_at'],
            (bool) $row['is_active'],
            isset($row['expires_at']) ? (string) $row['expires_at'] : null,
            isset($row['created_by']) ? (int) $row['created_by'] : null,
        );
    }

    public function getId(): int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getBody(): string { return $this->body; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function isActive(): bool { return $this->active; }
    public function getExpiresAt(): ?string { return $this->expiresAt; }
    public function getCreatedBy(): ?int { return $this->createdBy; }
}