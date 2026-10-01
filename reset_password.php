<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\AppException;
use Wisdom\Core\Csrf;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Services\PasswordResetService;

if (Guard::user() !== null) {
    redirect(Guard::user()->isStaff() ? 'admin.php' : 'dashboard.php');
}
Guard::throttle('password.reset', 5, 900);
$service = App::get(PasswordResetService::class);
$token = Request::get('token');
$email = strtolower(Request::get('email'));
$error = '';
$done = false;

if ($token === '' || $email === '') {
    render_error_page(400, 'Invalid reset link', 'This password reset link is missing information.', 'Please request a new link.');
}
try {
    $service->consumeToken($token, $email);
} catch (AppException $exception) {
    render_error_page(400, 'Link expired', $exception->getMessage(), 'Request a new link from the sign-in page.');
}
if (Request::isPost()) {
    if (!Csrf::verifyRequest()) {
        $error = 'Your session expired. Please try again.';
    } else {
        try {
            $service->completeReset($token, $email, (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirmation'] ?? ''));
            $done = true;
        } catch (AppException $exception) {
            $error = $exception->getMessage();
        }
    }
}
$pageTitle = 'Set a new password';
$noIndex = true;
?>
<!doctype html><html lang="en"><head><?php require __DIR__ . '/partials/head.php'; ?><style nonce="<?= e(nonce()) ?>">body{min-height:100vh;display:grid;place-items:center;padding:24px;background:linear-gradient(135deg,#eef3ff,#f8fbff)}.card{width:min(100%,460px);padding:36px;background:#fff;border:1px solid #e3e8ef;border-radius:18px;box-shadow:0 18px 50px #1822301a}.field{margin:14px 0}.field label{display:block;margin-bottom:6px}.field input{width:100%;padding:11px 13px;border:1px solid #d5dce8;border-radius:9px}.btn{display:inline-flex;justify-content:center;width:100%;margin-top:8px;padding:12px 18px;border:0;border-radius:9px;background:#c9a227;color:#0d2b45;font-weight:700;cursor:pointer}.alert{padding:11px 14px;border-radius:9px;margin-bottom:16px}.alert--error{color:#9d1c2b;background:#fdeef0}.alert--success{color:#176b3a;background:#e9f8ef}</style></head><body><main class="card"><?php if ($done): ?><div class="alert alert--success">Password updated. You can now sign in.</div><a class="btn" href="login.php">Go to sign in</a><?php else: ?><div class="wordmark">WISDOM</div><h1>Choose a new password</h1><p>Setting a new password for <strong><?= e($email) ?></strong>.</p><?php if ($error !== ''): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?><form method="post"><?= csrf_field() ?><div class="field"><label for="password">New password</label><input id="password" name="password" type="password" minlength="8" required autocomplete="new-password"></div><div class="field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password"></div><button class="btn" type="submit">Update password</button></form><?php endif; ?></main></body></html>
