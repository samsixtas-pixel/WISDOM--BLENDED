<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Core\AppException;
use Wisdom\Models\Notice;
use Wisdom\Models\User;
use Wisdom\Repositories\AuditLogRepository;
use Wisdom\Repositories\NoticeRepository;

final class NoticeService
{
    private const SESSION_KEY = '_dismissed_notices';

    public function __construct(
        private NoticeRepository $notices,
        private AuditLogRepository $audit,
    ) {
    }

    /** @return list<Notice> Active notices the current session hasn't dismissed yet. */
    public function activeForCurrentUser(?int $userId = null): array
    {
        $dismissed = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($dismissed)) {
            $dismissed = [];
        }
        $dismissed = array_map('intval', $dismissed);

        return array_values(array_filter(
            $this->notices->active(),
            static fn (Notice $notice): bool => !in_array($notice->getId(), $dismissed, true) && ($userId === null || $notice->getCreatedBy() !== $userId)
        ));
    }

    public function dismiss(int $noticeId): void
    {
        $dismissed = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($dismissed)) {
            $dismissed = [];
        }
        if (!in_array($noticeId, $dismissed, true)) {
            $dismissed[] = $noticeId;
        }
        $_SESSION[self::SESSION_KEY] = $dismissed;
    }

    /** @throws AppException */
    public function create(User $admin, string $title, string $body, int $durationHours = 24): void
    {
        $title = trim($title);
        $body = trim($body);
        if ($title === '' || $body === '') {
            throw new AppException('Notice title and body are required.');
        }
        if (strlen($title) > 150) {
            throw new AppException('Notice title must be 150 characters or fewer.');
        }
        if (!in_array($durationHours, [24, 48, 72, 168], true)) $durationHours = 24;
        $expiresAt = date('Y-m-d H:i:s', time() + $durationHours * 3600);
        $id = $this->notices->create($title, $body, $admin->getId(), $expiresAt);
        $this->audit->record($admin->getId(), 'notice.created', "notice #$id");
    }

    public function sweepExpired(): int
    {
        try { return $this->notices->sweepExpired(); } catch (\Throwable) { return 0; }
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->notices->all();
    }

    /** @throws AppException */
    public function deactivate(User $admin, int $id): void
    {
        if ($id <= 0) {
            throw new AppException('That notice could not be found.');
        }
        $this->notices->deactivate($id);
        $this->audit->record($admin->getId(), 'notice.deactivated', (string) $id);
    }
}