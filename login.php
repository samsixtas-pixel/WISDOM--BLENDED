<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Csrf;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Services\AuthService;

if (Request::isPost()) {
    Guard::throttle('login', 10, 60);
}

if (Guard::user() !== null) {
    $currentUser = Guard::user();
    redirect($currentUser !== null && $currentUser->isSecretary() ? 'admin.php' : 'dashboard.php');
}

$auth = App::get(AuthService::class);
$pageTitle = 'Sign in';
$error = '';
$email = '';

if (Request::isPost()) {
    $email = Request::post('email');
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    if (!Csrf::verifyRequest()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $validator = $auth->loginValidator(['email' => $email, 'password' => $password]);
        if (!$validator->passes()) {
            $error = 'Enter a valid email and password.';
        } else {
            try {
                $user = $auth->attempt($email, $password);
                $auth->login($user);
                if ($user->mustResetPassword()) {
                    redirect('set_password.php');
                }
                redirect($user->isSecretary() ? 'admin.php' : 'dashboard.php');
            } catch (\Wisdom\Core\AppException $exception) {
                $error = $exception->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
<style>/*
:root{--ink:#172033;--muted:#667085;--primary:#315efb;--line:#d5dce8}*{box-sizing:border-box}body{min-height:100vh;margin:0;display:grid;place-items:center;padding:24px;color:var(--ink);background:linear-gradient(135deg,#eef3ff,#f8fbff);font:16px/1.5 system-ui,sans-serif}main{width:min(100%,430px);padding:36px;border:1px solid #e3e8ef;border-radius:18px;background:#fff;box-shadow:0 18px 50px rgb(24 34 48 / 10%)}.brand{margin:0 0 28px;color:var(--primary);font-size:1rem;letter-spacing:.18em}h1{margin:0 0 8px;font-size:1.9rem}p{margin:0 0 26px;color:var(--muted)}label{display:block;margin:17px 0 7px;font-weight:650}input{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:9px;font:inherit}input:focus{outline:3px solid rgb(49 94 251 / 18%);border-color:var(--primary)}button{width:100%;margin-top:24px;padding:13px;border:0;border-radius:9px;color:#fff;background:var(--primary);font:inherit;font-weight:700;cursor:pointer}.error{margin:0 0 18px;padding:11px 13px;border-radius:8px;color:#9d1c2b;background:#fff0f1}.link{margin-top:22px;text-align:center}a{color:var(--primary);font-weight:650;text-decoration:none}
*/</style>
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="wordmark">WISDOM</div>
        <h1>Welcome back</h1>
        <p>Sign in to continue to your account.</p>
<?php if ($error !== ''): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (isset($_GET['logged_out'])): ?><p class="alert alert--info" role="status">You have been signed out.</p><?php endif; ?>
<?php if (isset($_GET['registered'])): ?><p class="alert alert--success" role="status">Account created. You can sign in now.</p><?php endif; ?>
<form method="post" action="login.php" novalidate>
    <?= csrf_field() ?>
    <label for="email">Email</label>
    <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="username" required>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button type="submit">Sign in</button>
</form>
<div class="auth-links"><a href="forgot_password.php">Forgot your password?</a></div>
<div class="auth-links">New to WISDOM? <a href="register.php">Create an account</a></div>
</main>
</body>
</html>
