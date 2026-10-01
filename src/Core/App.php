<?php
declare(strict_types=1);

namespace Wisdom\Core;

use Throwable;
use Wisdom\Repositories\AuditLogRepository;
use Wisdom\Repositories\ExamRepository;
use Wisdom\Repositories\LoginAttemptRepository;
use Wisdom\Repositories\LiveClassRepository;
use Wisdom\Repositories\PaymentRepository;
use Wisdom\Repositories\SubjectRepository;
use Wisdom\Repositories\UserRepository;
use Wisdom\Repositories\PasswordResetRepository;
use Wisdom\Repositories\PasswordResetRequestRepository;
use Wisdom\Repositories\NoticeRepository;
use Wisdom\Repositories\SettingRepository;
use Wisdom\Repositories\ExamImportRepository;
use Wisdom\Services\NoticeService;
use Wisdom\Services\SettingService;
use Wisdom\Services\AuthService;
use Wisdom\Services\AvatarService;
use Wisdom\Services\ExamService;
use Wisdom\Services\FeeService;
use Wisdom\Services\FileUploader;
use Wisdom\Services\LiveClassService;
use Wisdom\Services\PaymentService;
use Wisdom\Services\RegistrationService;
use Wisdom\Services\ReportService;
use Wisdom\Services\UserService;
use Wisdom\Services\MailService;
use Wisdom\Services\PasswordResetService;
use Wisdom\Services\ResultsPdfService;
use Wisdom\Services\ExcelImportService;

/**
 * Application kernel + tiny service container.
 * Boots the environment (errors, timezone, headers, session) and builds
 * services lazily, wiring their dependencies in one place.
 */
final class App
{
    private static array $config = [];
    /** @var array<string,object> */
    private static array $instances = [];
    private static string $nonce = '';

    public static function boot(array $config): void
    {
        self::$config = $config;
        self::$nonce = base64_encode(random_bytes(16));

        date_default_timezone_set((string) self::config('app.timezone', 'UTC'));
        self::configureErrors();
        self::sendSecurityHeaders();
        Session::start(self::config('session'));
        self::maybeRunCleanup();
        self::enforceHttps();
    }

    /** Read a config value using dot notation, e.g. config('db.host'). */
    public static function config(string $path, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function nonce(): string
    {
        return self::$nonce;
    }

    /** Run low-priority maintenance once per hour; the normal request path is one file stat. */
    private static function maybeRunCleanup(): void
    {
        if (!(bool) self::config('features.opportunistic_cleanup', false)) {
            return;
        }
        $lockPath = WISDOM_ROOT . '/storage/logs/.cleanup.lock';
        if (is_file($lockPath) && (time() - (int) @filemtime($lockPath)) < 3600) {
            return;
        }
        $handle = @fopen($lockPath, 'c');
        if ($handle === false) {
            return;
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }
            if (is_file($lockPath) && (time() - (int) @filemtime($lockPath)) < 3600) {
                return;
            }
            @touch($lockPath);
            try {
                self::get(NoticeService::class)->sweepExpired();
                self::get(PaymentService::class)->cleanupOldProofs(self::get(FileUploader::class), 24);
            } catch (Throwable $exception) {
                error_log('Opportunistic cleanup failed: ' . $exception->getMessage());
            }
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function enforceHttps(): void
    {
        if (!(bool) self::config('app.https_enforce', false) || PHP_SAPI === 'cli' || Session::isHttps()) {
            return;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '' || !preg_match('/^[a-z0-9.:-]+$/i', $host)) {
            return;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function get(string $class): object
    {
        return self::$instances[$class] ??= self::build($class);
    }

    private static function build(string $class): object
    {
        return match ($class) {
            Database::class               => new Database(self::config('db')),
            RateLimiter::class            => new RateLimiter((string) self::config('rate_limit.dir')),
            UserRepository::class         => new UserRepository(self::get(Database::class)),
            SubjectRepository::class      => new SubjectRepository(self::get(Database::class)),
            PaymentRepository::class      => new PaymentRepository(self::get(Database::class)),
            ExamRepository::class         => new ExamRepository(self::get(Database::class)),
            LoginAttemptRepository::class => new LoginAttemptRepository(self::get(Database::class)),
            AuditLogRepository::class     => new AuditLogRepository(self::get(Database::class)),
            AuthService::class            => new AuthService(
                self::get(UserRepository::class),
                self::get(LoginAttemptRepository::class),
                self::get(AuditLogRepository::class),
                self::config('security'),
            ),
            RegistrationService::class    => new RegistrationService(
                self::get(UserRepository::class),
                self::get(SubjectRepository::class),
                self::get(AuditLogRepository::class),
                (int) self::config('security.password_min_length', 8),
            ),
            FileUploader::class           => new FileUploader(
                (string) self::config('upload.dir'),
                (int) self::config('upload.max_bytes'),
            ),
            AvatarService::class          => new AvatarService(
                (string) self::config('avatar.dir'),
                (int) self::config('avatar.max_bytes'),
                self::get(UserRepository::class),
                self::get(AuditLogRepository::class),
            ),
            FeeService::class             => new FeeService(
                self::get(PaymentRepository::class),
                self::get(FileUploader::class),
                self::get(AuditLogRepository::class),
                self::config('fees'),
            ),
            PaymentService::class         => new PaymentService(
                self::get(Database::class),
                self::get(PaymentRepository::class),
                self::get(UserRepository::class),
                self::get(AuditLogRepository::class),
            ),
            ExamService::class            => new ExamService(
                self::get(ExamRepository::class),
                self::get(UserRepository::class),
                self::get(AuditLogRepository::class),
            ),
            UserService::class            => new UserService(
                self::get(UserRepository::class),
                self::get(AuditLogRepository::class),
                (int) self::config('security.password_min_length', 8),
                self::get(SubjectRepository::class),
            ),
            ReportService::class          => new ReportService(self::get(Database::class)),
            SettingRepository::class      => new SettingRepository(self::get(Database::class)),
            NoticeRepository::class       => new NoticeRepository(self::get(Database::class)),
            SettingService::class         => new SettingService(
                self::get(SettingRepository::class),
                self::get(AuditLogRepository::class),
            ),
            NoticeService::class          => new NoticeService(
                self::get(NoticeRepository::class),
                self::get(AuditLogRepository::class),
            ),
            MailService::class            => new MailService(
                (string) self::config('mail.from'),
                (string) self::config('mail.from_name'),
                (bool) self::config('mail.log_only'),
                (string) self::config('mail.log_dir'),
            ),
            PasswordResetRepository::class => new PasswordResetRepository(self::get(Database::class)),
            PasswordResetRequestRepository::class => new PasswordResetRequestRepository(self::get(Database::class)),
            PasswordResetService::class => new PasswordResetService(
                self::get(UserRepository::class),
                self::get(PasswordResetRepository::class),
                self::get(PasswordResetRequestRepository::class),
                self::get(MailService::class),
                self::get(AuditLogRepository::class),
                (int) self::config('security.reset_token_ttl_minutes', 60),
                (int) self::config('security.password_min_length', 8),
                (string) self::config('app.base_url', 'http://127.0.0.1:9000'),
            ),
            ResultsPdfService::class => new ResultsPdfService(self::get(ExamRepository::class)),
            ExamImportRepository::class => new ExamImportRepository(self::get(Database::class)),
            ExcelImportService::class => new ExcelImportService(
                self::get(Database::class),
                self::get(UserRepository::class),
                self::get(ExamRepository::class),
                self::get(ExamImportRepository::class),
                self::get(AuditLogRepository::class),
            ),

            LiveClassRepository::class    => new LiveClassRepository(self::get(Database::class)),
            LiveClassService::class       => new LiveClassService(
                self::get(LiveClassRepository::class),
                self::get(SubjectRepository::class),
                self::get(AuditLogRepository::class),
            ),
            default                       => throw new \LogicException('Unknown service: ' . $class),
        };
    }

    private static function configureErrors(): void
    {
        $debug = (bool) self::config('app.debug', false);

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', WISDOM_ROOT . '/storage/logs/app.log');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return true;
            }
            error_log(sprintf('PHP error [%d] %s in %s:%d', $severity, $message, $file, $line));

            return true;
        });

        set_exception_handler(static function (Throwable $exception) use ($debug): void {
            error_log(sprintf(
                "Uncaught %s: %s in %s:%d\n%s",
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            ));

            if (!headers_sent()) {
                http_response_code(500);
            }
            if (Request::wantsJson()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Something went wrong. Please try again.']);
            } else {
                echo '<!doctype html><meta charset="utf-8"><title>Error | WISDOM</title>'
                    . '<p style="font:16px system-ui;padding:40px">Something went wrong. Please try again later.</p>';
                if ($debug) {
                    echo '<pre style="padding:0 40px">' . e($exception->getMessage()) . '</pre>';
                }
            }
        });
    }

    private static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        header_remove('Server');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Cross-Origin-Embedder-Policy: require-corp');
        header('X-Permitted-Cross-Domain-Policies: none');
        header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header(
            "Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . self::$nonce . "'; "
            . "style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; "
            . "connect-src 'self'; media-src 'self'; object-src 'none'; base-uri 'self'; "
            . "form-action 'self'; frame-ancestors 'none'"
        );
        if (Session::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
