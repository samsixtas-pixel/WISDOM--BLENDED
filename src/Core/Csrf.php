<?php
declare(strict_types=1);

namespace Wisdom\Core;

/** Synchroniser-token CSRF protection tied to the session. */
final class Csrf
{
    private const KEY = '_csrf';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::token()) . '">';
    }

    public static function issueToken(string $formKey): string
    {
        $token = bin2hex(random_bytes(32));
        if (!isset($_SESSION['_csrf_once']) || !is_array($_SESSION['_csrf_once'])) {
            $_SESSION['_csrf_once'] = [];
        }
        $_SESSION['_csrf_once'][$formKey . ':' . $token] = time();
        $cutoff = time() - 7200;
        foreach ($_SESSION['_csrf_once'] as $key => $issuedAt) {
            if (!is_int($issuedAt) || $issuedAt < $cutoff) {
                unset($_SESSION['_csrf_once'][$key]);
            }
        }
        return $token;
    }

    public static function consumeToken(string $formKey, mixed $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }
        $key = $formKey . ':' . $token;
        if (!isset($_SESSION['_csrf_once'][$key])) {
            return false;
        }
        unset($_SESSION['_csrf_once'][$key]);
        return true;
    }

    public static function verify(mixed $submitted): bool
    {
        return is_string($submitted) && $submitted !== '' && hash_equals(self::token(), $submitted);
    }

    /** Verify the token posted with the current request. */
    public static function verifyRequest(): bool
    {
        return self::verify($_POST['csrf_token'] ?? null);
    }

    public static function rotate(): void
    {
        unset($_SESSION[self::KEY]);
        self::token();
    }
}
