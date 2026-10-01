<?php
declare(strict_types=1);

namespace Wisdom\Core;

/** Small helpers for reading input safely. */
final class Request
{
    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /** Trimmed string from POST ('' when missing or not a string). */
    public static function post(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /** Trimmed string from the query string. */
    public static function get(string $key): string
    {
        $value = $_GET[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /** @return list<string> */
    public static function postList(string $key): array
    {
        $value = $_POST[$key] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    public static function intGet(string $key): int
    {
        return (int) filter_var($_GET[$key] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    }

    public static function intPost(string $key): int
    {
        return (int) filter_var($_POST[$key] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    }

    /** Client IP. X-Forwarded-For is deliberately NOT trusted (spoofable). */
    public static function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }
}
