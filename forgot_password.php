<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Csrf;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Repositories\UserRepository;
use Wisdom\Services\PasswordResetService;

if (Guard::user() !== null) {
    redirect(Guard::user()->isStaff() ? 'admin.php' : 'dashboard.php');
}
Guard::throttle('password.forgot', 5, 300);

$service = App::get(PasswordResetService::class);
$error = '';
$notice = '';
$email = '';

if (Request::isPost()) {
    $email = strtolower(Request::post('email'));
    if (!Csrf::verifyRequest()) {
        $error = 'Your session expired. Please try again.';
    } elseif (Request::post('action') === 'admin_request') {
        $user = $email === '' ? null : App::get(UserRepository::class)->findByEmail($email);
        if ($user === null) {
            $error = 'We could not find an account with that email address.';
        } else {
            $service->requestAdminApproval($user->getId(), Request::post('note'));
            $notice = 'Your request has been sent to the administrator. They will contact you on WhatsApp to confirm.';
        }
    } else {
        $service->sendResetLink($email);
        $notice = 'If that email is registered, we have sent a reset link. Check your inbox and spam folder.';
        $email = '';
    }
}

$waUrl = whatsapp_url("Hello WISDOM support, I need help resetting my password.\n\nMy email: {$email}");
$pageTitle = 'Forgot password';
$noIndex = true;
?>
<!doctype html><html lang="en"><head><?php require __DIR__ . '/partials/head.php'; ?>
<style nonce="<?= e(nonce()) ?>">body{min-height:100vh;display:grid;place-items:center;padding:24px;background:linear-gradient(135deg,#eef3ff,#f8fbff)}.fp-card{width:min(100%,480px);padding:36px;background:#fff;border:1px solid #e3e8ef;border-radius:18px;box-shadow:0 18px 50px #1822301a}.fp-card h1{margin:0 0 8px}.lead{color:#667085}.field{margin:14px 0}.field label{display:block;margin-bottom:6px}.field input{width:100%;padding:11px 13px;border:1px solid #d5dce8;border-radius:9px}.btn{display:inline-flex;justify-content:center;width:100%;padding:12px 18px;border:0;border-radius:9px;background:#0d2b45;color:#fff;font-weight:700;cursor:pointer}.btn--gold{background:#c9a227;color:#0d2b45}.alert{padding:11px 14px;border-radius:9px;margin-bottom:16px}.alert--error{color:#9d1c2b;background:#fdeef0}.alert--info{color:#1f5262;background:#e9f2f5}.alt{margin-top:24px;padding-top:20px;border-top:1px solid #e3e0d6}.wa{display:inline-block;margin-top:10px;padding:11px 16px;border-radius:9px;background:#25d366;color:#fff;font-weight:700}</style></head><body><main class="fp-card">
<div class="wordmark">WISDOM</div><h1>Forgot your password?</h1><p class="lead">Enter your registered email and we will send a reset link.</p>
<?php if ($error !== ''): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?><?php if ($notice !== ''): ?><div class="alert alert--info"><?= e($notice) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="action" value="email_reset"><?= csrf_field() ?><div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="<?= e($email) ?>" required autocomplete="email"></div><button class="btn btn--gold" type="submit">Send reset link</button></form>
<div class="alt"><h2>Cannot access your email?</h2><p class="lead">Request administrator help through WhatsApp.</p><form method="post"><input type="hidden" name="action" value="admin_request"><?= csrf_field() ?><div class="field"><label for="request-email">Registered email</label><input id="request-email" name="email" type="email" value="<?= e($email) ?>" required></div><div class="field"><label for="note">Note (optional)</label><input id="note" name="note" maxlength="500"></div><button class="btn" type="submit">Request admin help</button></form><a class="wa" href="<?= e($waUrl) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a></div>
<p style="text-align:center;margin-top:22px"><a href="login.php">Back to sign in</a></p></main></body></html>
