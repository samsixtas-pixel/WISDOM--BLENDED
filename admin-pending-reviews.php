<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\AppException;
use Wisdom\Core\Csrf;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Core\Session;
use Wisdom\Services\ExcelImportService;

if (!(bool) App::config('features.excel_import', false)) {
    render_error_page(404, 'Not available', 'Excel results import is disabled.');
}
$admin = Guard::requirePermission('exam.manage');
Guard::requirePasswordResetHandled();
$service = App::get(ExcelImportService::class);
$active = 'admin-pending-reviews';
$pageTitle = 'Pending import reviews';

if (Request::isPost()) {
    Guard::throttle('admin.action', 60, 60);
    if (!Csrf::verifyRequest()) {
        Session::flash('error', 'Your session expired. Please try again.');
        redirect('admin-pending-reviews.php');
    }
    try {
        $id = Request::intPost('review_id');
        switch (Request::post('form_action')) {
            case 'approve_review':
                $service->approvePending($admin, $id, Request::intPost('user_id'));
                Session::flash('success', 'Import row matched and saved as an exam record.');
                break;
            case 'reject_review':
                $service->rejectPending($admin, $id);
                Session::flash('success', 'Import row rejected.');
                break;
            default:
                throw new AppException('Unknown action.');
        }
    } catch (AppException $exception) {
        Session::flash('error', $exception->getMessage());
    }
    redirect('admin-pending-reviews.php');
}

$rows = $service->pendingReviews(200);
?>
<!doctype html>
<html lang="en">
<head><?php require __DIR__ . '/partials/head.php'; ?></head>
<body>
<div class="shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>
    <main class="shell__main page-fade">
        <?php require __DIR__ . '/partials/notice.php'; ?>
        <?php require __DIR__ . '/partials/admin_flash.php'; ?>
        <header style="margin-bottom:var(--s-8)"><div class="eyebrow">Examinations</div><h1 style="margin:6px 0 4px">Pending import reviews</h1><p class="text-muted">Match each flagged spreadsheet row to the correct student, or reject it.</p></header>
        <?php if ($rows === []): ?><section class="card"><p class="text-muted">No import rows are waiting for review.</p></section><?php else: ?>
            <div class="stack">
            <?php foreach ($rows as $row): $raw = json_decode((string) $row['raw_data'], true); $raw = is_array($raw) ? $raw : []; $suggestedId = (int) ($row['suggested_user_id'] ?? 0); ?>
                <article class="card">
                    <div class="row row--between" style="align-items:flex-start;gap:var(--s-5);flex-wrap:wrap">
                        <div style="flex:1;min-width:260px">
                            <div class="eyebrow">Row <?= (int) $row['row_number'] ?><?php if (!empty($row['original_filename'])): ?> · <?= e((string) $row['original_filename']) ?><?php endif; ?></div>
                            <h2 style="margin:6px 0"><?= e((string) ($raw['subject_name'] ?? 'Unknown subject')) ?> <small><?= e((string) ($raw['exam_number'] ?? '')) ?></small></h2>
                            <p class="alert alert--warn"><strong>Reason:</strong> <?= e((string) $row['reason']) ?></p>
                            <dl class="profile-details">
                                <?php foreach (['student_name' => 'Student', 'email' => 'Email', 'student_id' => 'Student ID', 'subject_code' => 'Subject code', 'weight' => 'Weight', 'result' => 'Result', 'grade' => 'Grade', 'status' => 'Status'] as $key => $label): if (!isset($raw[$key]) || $raw[$key] === '') continue; ?>
                                <div><dt><?= e($label) ?></dt><dd><?= e((string) $raw[$key]) ?></dd></div>
                                <?php endforeach; ?>
                            </dl>
                            <?php if (!empty($row['suggested_name'])): ?><p class="text-muted">Suggested match: <strong><?= e((string) $row['suggested_name']) ?></strong></p><?php endif; ?>
                        </div>
                        <div style="min-width:min(100%,280px);flex:0 1 340px">
                            <form method="post" class="stack">
                                <?= csrf_field() ?><input type="hidden" name="form_action" value="approve_review"><input type="hidden" name="review_id" value="<?= (int) $row['id'] ?>">
                                <label for="student-<?= (int) $row['id'] ?>">Match to student</label>
                                <div class="ac-wrap" id="review-ac-<?= (int) $row['id'] ?>" data-endpoint="admin_suggest.php" data-extra='{"type":"student"}'>
                                    <input id="student-<?= (int) $row['id'] ?>" class="ac-input" type="text" placeholder="Type student name" autocomplete="off" value="<?= e((string) ($row['suggested_name'] ?? '')) ?>">
                                    <input type="hidden" name="user_id" value="<?= $suggestedId ?>">
                                    <button type="button" class="ac-clear" aria-label="Clear student">×</button><ul class="ac-list" role="listbox" aria-label="Student suggestions"></ul>
                                </div>
                                <button class="btn btn--gold" type="submit">Approve and save</button>
                            </form>
                            <form method="post" style="margin-top:var(--s-3)" data-confirm-modal data-modal-title="Reject this row?" data-modal-body="The row will not be added to an exam record." data-modal-confirm="Reject" data-modal-danger="1">
                                <?= csrf_field() ?><input type="hidden" name="form_action" value="reject_review"><input type="hidden" name="review_id" value="<?= (int) $row['id'] ?>"><button class="btn btn--danger btn--sm" type="submit">Reject row</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>
<script src="assets/js/wisdom-autocomplete.js" nonce="<?= e(nonce()) ?>" defer></script>
<script src="assets/js/wisdom-ui.js" nonce="<?= e(nonce()) ?>" defer></script>
<script nonce="<?= e(nonce()) ?>">
document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[id^="review-ac-"]').forEach(wrap => {
    const hidden = wrap.querySelector('input[type="hidden"]');
    wrap.addEventListener('ac:selected', event => { hidden.value = event.detail.value; });
    wrap.addEventListener('ac:cleared', () => { hidden.value = ''; });
}));
</script>
</body>
</html>
