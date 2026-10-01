<?php
declare(strict_types=1);

namespace Wisdom\Models;

final class Payment
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const CATEGORIES = ['programme', 'examination'];
    public const TYPES = ['half', 'full'];

    public function __construct(
        private int $id,
        private int $userId,
        private ?string $reference,
        private string $proofPath,
        private ?string $proofDeletedAt,
        private int $amount,
        private string $paymentType,
        private string $category,
        private string $status,
        private string $submittedAt,
        private ?string $reviewedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['user_id'],
            isset($row['reference']) ? (string) $row['reference'] : null,
            (string) $row['proof_path'],
            isset($row['proof_deleted_at']) ? (string) $row['proof_deleted_at'] : null,
            (int) $row['amount'],
            (string) $row['payment_type'],
            (string) $row['category'],
            (string) $row['status'],
            (string) $row['submitted_at'],
            isset($row['reviewed_at']) ? (string) $row['reviewed_at'] : null,
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

    public function getProofPath(): string
    {
        return $this->proofPath;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getProofDeletedAt(): ?string
    {
        return $this->proofDeletedAt;
    }

    public function isProofDeleted(): bool
    {
        return $this->proofDeletedAt !== null && $this->proofDeletedAt !== '';
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function isProgrammeFee(): bool
    {
        return $this->category === 'programme';
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->userId,
            'reference'    => $this->reference,
            'amount'       => $this->amount,
            'payment_type' => $this->paymentType,
            'category'     => $this->category,
            'status'       => $this->status,
            'submitted_at' => $this->submittedAt,
            'reviewed_at'  => $this->reviewedAt,
            'proof_deleted_at' => $this->proofDeletedAt,
        ];
    }
}
