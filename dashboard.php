<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Guard;
use Wisdom\Services\FeeService;
use Wisdom\Services\LiveClassService;

$user = Guard::requireLogin();
Guard::requirePasswordResetHandled();
if ($user->isSecretary()) {
    redirect('admin.php');
}
$active = 'dashboard';
$fees = App::get(FeeService::class);
$totalFees = $fees->totalFor($user);
$fullyPaid = $fees->isFullyPaid($user);
$subjects = $user->getSubjects();
$upcomingClasses = $user->can('admin.access') ? [] : array_slice(App::get(LiveClassService::class)->forStudent($user), 0, 3);
$createdAt = strtotime($user->getCreatedAt());
$memberSince = $createdAt === false ? 'Recently' : date('M Y', $createdAt);
$pageTitle = 'Dashboard';
$pageDesc = 'Your WISDOM learner workspace.';
?>
<!doctype html>
<html lang="en">
<head><?php require __DIR__ . '/partials/head.php'; ?></head>
<body>
<div class="shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>
    <main class="shell__main page-fade">
        <?php require __DIR__ . '/partials/notice.php'; ?>
        <header style="margin-bottom:var(--s-8)">
            <div class="row gap-4" style="align-items:center;flex-wrap:wrap">
                <img class="avatar avatar--hero" src="avatar.php?id=<?= (int) $user->getId() ?>&v=<?= e(substr((string) ($user->getAvatar() ?? 'default'), 0, 12)) ?>" alt="" width="64" height="64" loading="eager" decoding="async" fetchpriority="high">
                <div style="min-width:0;flex:1"><div class="eyebrow">Your workspace</div><h1 style="margin:6px 0 4px">Welcome back, <?= e(explode(' ', $user->getName())[0]) ?>.</h1><p class="text-muted mb-0">Here is a summary of your learning account.</p></div>
                <?php if (!$fullyPaid): ?><a class="btn btn--gold" href="fees.php">Submit a payment</a><?php endif; ?>
            </div>
        </header>
        <section class="stat-grid" aria-label="Account summary">
            <article class="stat"><p class="stat__label">Account status</p><div class="stat__value" style="font-size:1.55rem"><?= $user->isApproved() ? 'Approved' : 'Pending' ?></div><div class="stat__delta <?= $user->isApproved() ? 'stat__delta--up' : '' ?>"><?= $user->isApproved() ? 'Account approved' : 'Awaiting fee verification' ?></div></article>
            <article class="stat"><p class="stat__label">Programme</p><div class="stat__value"><?= e((string) $user->getLevel()) ?></div><div class="stat__delta"><?= count($subjects) ?> selected subject<?= count($subjects) === 1 ? '' : 's' ?></div></article>
            <article class="stat"><p class="stat__label">Member since</p><div class="stat__value" style="font-size:1.7rem"><?= e($memberSince) ?></div><div class="stat__delta">Programme fee total: <strong><?= e(money($totalFees)) ?></strong></div></article>
        </section>
        <div class="grid-2--asym-rev">
            <section class="card">
                <div class="card__head"><div><div class="eyebrow">Your profile</div><h2 class="card__title" style="margin-top:6px">Registration details</h2></div><a class="btn btn--ghost btn--sm" href="student_details.php">View details</a></div>
                <dl class="profile-details"><div><dt>Full name</dt><dd><?= e($user->getName()) ?></dd></div><div><dt>Sex</dt><dd><?= e(ucfirst((string) $user->getSex())) ?></dd></div><div><dt>Programme</dt><dd><?= e((string) $user->getLevel()) ?></dd></div><div><dt>Selected subjects</dt><dd><?php if ($subjects === []): ?>None selected<?php else: ?><div class="row flex-wrap gap-2"><?php foreach ($subjects as $subject): ?><span class="badge badge--navy badge--plain"><?= e($subject) ?></span><?php endforeach; ?></div><?php endif; ?></dd></div></dl>
                <?php if (!$user->isStaff()): ?><a href="edit_details.php" class="btn btn--gold btn--sm">Edit registration details</a><?php endif; ?>
                <a href="profile.php" class="btn btn--ghost">Manage profile picture</a>
            </section>
            <aside class="stack">
                <section class="card card--paper"><div class="eyebrow">Live learning</div><h2 style="margin:6px 0">Next live class</h2>
                    <?php if ($upcomingClasses === []): ?><p class="text-muted">No upcoming sessions are currently listed for your subjects.</p><?php else: $next = $upcomingClasses[0]; ?>
                        <h3><?= e($next->getSubjectName()) ?></h3><p class="text-muted"><?= e($next->getLevel()) ?> · <?= e(date('D, j M Y · H:i', strtotime($next->getScheduledAt()))) ?></p><a href="<?= e($next->getLink()) ?>" target="_blank" rel="noopener noreferrer" class="btn btn--gold btn--sm">Join session</a>
                    <?php endif; ?>
                    <a href="classes.php" class="btn btn--ghost btn--sm" style="margin-top:var(--s-3)">View live classes</a>
                </section>
                <section class="card"><div class="eyebrow">Quick actions</div><div class="stack" style="margin-top:var(--s-3)"><a href="fees.php" class="btn btn--ghost btn--block">Fees and payment history</a><a href="exams.php" class="btn btn--ghost btn--block">View examination results</a><?php if ($user->can('admin.access')): ?><a href="admin.php" class="btn btn--ghost btn--block">Open admin workspace</a><?php endif; ?></div></section>
            </aside>
        </div>
    </main>
</div>
<script src="assets/js/wisdom-ui.js" defer></script>
</body>
</html>
