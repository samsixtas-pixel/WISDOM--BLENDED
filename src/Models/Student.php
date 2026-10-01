<?php
declare(strict_types=1);

namespace Wisdom\Models;

/** A learner: can view their own profile, pay fees and see published results. */
final class Student extends User
{
    public function getRole(): string
    {
        return self::ROLE_STUDENT;
    }

    public function roleLabel(): string
    {
        return 'Student';
    }

    public function permissions(): array
    {
        return ['profile.view', 'profile.update', 'payment.submit', 'exam.view'];
    }
}
