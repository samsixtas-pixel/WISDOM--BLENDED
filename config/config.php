<?php
declare(strict_types=1);

/*
 * Central configuration. Values can be overridden with real environment
 * variables or with a .env file in the project root (never commit .env).
 */
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . trim($value, " \t\"'"));
        }
    }
}

$env = static fn (string $key, string $default = ''): string => ($value = getenv($key)) === false ? $default : $value;

$dbUser = $env('DB_USER', 'root');
$dbUser = $dbUser === '' ? 'root' : $dbUser;

return [
    'app' => [
        'env'      => $env('APP_ENV', 'production'),          // production | development
        'debug'    => $env('APP_DEBUG', '0') === '1',         // show error details (development only)
        'timezone' => $env('APP_TIMEZONE', 'Africa/Dar_es_Salaam'),
        'base_url' => $env('APP_BASE_URL', 'http://127.0.0.1:9000'),
        'https_enforce' => $env('HTTPS_ENFORCE', '0') === '1',
    ],
    'pdf' => [
        'logo_path' => $env('PDF_LOGO_PATH', dirname(__DIR__) . '/public/image/image.jpeg'),
        'logo_width_mm' => 30,
    ],
    'db' => [
        'host'    => $env('DB_HOST', '127.0.0.1'),
        'port'    => (int) $env('DB_PORT', '3306'),
        'name'    => $env('DB_NAME', 'wisdom_db'),
        'user'    => $dbUser,
        'pass'    => $env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name'             => 'WISDOMSESSID',
        'idle_timeout'     => 1800,    // 30 minutes without activity
        'absolute_timeout' => 28800,   // 8 hours maximum
    ],
    'security' => [
        'max_login_attempts_per_email' => 5,
        'max_login_attempts_per_ip'    => 25,
        'lockout_minutes'              => 15,
        'password_min_length'          => 8,
        'reset_token_ttl_minutes'      => (int) $env('RESET_TOKEN_TTL', '60'),
    ],
    'upload' => [
        'dir'       => dirname(__DIR__) . '/storage/payments',
        'max_bytes' => 5 * 1024 * 1024,
    ],
    'fees' => [
        'standard_rate' => 50000,   // CPSP I, PD I, PD II (per subject)
        'advanced_rate' => 60000,   // CPSP II (per subject)
    ],
    'admin' => [
        'search_limit'     => 100,
        'live_refresh_sec' => 5,
    ],
    'avatar' => [
        'dir'       => dirname(__DIR__) . '/storage/avatars',
        'max_bytes' => 2 * 1024 * 1024,
    ],
    'contact' => [
        'phone_display' => '+255 673 266 852',
        'phone_wa'      => '255673266852',
        'email'         => $env('CONTACT_EMAIL', 'support@wisdom.local'),
    ],
    'mail' => [
        'from'      => $env('MAIL_FROM', 'no-reply@wisdom.local'),
        'from_name' => $env('MAIL_FROM_NAME', 'WISDOM Blended Classes'),
        'log_only'  => $env('MAIL_LOG_ONLY', '1') === '1',
        'log_dir'   => dirname(__DIR__) . '/storage/mail',
    ],
    'rate_limit' => [
        'dir' => dirname(__DIR__) . '/storage/ratelimits',
    ],
    // ===========================================================
    // =============== COMMENT OUT HERE (START) ==================
    // Feature flags. Toggle via .env to hide advanced features
    // on hosting without deleting code.
    // ===========================================================
    'features' => [
        'excel_import' => $env('FEATURE_EXCEL_IMPORT', '0') === '1',
        'results_pdf'  => $env('FEATURE_RESULTS_PDF', '1') === '1',
        'opportunistic_cleanup' => $env('FEATURE_OPPORTUNISTIC_CLEANUP', '1') === '1',
    ],
    // =============== COMMENT OUT HERE (END) ====================
];

