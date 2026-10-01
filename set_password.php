<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Csrf;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Core\Session;
use Wisdom\Core\Validator;
use Wisdom\Repositories\UserRepository;

$user = Guard::requireLogin();
if (!$user->mustResetPassword()) {
    redirect($user->isStaff() ? 'admin.php' : 'dashboard.php');
}
$error = '';
$done = false;
if (Request::isPost()) {
    if (!Csrf::verifyRequest()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        $validator = (new Validator(['password' => $password, 'password_confirmation' => $confirmation]))
            ->required('password', 'Password')
            ->password('password', (int) App::config('security.password_min_length', 8))
            ->matches('password_confirmation', 'password', 'Passwords do not match.');
        if (!$validator->passes()) {
            $errors = $validator->errors();
            $error = (string) reset($errors);
        } else {
            App::get(UserRepository::class)->setPasswordAndClearReset($user->getId(), password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));
            Session::regenerate();
            $done = true;
        }
    }
}
$pageTitle = 'Choose a new password';
$noIndex = true;
?>
<!doctype html><html lang="en"><head><?php require __DIR__ . '/partials/head.php'; ?><style nonce="<?= e(nonce()) ?>">body{min-height:100vh;display:grid;place-items:center;padding:24px;background:linear-gradient(135deg,#eef3ff,#f8fbff)}.card{width:min(100%,460px);padding:36px;background:#fff;border:1px solid #e3e8ef;border-radius:18px;box-shadow:0 18px 50px #1822301a}.field{margin:14px 0}.field label{display:block;margin-bottom:6px}.field input{width:100%;padding:11px 13px;border:1px solid #d5dce8;border-radius:9px}.btn{display:inline-flex;justify-content:center;width:100%;margin-top:8px;padding:12px 18px;border:0;border-radius:9px;background:#c9a227;color:#0d2b45;font-weight:700;cursor:pointer}.alert{padding:11px 14px;border-radius:9px;margin-bottom:16px}.alert--error{color:#9d1c2b;background:#fdeef0}.alert--success{color:#176b3a;background:#e9f8ef}</style></head><body><main class="card"><?php if ($done): ?><div class="alert alert--success">Your password has been updated.</div><a class="btn" href="<?= $user->isStaff() ? 'admin.php' : 'dashboard.php' ?>">Continue to your account</a><?php else: ?><div class="wordmark">WISDOM</div><h1>Choose a new password</h1><p>Welcome back, <?= e($user->getName()) ?>. Replace your temporary password to continue.</p><?php if ($error !== ''): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?><form method="post"><?= csrf_field() ?><div class="field"><label for="password">New password</label><input id="password" name="password" type="password" minlength="8" required autocomplete="new-password"></div><div class="field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password"></div><button class="btn" type="submit">Set new password</button></form><?php endif; ?></main></body></html>
