<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Repositories\ExamRepository;
use Wisdom\Repositories\PaymentRepository;
use Wisdom\Repositories\UserRepository;
use Wisdom\Services\PasswordResetService;

$admin = Guard::requirePermission('admin.access');
Guard::requirePasswordResetHandled();
if (Request::isPost()) {
    http_response_code(405);
    header('Allow: GET');
    render_error_page(405, 'Method not allowed', 'The dashboard is view-only.', 'Open a dedicated administration page to submit changes.');
}

$active = 'admin';
$pageTitle = $admin->isSecretary() ? 'Examinations desk' : 'Dashboard';
$pageDesc = 'WISDOM administration.';
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$today = date('D, j M Y');

if ($admin->isSecretary()) {
    $examTotal = App::get(ExamRepository::class)->count();
    ?>
    <!doctype html><html lang="en"><head><?php require __DIR__ . '/partials/head.php'; ?></head><body>
    <div class="shell">
        <?php require __DIR__ . '/partials/nav.php'; ?>
        <main class="shell__main page-fade">
            <?php require __DIR__ . '/partials/notice.php'; ?>
            <?php require __DIR__ . '/partials/admin_flash.php'; ?>
            <header class="adm-header"><div><div class="eyebrow">Examinations desk</div><h1><?= e($greeting) ?>, <?= e(explode(' ', $admin->getName())[0]) ?>.</h1><p class="text-muted">Enter and manage examination records for enrolled students.</p></div><span class="adm-header__date"><?= e($today) ?></span></header>
            <section class="stat-grid" aria-label="Summary"><article class="stat"><p class="stat__label">Exam records</p><div class="stat__value"><?= (int) $examTotal ?></div><div class="stat__delta">Total records on file</div></article></section>
            <section class="card"><div class="eyebrow">Quick access</div><h2 class="card__title">Examination desk</h2><div class="grid-2"><a href="admin-exams.php" class="card card--paper"><h3>Enter and view results</h3><p class="text-muted">Add a result or browse the examination records.</p></a></div></section>
        </main>
    </div><script src="assets/js/wisdom-ui.js" nonce="<?= e(nonce()) ?>" defer></script></body></html>
    <?php
    exit;
}

$userRepository = App::get(UserRepository::class);
$paymentRepository = App::get(PaymentRepository::class);
$examRepository = App::get(ExamRepository::class);
$passwordReset = App::get(PasswordResetService::class);
$pendingPayments = $paymentRepository->countPending();
$totalUsers = $userRepository->count();
$totalExams = $examRepository->count();
$pendingResets = $passwordReset->pendingRequestCount();
$recentPayments = $paymentRepository->allWithPayer(5);
$recentExams = $examRepository->allWithStudent(5);
?>
<!doctype html><html lang="en"><head><?php require __DIR__ . '/partials/head.php'; ?>
<style nonce="<?= e(nonce()) ?>">
.adm-header{display:flex;align-items:flex-end;justify-content:space-between;gap:var(--s-6);flex-wrap:wrap;margin-bottom:var(--s-8)}
.adm-header__date{display:inline-flex;align-items:center;padding:8px 14px;border-radius:var(--r-pill);background:var(--cream-100);border:1px solid var(--line);font-size:var(--text-sm);color:var(--ink-700)}
.adm-quick-grid{display:grid;grid-template-columns:1fr;gap:var(--s-4)}
@media(min-width:700px){.adm-quick-grid{grid-template-columns:repeat(2,1fr)}}
@media(min-width:1100px){.adm-quick-grid{grid-template-columns:repeat(4,1fr)}}
.adm-card-link{display:block;padding:var(--s-5);border:1px solid var(--line);border-radius:var(--r-lg);background:var(--paper);box-shadow:var(--shadow-1);text-decoration:none;transition:transform 200ms,box-shadow 220ms var(--ease)}
.adm-card-link:hover{transform:translateY(-3px);box-shadow:var(--shadow-2)}
.adm-card-link h3{margin:6px 0 4px;color:var(--navy-800);font-size:var(--text-lg)}
.adm-card-link p{margin:0;color:var(--ink-500);font-size:var(--text-sm)}
.adm-recent-list{list-style:none;padding:0;margin:0}.adm-recent-list li{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--line);font-size:14px}.adm-recent-list li:last-child{border:0}.adm-recent-list strong{max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--navy-800)}.adm-recent-list small{color:var(--ink-500);white-space:nowrap}
</style></head><body>
<div class="shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>
    <main class="shell__main page-fade">
        <?php require __DIR__ . '/partials/notice.php'; ?>
        <?php require __DIR__ . '/partials/admin_flash.php'; ?>
        <header class="adm-header"><div><div class="eyebrow">Command centre</div><h1><?= e($greeting) ?>, <?= e(explode(' ', $admin->getName())[0]) ?>.</h1><p class="text-muted">Everything on the platform at a glance.</p></div><span class="adm-header__date"><?= e($today) ?></span></header>
        <section class="stat-grid" aria-label="Platform summary">
            <article class="stat"><p class="stat__label">Pending payments</p><div class="stat__value"><?= (int) $pendingPayments ?></div><div class="stat__delta"><a href="admin-payments.php?f_status=pending">Open queue</a></div></article>
            <article class="stat"><p class="stat__label">Registered users</p><div class="stat__value"><?= (int) $totalUsers ?></div><div class="stat__delta"><a href="admin-users.php">Manage directory</a></div></article>
            <article class="stat"><p class="stat__label">Exam records</p><div class="stat__value"><?= (int) $totalExams ?></div><div class="stat__delta"><a href="admin-exams.php">Open examinations</a></div></article>
        </section>
        <?php if ($pendingResets > 0): ?><section class="card" style="border-left:5px solid var(--gold-600);margin-bottom:var(--s-8)"><div class="row row--between" style="align-items:center;flex-wrap:wrap"><div><div class="eyebrow">Security</div><h2><?= (int) $pendingResets ?> password reset request<?= $pendingResets === 1 ? '' : 's' ?> awaiting review</h2><p class="text-muted">Approve to generate a temporary password.</p></div><a href="admin-password-resets.php" class="btn btn--gold">Review</a></div></section><?php endif; ?>
        <section style="margin-bottom:var(--s-8)"><div class="eyebrow">Jump to</div><div class="adm-quick-grid">
            <a href="admin-payments.php" class="adm-card-link"><div class="eyebrow">Tasks</div><h3>Fees verification</h3><p>Review payment proofs and decisions.</p></a>
            <a href="admin-exams.php" class="adm-card-link"><div class="eyebrow">Examinations</div><h3>Enter results</h3><p>Search students, add records, and browse results.</p></a>
            <a href="admin-users.php" class="adm-card-link"><div class="eyebrow">People</div><h3>All users</h3><p>Filter and manage accounts.</p></a>
            <a href="admin-add-user.php" class="adm-card-link"><div class="eyebrow">People</div><h3>Add user</h3><p>Create an administrator or secretary account.</p></a>
        </div></section>
        <div class="grid-2">
            <section class="card"><div class="card__head"><h2 class="card__title">Recent payments</h2><a href="admin-payments.php" class="btn btn--ghost btn--sm">View all</a></div><?php if ($recentPayments === []): ?><p class="text-muted">No payments submitted yet.</p><?php else: ?><ul class="adm-recent-list"><?php foreach ($recentPayments as $payment): ?><li><strong><?= e((string) $payment['name']) ?></strong><small><?= e(money((int) $payment['amount'])) ?> · <?= e(ucfirst((string) $payment['status'])) ?></small></li><?php endforeach; ?></ul><?php endif; ?></section>
            <section class="card"><div class="card__head"><h2 class="card__title">Recent exam records</h2><a href="admin-exams.php" class="btn btn--ghost btn--sm">View all</a></div><?php if ($recentExams === []): ?><p class="text-muted">No exam records yet.</p><?php else: ?><ul class="adm-recent-list"><?php foreach ($recentExams as $exam): ?><li><strong><?= e((string) $exam['name']) ?></strong><small><?= e((string) $exam['subject_name']) ?></small></li><?php endforeach; ?></ul><?php endif; ?></section>
        </div>
    </main>
</div>
<script src="assets/js/wisdom-ui.js" nonce="<?= e(nonce()) ?>" defer></script>
</body></html>
