<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Core\AppException;
use Wisdom\Models\User;
use Wisdom\Repositories\AuditLogRepository;
use Wisdom\Repositories\UserRepository;
use Wisdom\Repositories\SubjectRepository;

/** Administrator actions on accounts: search, approve/suspend, delete. */
final class UserService
{
    public function __construct(
        private UserRepository $users,
        private AuditLogRepository $audit,
        private int $passwordMinLength,
        private SubjectRepository $subjects,
    ) {
    }

    /** @return list<User> */
    public function search(string $term, int $limit): array
    {
        return $this->users->search($term, $limit);
    }

    public function page(\Wisdom\Core\ListQuery $query): array
    {
        return $this->users->paginate($query);
    }

    /** @throws AppException */
    public function setActive(User $admin, int $userId, bool $active): void
    {
        $target = $this->users->find($userId);
        if ($target === null) {
            throw new AppException('That account no longer exists.');
        }
        if ($target->isAdmin() && !$active) {
            throw new AppException('Administrator accounts cannot be suspended here.');
        }
        $this->users->setActive($userId, $active);
        $this->audit->record($admin->getId(), $active ? 'user.reactivated' : 'user.suspended', (string) $userId);
    }

    /** @throws AppException */
    public function delete(User $admin, int $userId): void
    {
        if ($userId === $admin->getId()) {
            throw new AppException('You cannot delete your own account.');
        }
        $target = $this->users->find($userId);
        if ($target === null || $target->isAdmin()) {
            throw new AppException('Administrator accounts cannot be deleted here.');
        }
        if (!$this->users->delete($userId)) {
            throw new AppException('That account could not be deleted.');
        }
        $this->audit->record($admin->getId(), 'user.deleted', (string) $userId);
    }

    /** @throws AppException */
    public function createTeamMember(User $actor, string $name, string $email, string $password, string $confirmation, string $role): void
    {
        $name = trim($name);
        $email = trim($email);
        $role = trim($role);
        if (!in_array($role, [User::ROLE_ADMIN, User::ROLE_SECRETARY], true)) {
            throw new AppException('Choose a valid team role.');
        }
        if (str_len($name) < 2 || str_len($name) > 255) {
            throw new AppException('Enter a name between 2 and 255 characters.');
        }
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AppException('Enter a valid email address.');
        }
        if ($this->users->emailExists($email)) {
            throw new AppException('That email is already registered.');
        }
        if (strlen($password) < $this->passwordMinLength || strlen($password) > 72) {
            throw new AppException(sprintf('Password must be between %d and 72 characters.', $this->passwordMinLength));
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new AppException('Password must contain at least one letter and one number.');
        }
        if (!hash_equals($password, $confirmation)) {
            throw new AppException('Passwords do not match.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $id = $this->users->createTeamMember($name, $email, $hash, $role);
        $this->audit->record($actor->getId(), 'team.created', "$role #$id");
    }

    public function updateOwnProfile(User $user, string $name, string $sex, array $selectedSubjects): void
    {
        $name = trim($name);
        $catalogue = $this->subjects->forLevel((string) $user->getLevel());
        $selectedSubjects = array_values(array_unique(array_filter($selectedSubjects, 'is_string')));
        if (str_len($name) < 2 || str_len($name) > 255) {
            throw new AppException('Enter a name between 2 and 255 characters.');
        }
        if (!in_array($sex, User::SEXES, true)) {
            throw new AppException('Choose a valid sex.');
        }
        if ($selectedSubjects === [] || array_diff($selectedSubjects, $catalogue) !== []) {
            throw new AppException('Choose at least one valid subject for your programme.');
        }
        $this->users->updateProfile($user->getId(), $name, $sex, $selectedSubjects);
        $this->audit->record($user->getId(), 'profile.updated');
    }
}
