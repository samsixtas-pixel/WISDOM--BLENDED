<?php
declare(strict_types=1);

namespace Wisdom\Core;

/**
 * Hardened session handling: strict mode, HttpOnly/SameSite cookies,
 * idle + absolute timeouts, user-agent binding and flash messages.
 */
final class Session
{
    public static function start(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        session_name($config['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $now = time();
        $fingerprint = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $ipHash = hash('sha256', Request::ip() . '|' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

        if (isset($_SESSION['_created'], $_SESSION['_last'], $_SESSION['_fp'])) {
            $idleExpired = ($now - (int) $_SESSION['_last']) > $config['idle_timeout'];
            $absoluteExpired = ($now - (int) $_SESSION['_created']) > $config['absolute_timeout'];
            $mismatch = !hash_equals((string) $_SESSION['_fp'], $fingerprint);
            $ipChanged = isset($_SESSION['_iphash']) && !hash_equals((string) $_SESSION['_iphash'], $ipHash);

            if ($idleExpired || $absoluteExpired || $mismatch) {
                $wasSignedIn = !empty($_SESSION['user_id']);
                $_SESSION = [];
                session_regenerate_id(true);
                if ($wasSignedIn) {
                    self::flash('info', 'Your session expired. Please sign in again.');
                }
            } elseif ($ipChanged && !empty($_SESSION['user_id'])) {
                $_SESSION['_ip_changed'] = true;
            }
        }

        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = $now;
            $_SESSION['_fp'] = $fingerprint;
            $_SESSION['_iphash'] = $ipHash;
        }
        $_SESSION['_last'] = $now;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?: 'Lax',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Store a one-time message (survives exactly one redirect). */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    /** Read and clear a one-time message. */
    public static function pullFlash(string $type): string
    {
        $message = (string) ($_SESSION['_flash'][$type] ?? '');
        unset($_SESSION['_flash'][$type]);

        return $message;
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    public static function ipChangedThisSession(): bool
    {
        return !empty($_SESSION['_ip_changed']);
    }

    public static function clearIpChangedFlag(): void
    {
        unset($_SESSION['_ip_changed']);
    }
}
