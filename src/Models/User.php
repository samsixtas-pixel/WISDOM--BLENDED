<?php
declare(strict_types=1);

namespace Wisdom\Models;

/**
 * Abstract base for every account type.
 *
 * Encapsulation: all state is private and exposed only through getters.
 * Abstraction: subclasses must define their role and permissions.
 */
abstract class User
{
    public const ROLE_STUDENT = 'student';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SECRETARY = 'secretary';

    public const SEXES = ['male', 'female'];    //, 'prefer not to say'
    public const LEVELS = ['CPSP I', 'CPSP II', 'PD I', 'PD II'];

    /** @param list<string> $subjects */
    public function __construct(
        private int $id,
        private string $name,
        private string $email,
        private ?string $sex,
        private string $passwordHash,
        private ?string $level,
        private bool $approved,
        private bool $active,
        private string $createdAt,
        private array $subjects = [],
        private ?string $avatar = null,
        private ?string $termsAcceptedAt = null,
        private ?string $resultsAckAt = null,
        private bool $forcePasswordReset = false,
    ) {
    }

    /** Factory: builds the right subclass from a database row (polymorphic construction). */
    public static function fromRow(array $row, array $subjects = []): self
    {
        $args = [
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            isset($row['sex']) ? (string) $row['sex'] : null,
            (string) $row['password'],
            isset($row['level']) ? (string) $row['level'] : null,
            (bool) $row['is_approved'],
            (bool) $row['is_active'],
            (string) $row['created_at'],
            $subjects,
            isset($row['avatar']) && is_string($row['avatar']) ? $row['avatar'] : null,
            isset($row['terms_accepted_at']) && $row['terms_accepted_at'] !== null ? (string) $row['terms_accepted_at'] : null,
            isset($row['results_ack_at']) && $row['results_ack_at'] !== null ? (string) $row['results_ack_at'] : null,
            (bool) ($row['force_password_reset'] ?? false),
        ];

        return match ($row['role'] ?? self::ROLE_STUDENT) {
            self::ROLE_ADMIN => new Admin(...$args),
            self::ROLE_SECRETARY => new Secretary(...$args),
            default => new Student(...$args),
        };
    }

    /** The role name stored in the database. */
    abstract public function getRole(): string;

    /** @return list<string> permission keys granted to this account type */
    abstract public function permissions(): array;

    /** Human-readable label for the account type. */
    abstract public function roleLabel(): string;

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function isAdmin(): bool
    {
        return $this->getRole() === self::ROLE_ADMIN;
    }

    public function isSecretary(): bool
    {
        return $this->getRole() === self::ROLE_SECRETARY;
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isSecretary();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getSex(): ?string
    {
        return $this->sex;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    /** @return list<string> */
    public function getSubjects(): array
    {
        return $this->subjects;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function getTermsAcceptedAt(): ?string
    {
        return $this->termsAcceptedAt;
    }

    public function hasAcceptedTerms(): bool
    {
        return $this->termsAcceptedAt !== null && $this->termsAcceptedAt !== '';
    }

    public function mustResetPassword(): bool
    {
        return $this->forcePasswordReset;
    }

    public function getResultsAckAt(): ?string
    {
        return $this->resultsAckAt;
    }

    public function getInitial(): string
    {
        return strtoupper(substr($this->name, 0, 1)) ?: '?';
    }

    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, $this->passwordHash);
    }

    public function passwordNeedsRehash(): bool
    {
        return password_needs_rehash($this->passwordHash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /** The stored hash is only exposed to the authentication layer. */
    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    /** Safe representation (never contains the password hash). */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'email'       => $this->email,
            'sex'         => $this->sex,
            'level'       => $this->level,
            'role'        => $this->getRole(),
            'is_approved' => $this->approved,
            'is_active'   => $this->active,
            'created_at'  => $this->createdAt,
            'subjects'    => $this->subjects,
            'avatar'      => $this->avatar,
        ];
    }
}
