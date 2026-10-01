<?php
declare(strict_types=1);

namespace Wisdom\Core;

use Wisdom\Models\User;
use Wisdom\Services\AuthService;

/** Route-guard helpers shared by every page under public/. */
final class Guard
{
    public static function user(): ?User
    {
        static $cached = null;
        static $resolved = false;
        if (!$resolved) {
            $cached = App::get(AuthService::class)->currentUser();
            $resolved = true;
        }

        return $cached;
    }

    /** Redirect to login unless signed in and still active; returns the user otherwise. */
    public static function requireLogin(): User
    {
        $user = self::user();
        if ($user === null) {
            redirect('login.php');
        }

        return $user;
    }

    public static function requirePasswordResetHandled(): void
    {
        $user = self::user();
        if ($user === null || !$user->mustResetPassword()) {
            return;
        }
        $current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
        if ($current === 'set_password.php' || $current === 'logout.php') {
            return;
        }
        redirect('set_password.php');
    }

    /** Require a signed-in user who holds the given permission, else 403. */
    public static function requirePermission(string $permission): User
    {
        $user = self::requireLogin();
        if (!$user->can($permission)) {
            render_error_page(
                403,
                'Access denied',
                'You do not have permission to view this page.',
                'Contact an administrator if you believe this is a mistake.'
            );
        }

        return $user;
    }

    public static function throttle(string $key, int $maxHits, int $windowSeconds): void
    {
        $retryAfter = 0;
        $bucket = $key . ':' . Request::ip();
        if (App::get(RateLimiter::class)->attempt($bucket, $maxHits, $windowSeconds, $retryAfter)) {
            return;
        }

        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        if (Request::wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Too many requests. Please retry later.']);
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Too many requests. Please retry in ' . $retryAfter . ' seconds.';
        }
        exit;
    }
    /**
     * Require a signed-in user whose learning-fee payment has been approved.
     * Unapproved users are redirected to the fees page so they can pay.
     */
    public static function requireApproved(): User
    {
        $user = self::requireLogin();
        if (!$user->isApproved()) {
            redirect('fees.php?view=learning&locked=1');
        }

        return $user;
    }
}
