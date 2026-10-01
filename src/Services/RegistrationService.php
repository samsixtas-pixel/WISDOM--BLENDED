<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Core\AppException;
use Wisdom\Core\Validator;
use Wisdom\Models\User;
use Wisdom\Repositories\AuditLogRepository;
use Wisdom\Repositories\SubjectRepository;
use Wisdom\Repositories\UserRepository;

/** Turns a registration form submission into a new student account. */
final class RegistrationService
{
    public function __construct(
        private UserRepository $users,
        private SubjectRepository $subjects,
        private AuditLogRepository $audit,
        private int $passwordMinLength,
    ) {
    }

    /** @param array<string,mixed> $data */
    public function validator(array $data): Validator
    {
        return (new Validator($data))
            ->required('name', 'Full name')->length('name', 'Full name', 2, 255)
            ->required('email', 'Email')->email('email')
            ->required('sex', 'Sex')->in('sex', 'Sex', User::SEXES)
            ->required('level', 'Programme')->in('level', 'Programme', User::LEVELS)
            ->required('password', 'Password')->password('password', $this->passwordMinLength)
            ->matches('password_confirmation', 'password', 'Passwords do not match.');
    }

    /**
     * @param list<string> $requestedSubjects
     * @throws AppException
     */
    public function register(string $name, string $email, string $sex, string $level, array $requestedSubjects, string $password): User
    {
        $allowed = $this->subjects->forLevel($level);
        $subjects = array_values(array_intersect($allowed, $requestedSubjects));

        if ($subjects === []) {
            throw new AppException('Choose at least one subject for your programme.');
        }
        if ($this->users->emailExists($email)) {
            throw new AppException('That email is already registered.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $id = $this->users->create($name, $email, $sex, $hash, $level, $subjects);
        $this->audit->record($id, 'user.registered');

        return $this->users->find($id) ?? throw new AppException('Registration failed. Please try again.');
    }
}
