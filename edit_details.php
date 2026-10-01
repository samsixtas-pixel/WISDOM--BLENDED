<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\AppException;
use Wisdom\Core\Csrf;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Repositories\SubjectRepository;
use Wisdom\Services\UserService;

$user = Guard::requireLogin();
Guard::requirePasswordResetHandled();
if ($user->isStaff()) {
    redirect('profile.php');
}
$catalogue = App::get(SubjectRepository::class)->forLevel((string) $user->getLevel());
$error = '';
$saved = false;
if (Request::isPost()) {
    if (!Csrf::verifyRequest()) {
        $error = 'Your session expired. Please try again.';
    } else {
        try {
            App::get(UserService::class)->updateOwnProfile($user, Request::post('name'), Request::post('sex'), is_array($_POST['subjects'] ?? null) ? $_POST['subjects'] : []);
            $saved = true;
            $user = App::get(\Wisdom\Repositories\UserRepository::class)->find($user->getId()) ?? $user;
        } catch (AppException $exception) {
            $error = $exception->getMessage();
        }
    }
}
$pageTitle = 'Edit registration details';
?>
<!doctype html><html lang="en"><head><?php require __DIR__ . '/partials/head.php'; ?></head><body><div class="shell"><?php require __DIR__ . '/partials/nav.php'; ?><main class="shell__main page-fade"><section class="card" style="max-width:720px"><div class="eyebrow">Registration</div><h1>Edit details</h1><p class="text-muted">Your email and programme cannot be changed here.</p><?php if ($error !== ''): ?><p class="alert alert--error"><?= e($error) ?></p><?php endif; ?><?php if ($saved): ?><p class="alert alert--success">Your details were updated.</p><?php endif; ?><form method="post" class="stack"><?= csrf_field() ?><div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?= e($user->getName()) ?>" required maxlength="255"></div><div class="field"><label for="sex">Sex</label><select id="sex" name="sex" required><?php foreach (\Wisdom\Models\User::SEXES as $sex): ?><option value="<?= e($sex) ?>" <?= $user->getSex() === $sex ? 'selected' : '' ?>><?= e(ucfirst($sex)) ?></option><?php endforeach; ?></select></div><div class="field"><label>Programme</label><input value="<?= e((string) $user->getLevel()) ?>" readonly></div><fieldset class="subjects-fieldset"><legend>Subjects</legend><div class="subjects-list"><?php foreach ($catalogue as $subject): ?><label class="subject-option"><input type="checkbox" name="subjects[]" value="<?= e($subject) ?>" <?= in_array($subject, $user->getSubjects(), true) ? 'checked' : '' ?>><?= e($subject) ?></label><?php endforeach; ?></div></fieldset><button class="btn btn--gold" type="submit">Save details</button></form></section></main></div><script src="assets/js/wisdom-ui.js" defer></script></body></html>