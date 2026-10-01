<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\AppException;
use Wisdom\Core\Csrf;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Services\AvatarService;

$user = Guard::requirePermission('profile.update');
Guard::requirePasswordResetHandled();
$active = 'profile';
$avatarService = App::get(AvatarService::class);
$error = '';
$message = '';

if (Request::isPost()) {
    if (!Csrf::verifyRequest()) {
        $error = 'Your session expired. Please try again.';
    } else {
        try {
            $avatarService->store($user, isset($_FILES['avatar']) && is_array($_FILES['avatar']) ? $_FILES['avatar'] : null);
            $message = 'Profile picture updated.';
            $user = Guard::user() ?? $user;
        } catch (AppException $exception) {
            $error = $exception->getMessage();
        }
    }
}

$pageTitle = 'My profile';
?>
<!doctype html>
<html lang="en">
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<div class="shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>
    <main class="shell__main page-fade">
        <?php require __DIR__ . '/partials/notice.php'; ?>
        <?php require __DIR__ . '/partials/admin_flash.php'; ?>
        <div class="eyebrow">Your account</div>
        <h1>My profile</h1>
        <section class="card profile-card">
            <div class="profile-card__identity">
                <img class="profile-avatar" src="avatar.php?id=<?= (int) $user->getId() ?>&amp;v=<?= e(substr((string) ($user->getAvatar() ?? 'default'), 0, 12)) ?>" alt="Profile picture">
                <div><h2><?= e($user->getName()) ?></h2><p class="text-muted"><?= e($user->getEmail()) ?></p><span class="badge badge--navy badge--plain"><?= e($user->roleLabel()) ?></span></div>
            </div>
            <?php if ($error !== ''): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif; ?>
            <?php if ($message !== ''): ?><p class="alert alert--success" role="status"><?= e($message) ?></p><?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="stack">
                <?= csrf_field() ?>
                <div class="field"><label for="avatar">Profile picture</label><input id="avatar" name="avatar" type="file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" required><small class="text-muted">JPG, PNG, GIF or WebP, maximum 2 MB.</small></div>
                <button class="btn btn--gold" type="submit">Save picture</button>
            </form>
        </section>
        <section class="card mt-6">
            <h2>Registration details</h2>
            <dl class="profile-details"><div><dt>Programme</dt><dd><?= e((string) $user->getLevel()) ?></dd></div><div><dt>Account status</dt><dd><?= $user->isApproved() ? 'Approved' : 'Pending approval' ?></dd></div><div><dt>Subjects</dt><dd><?= e(implode(', ', $user->getSubjects())) ?></dd></div></dl>
            <a class="btn btn--ghost" href="student_details.php">View full registration details</a>
            <?php if (!$user->isStaff()): ?><a class="btn btn--gold" href="edit_details.php">Edit details</a><?php endif; ?>
        </section>
    </main>
</div>
<script src="assets/js/wisdom-ui.js" defer></script>
</body>
</html>