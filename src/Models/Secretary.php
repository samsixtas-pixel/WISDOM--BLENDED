<?php
declare(strict_types=1);

namespace Wisdom\Models;

/** Staff member limited to student lookup and exam-entry workflows. */
final class Secretary extends User
{
    public function getRole(): string
    {
        return self::ROLE_SECRETARY;
    }

    public function roleLabel(): string
    {
        return 'Secretary';
    }

    public function permissions(): array
    {
        return ['profile.view', 'profile.update', 'admin.access', 'user.search', 'exam.manage'];
    }
}