<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Core\AppException;
use Wisdom\Core\Request;
use Wisdom\Core\Session;
use Wisdom\Core\Validator;
use Wisdom\Models\User;
use Wisdom\Repositories\AuditLogRepository;
use Wisdom\Repositories\LoginAttemptRepository;
use Wisdom\Repositories\UserRepository;

/** Sign-in, sign-out and brute-force throttling. */
final class AuthService
{
    public function __construct(
        private UserRepository $users,
        private LoginAttemptRepository $attempts,
        private AuditLogRepository $audit,
        private array $security,
    ) {
    }

    /** @throws AppException on invalid credentials or when locked out */
    public function attempt(string $email, string $password): User
    {
        $ip = Request::ip();
        $emailLimit = (int) $this->security['max_login_attempts_per_email'];
        $ipLimit = (int) $this->security['max_login_attempts_per_ip'];
        $lockoutMinutes = (int) $this->security['lockout_minutes'];

        if ($email !== '' && $this->attempts->recentFailuresForEmail($email, $lockoutMinutes) >= $emailLimit) {
            throw new AppException(sprintf('Too many failed attempts. Try again in %d minutes.', $lockoutMinutes));
        }
        if ($this->attempts->recentFailuresForIp($ip, $lockoutMinutes) >= $ipLimit) {
            throw new AppException('Too many failed attempts from this network. Please try again later.');
        }
        if ($email !== '' && $this->attempts->distinctFailuresForEmail($email, 60) >= 8) {
            $this->audit->record(null, 'login.distributed_lockout', $email);
            throw new AppException('This account is temporarily locked for security. Please try again later.');
        }

        $user = $email === '' ? null : $this->users->findByEmail($email);

        // Always run password_verify, even with no user, so a fixed-cost hash
        // is checked either way — this keeps timing consistent and hides
        // whether the email exists from a timing side-channel.
        $hash = $user?->getPasswordHash() ?? '$2y$12$invalidinvalidinvaliduinvalidinvalidinvalidinvalidin';
        $passwordOk = password_verify($password, $hash);

        if (!$user || !$passwordOk || !$user->isActive()) {
            $this->attempts->record($email, $ip, false);
            $this->audit->record($user?->getId(), 'login.failed', $email);
            throw new AppException('The email or password is incorrect.');
        }

        $this->attempts->record($email, $ip, true);
        $this->audit->record($user->getId(), 'login.success');

        if ($user->passwordNeedsRehash()) {
            $this->users->updatePasswordHash($user->getId(), password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));
        }

        return $user;
    }

    public function login(User $user): void
    {
        Session::regenerate(); // prevents session fixation
        Session::set('user_id', $user->getId());
        Session::set('role', $user->getRole());
        $_SESSION['_iphash'] = hash('sha256', Request::ip() . '|' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        Session::clearIpChangedFlag();
    }

    public function logout(): void
    {
        $userId = Session::get('user_id');
        if (is_int($userId)) {
            $this->audit->record($userId, 'logout');
        }
        \Wisdom\Core\Session::destroy();
    }

    /** Re-load the fresh user for the current session, or null if signed out / deleted. */
    public function currentUser(): ?User
    {
        $id = Session::get('user_id');
        if (!is_int($id)) {
            return null;
        }

        $user = $this->users->find($id);
        if ($user === null || !$user->isActive()) {
            Session::destroy();
            return null;
        }

        return $user;
    }

    public function loginValidator(array $data): Validator
    {
        return (new Validator($data))->required('email', 'Email')->email('email')->required('password', 'Password');
    }
}
