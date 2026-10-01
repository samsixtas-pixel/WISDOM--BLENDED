<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Core\AppException;
use Wisdom\Core\Validator;
use Wisdom\Models\User;
use Wisdom\Repositories\AuditLogRepository;
use Wisdom\Repositories\PasswordResetRepository;
use Wisdom\Repositories\PasswordResetRequestRepository;
use Wisdom\Repositories\UserRepository;

final class PasswordResetService
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetRepository $tokens,
        private PasswordResetRequestRepository $requests,
        private MailService $mailer,
        private AuditLogRepository $audit,
        private int $tokenTtlMinutes,
        private int $passwordMinLength,
        private string $appBaseUrl,
    ) {
    }

    public function sendResetLink(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            usleep(random_int(180000, 260000));
            return false;
        }
        if ($this->tokens->countRecentForUser($user->getId(), 15) >= 3) {
            return false;
        }
        $rawToken = bin2hex(random_bytes(32));
        $this->tokens->create($user->getId(), hash('sha256', $rawToken), date('Y-m-d H:i:s', time() + $this->tokenTtlMinutes * 60));
        $link = rtrim($this->appBaseUrl, '/') . '/reset_password.php?token=' . urlencode($rawToken) . '&email=' . urlencode($user->getEmail());
        $intro = '<p>Hello ' . htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8') . ',</p><p>We received a request to reset your WISDOM password. This link expires in ' . $this->tokenTtlMinutes . ' minutes.</p>';
        $html = $this->mailer->brandedTemplate('Reset your password', $intro, 'Choose a new password', $link);
        $sent = $this->mailer->send($user->getEmail(), 'Reset your WISDOM password', $html);
        $this->audit->record($user->getId(), 'password.reset_requested');
        return $sent;
    }

    public function consumeToken(string $rawToken, string $email): User
    {
        $email = strtolower(trim($email));
        $row = $rawToken === '' || $email === '' ? null : $this->tokens->findValidByHash(hash('sha256', $rawToken));
        if ($row === null) {
            throw new AppException('That reset link is invalid or has expired.');
        }
        $user = $this->users->find((int) $row['user_id']);
        if ($user === null || strtolower($user->getEmail()) !== $email) {
            throw new AppException('That reset link is invalid or has expired.');
        }
        return $user;
    }

    public function completeReset(string $rawToken, string $email, string $password, string $confirmation): User
    {
        $validator = (new Validator(['password' => $password, 'password_confirmation' => $confirmation]))
            ->required('password', 'Password')
            ->password('password', $this->passwordMinLength)
            ->matches('password_confirmation', 'password', 'Passwords do not match.');
        if (!$validator->passes()) {
            $errors = $validator->errors();
            throw new AppException((string) reset($errors));
        }
        $user = $this->consumeToken($rawToken, $email);
        $row = $this->tokens->findValidByHash(hash('sha256', $rawToken));
        if ($row === null) {
            throw new AppException('That reset link is invalid or has expired.');
        }
        $this->users->setPasswordAndClearReset($user->getId(), password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));
        $this->tokens->markUsed((int) $row['id']);
        $this->audit->record($user->getId(), 'password.reset_completed');
        return $user;
    }

    public function requestAdminApproval(int $userId, string $note = ''): bool
    {
        $id = $this->requests->create($userId, function_exists('mb_substr') ? mb_substr($note, 0, 500) : substr($note, 0, 500));
        $this->audit->record($userId, 'password.admin_request', "request #{$id}");
        return $id > 0;
    }

    public function pendingRequests(): array
    {
        return $this->requests->pending();
    }

    public function pendingRequestCount(): int
    {
        return $this->requests->countPending();
    }

    public function approve(User $admin, int $requestId): array
    {
        $row = $this->requests->find($requestId);
        if ($row === null || $row['status'] !== 'pending') {
            throw new AppException('That request is no longer pending.');
        }
        $user = $this->users->find((int) $row['user_id']);
        if ($user === null) {
            throw new AppException('The user for that request no longer exists.');
        }
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $pool = $letters . $digits;
        $chars = [$letters[random_int(0, strlen($letters) - 1)], $digits[random_int(0, strlen($digits) - 1)]];
        for ($i = 0; $i < 10; $i++) {
            $chars[] = $pool[random_int(0, strlen($pool) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        $tempPassword = implode('', $chars);
        $this->users->setPasswordForcedReset($user->getId(), password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]));
        $this->requests->markApproved($requestId, $admin->getId());
        $this->audit->record($admin->getId(), 'password.admin_approved', "request #{$requestId}, user #{$user->getId()}");
        $intro = '<p>Hello ' . htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8') . ',</p><p>Your temporary password is:</p><p><strong>' . htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8') . '</strong></p><p>You must choose a new password after signing in.</p>';
        $this->mailer->send($user->getEmail(), 'Your WISDOM temporary password', $this->mailer->brandedTemplate('Your temporary password', $intro));
        return [$user, $tempPassword];
    }

    public function reject(User $admin, int $requestId): void
    {
        $row = $this->requests->find($requestId);
        if ($row === null || $row['status'] !== 'pending') {
            throw new AppException('That request is no longer pending.');
        }
        $this->requests->markRejected($requestId, $admin->getId());
        $this->audit->record($admin->getId(), 'password.admin_rejected', "request #{$requestId}");
    }
}
