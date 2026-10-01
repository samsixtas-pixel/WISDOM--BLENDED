<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\AppException;
use Wisdom\Core\Csrf;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Core\Session;
use Wisdom\Models\Payment;
use Wisdom\Services\FeeService;
use Wisdom\Services\SettingService;

$user = Guard::requirePermission('payment.submit');
$active = 'fees';
$fees = App::get(FeeService::class);
$settings = App::get(SettingService::class);
$totalFees = $fees->totalFor($user);
$halfFees = $fees->halfFor($user);
$fullyPaid = $fees->isFullyPaid($user);
$error = '';

if (Request::isPost()) {
    Guard::throttle('payment.submit', 4, 600);
    if (!Csrf::verifyRequest()) {
        $error = 'Your session expired. Please try again.';
    } elseif (!Csrf::consumeToken('payment.submit', $_POST['csrf_once'] ?? null)) {
        $error = 'This form has expired or was already submitted. Reload and try again.';
    } else {
        try {
            $fees->submit($user, Request::post('category'), Request::post('payment_type'), isset($_FILES['proof']) && is_array($_FILES['proof']) ? $_FILES['proof'] : null);
            Session::flash('payment_success', 'Payment proof submitted for review.');
            redirect('fees.php');
        } catch (AppException $exception) {
            $error = $exception->getMessage();
        }
    }
}

$successMessage = Session::pullFlash('payment_success');
$payments = $fees->historyFor($user);
$subjects = $user->getSubjects();
$pageTitle = 'Fees';
?>
<!doctype html>
<html lang="en">
<head><?php require __DIR__ . '/partials/head.php'; ?></head>
<body>
<div class="shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>
    <main class="shell__main page-fade">
        <?php require __DIR__ . '/partials/notice.php'; ?>
        <?php if ($successMessage !== ''): ?><div id="js-success-modal" hidden data-title="Payment submitted" data-body="Your payment proof is queued for review. You can follow its status in your submission history." data-confirm="Got it"></div><?php endif; ?>
        <div class="eyebrow">Account billing</div><h1>Fees</h1>
        <p class="text-muted"><?= e((string) $user->getLevel()) ?> · <?= count($subjects) ?> selected subject<?= count($subjects) === 1 ? '' : 's' ?>.</p>
        <?php if ($error !== ''): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <section class="stat-grid" aria-label="Fee summary">
            <article class="stat"><p class="stat__label">Programme fee total</p><div class="stat__value"><?= e(money($totalFees)) ?></div><div class="stat__delta">Calculated from your selected subjects</div></article>
            <article class="stat"><p class="stat__label">Half payment</p><div class="stat__value"><?= e(money($halfFees)) ?></div><div class="stat__delta">Available payment option</div></article>
            <article class="stat"><p class="stat__label">Per subject</p><div class="stat__value"><?= e(money($fees->rateFor($user))) ?></div><div class="stat__delta"><?= count($subjects) ?> subject<?= count($subjects) === 1 ? '' : 's' ?> selected</div></article>
        </section>
        <?php if ($settings->bankNumber() !== '' || $settings->lipaNumber() !== ''): ?>
        <section class="card card--paper mb-6"><div class="eyebrow">Payment details</div><h2>Where to pay</h2><p class="text-muted">Use one of the available payment options, then submit your proof below.</p>
            <?php foreach ([['Bank account', 'bank-number', $settings->bankNumber()], ['Lipa number', 'lipa-number', $settings->lipaNumber()]] as [$label, $id, $value]): if ($value === '') continue; ?>
            <div class="payment-method"><div><div class="field"><label for="<?= e($id) ?>"><?= e($label) ?></label></div><code class="copy-value" id="<?= e($id) ?>"><?= e($value) ?></code></div><button class="btn btn--ghost" type="button" data-copy="<?= e($id) ?>">Copy</button></div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
        <?php if (!$fullyPaid): ?><section class="card mb-6"><div class="eyebrow">Submit a payment</div><h2>Upload payment proof</h2><form method="post" enctype="multipart/form-data" class="fee-form">
            <?= csrf_field() ?>
            <?= csrf_once_field('payment.submit') ?>
            <div class="field"><label for="category">Fee category</label><select id="category" name="category" required><?php foreach (Payment::CATEGORIES as $option): ?><option value="<?= e($option) ?>"><?= e(ucfirst($option)) ?> fees</option><?php endforeach; ?></select></div>
            <div class="field"><label for="payment_type">Payment amount</label><select id="payment_type" name="payment_type" required><option value="">Choose amount</option><option value="half">Half payment — <?= e(money($halfFees)) ?></option><option value="full">Full payment — <?= e(money($totalFees)) ?></option></select></div>
            <div class="field"><label for="proof">Payment proof</label><input id="proof" name="proof" type="file" accept="image/jpeg,image/png,application/pdf" required><small class="text-muted">Accepted formats and size are validated securely on upload.</small></div>
            <button class="btn btn--gold" type="submit">Submit proof for review</button>
        </form></section><?php else: ?><section class="card mb-6" style="border-left:5px solid var(--success);background:var(--success-soft)"><div class="row gap-4" style="align-items:center;flex-wrap:wrap"><span class="clay clay--teal clay--md" aria-hidden="true" style="flex:0 0 auto"><svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 12 10 18 20 6"/></svg></span><div style="flex:1;min-width:200px"><div class="eyebrow" style="color:var(--success)">All fees settled</div><h2 style="margin:6px 0 4px;color:var(--navy-800)">Your account is fully paid</h2><p style="margin:0;color:var(--ink-700);font-size:14px">Both your programme and examination payments have been approved. There is nothing left to submit.</p></div></div></section><?php endif; ?>
        <section class="card"><div class="card__head"><div><div class="eyebrow">Your records</div><h2 class="card__title">Submission history</h2></div></div>
            <?php if ($payments === []): ?><p class="text-muted mb-0">You have not submitted a payment yet.</p><?php else: ?><div class="table-wrap"><table class="table"><thead><tr><th>Submitted</th><th>Category</th><th>Type</th><th>Amount</th><th>Status</th></tr></thead><tbody><?php foreach ($payments as $payment): ?><tr><td><?= e((string) $payment['submitted_at']) ?></td><td><?= e(ucfirst((string) $payment['category'])) ?></td><td><?= e(ucfirst((string) $payment['payment_type'])) ?></td><td><?= e(money((int) $payment['amount'])) ?></td><td><span class="badge <?= $payment['status'] === 'approved' ? 'badge--on' : ($payment['status'] === 'rejected' ? 'badge--off' : 'badge--gold') ?>"><?= e(ucfirst((string) $payment['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </section>
    </main>
</div>
<script src="assets/js/wisdom-ui.js" defer></script>
</body>
</html>
