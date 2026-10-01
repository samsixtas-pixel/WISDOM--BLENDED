<?php
declare(strict_types=1);

namespace Wisdom\Models;

/** An administrator: manages users, reviews payments, records exams, reads reports. */
final class Admin extends User
{
    public function getRole(): string
    {
        return self::ROLE_ADMIN;
    }

    public function roleLabel(): string
    {
        return 'Administrator';
    }

    public function permissions(): array
    {
        return [
            'profile.view', 'profile.update',
            'admin.access', 'user.search', 'user.manage',
            'payment.review', 'exam.manage', 'report.view',
            'settings.manage', 'notice.manage', 'team.manage',
        ];
    }
}
