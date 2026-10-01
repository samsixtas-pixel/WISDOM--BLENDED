<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\Guard;

$user = Guard::requireLogin();
Guard::requirePasswordResetHandled();
$active = 'profile';
$pageTitle = 'Registration details';
?>
<!doctype html>
<html lang="en">
    <head>
        <?php require __DIR__ . '/partials/head.php'; ?>
        <style>
            .details-card{max-width:720px}
            dt{color:var(--ink-500)}
            dd{margin:0 0 18px;font-weight:650}
            li{margin:6px 0}
        </style>
    </head>
    <body>
    <div class="shell">
        <?php require __DIR__ . '/partials/nav.php'; ?>
        <main class="shell__main page-fade">
            <?php require __DIR__ . '/partials/notice.php'; ?>
            <section class="card details-card">
            <p><a href="dashboard.php">&larr; Dashboard</a></p>
            <div class="row row--between" style="align-items:flex-end;gap:var(--s-4);margin-bottom:var(--s-4);flex-wrap:wrap">
                <h1 style="margin:0">Student details</h1>
                <?php if (!$user->isStaff()): ?><a href="edit_details.php" class="btn btn--gold btn--sm">Edit details</a><?php endif; ?>
            </div>
            <dl>
                <dt>Full name</dt>
                <dd><?= e($user->getName()) ?></dd>
                <dt>Sex</dt>
                <dd><?= e(ucfirst((string) $user->getSex())) ?></dd>
                <dt>Programme</dt>
                <dd><?= e((string) $user->getLevel()) ?></dd>
                <dt>Account status</dt>
                <dd><?= $user->isApproved() ? 'Approved' : 'Pending approval' ?></dd>
                <dt>Subjects</dt>
                <dd><ul><?php foreach ($user->getSubjects() as $subject): ?><li><?= e($subject) ?></li><?php endforeach; ?></ul></dd>
            </dl>
            </section>
        </main>
    </div>
    <script src="assets/js/wisdom-ui.js" defer></script>
    </body>
</html>
