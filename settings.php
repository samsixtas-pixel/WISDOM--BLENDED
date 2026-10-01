<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Csrf;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Core\Validator;
use Wisdom\Repositories\UserRepository;

$user = Guard::requireLogin();
$active = 'settings';
$pageTitle = 'Settings';
$users = App::get(UserRepository::class);
$error = '';
$message = '';

if (Request::isPost()) {
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirmation'] ?? '');

    if (!Csrf::verifyRequest()) {
        $error = 'Your session expired. Please try again.';
    } elseif (!$user->verifyPassword($current)) {
        $error = 'Your current password is incorrect.';
    } else {
        $validator = (new Validator(['password' => $new, 'password_confirmation' => $confirm]))
            ->password('password', 8)
            ->matches('password_confirmation', 'password', 'New passwords do not match.');
        if (!$validator->passes()) {
            $fieldErrors = $validator->errors();
            $error = (string) reset($fieldErrors);
        } else {
            $users->updatePasswordHash($user->getId(), password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]));
            $message = 'Password updated.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><?php require __DIR__ . '/partials/head.php'; ?></head>
<body>
<div class="shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>
    <main class="shell__main page-fade">
        <?php require __DIR__ . '/partials/notice.php'; ?>
        <div class="eyebrow">Account security</div><h1>Settings</h1><section class="card" style="max-width:560px"><h2>Change password</h2>
    <?php if ($error !== ''): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($message !== ''): ?><p class="alert alert--success" role="status"><?= e($message) ?></p><?php endif; ?>
<form method="post" novalidate>
<?= csrf_field() ?>
<label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
<label for="new_password">New password</label><input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
<label for="new_password_confirmation">Confirm new password</label><input id="new_password_confirmation" name="new_password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
<button type="submit">Update password</button>
</form></section>
</main></div><script src="assets/js/wisdom-ui.js" defer></script></body></html>
