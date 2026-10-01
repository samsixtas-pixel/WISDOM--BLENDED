<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
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
$active = 'admin-excel-import';
$pageTitle = 'Import results';
$pageDesc = 'Import examination results from an Excel workbook.';

if (Request::get('template') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="WISDOM-results-template.csv"');
    echo "Exam Number,Subject Code,Subject Name,Weight,Result,Grade,Status,Email,Student ID,Student Name\r\n";
    echo "EX-001,PMS-101,Procurement Principles,100,78,A,published,student@example.com,,Student Name\r\n";
    exit;
}

if (Request::isPost()) {
    Guard::throttle('admin.import', 10, 300);
    if (!Csrf::verifyRequest()) {
        Session::flash('error', 'Your session expired. Please try again.');
        redirect('admin-excel-import.php');
    }
    $file = $_FILES['workbook'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        Session::flash('error', 'Choose an .xlsx workbook to upload.');
        redirect('admin-excel-import.php');
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
        Session::flash('error', 'The workbook upload failed or exceeds the 8 MB limit.');
        redirect('admin-excel-import.php');
    }
    $tmp = $file['tmp_name'] ?? '';
    if (!is_string($tmp) || $tmp === '' || !is_uploaded_file($tmp)) {
        Session::flash('error', 'Invalid upload. Choose the workbook again.');
        redirect('admin-excel-import.php');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!in_array($mime, [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/octet-stream',
    ], true)) {
        Session::flash('error', 'Only valid .xlsx workbooks are accepted.');
        redirect('admin-excel-import.php');
    }
    $uploadDir = WISDOM_ROOT . '/storage/imports';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
        Session::flash('error', 'The private import-storage folder is unavailable.');
        redirect('admin-excel-import.php');
    }
    $stagedPath = $uploadDir . '/' . bin2hex(random_bytes(16)) . '.xlsx';
    if (!move_uploaded_file($tmp, $stagedPath)) {
        Session::flash('error', 'The workbook could not be stored.');
        redirect('admin-excel-import.php');
    }
    chmod($stagedPath, 0640);
    try {
        $summary = $service->ingest($stagedPath, (string) ($file['name'] ?? 'workbook.xlsx'), $admin);
        Session::flash('success', sprintf('Import complete: %d matched, %d pending review.', $summary['matched'], $summary['pending']));
    } catch (\Wisdom\Core\AppException $exception) {
        Session::flash('error', $exception->getMessage());
    } catch (\Throwable $exception) {
        error_log('Excel import failed: ' . $exception->getMessage());
        Session::flash('error', 'The import could not be completed. Check the application log.');
    }
    redirect('admin-excel-import.php');
}

$pendingCount = $service->pendingCount();
$batches = $service->recentBatches(10);
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
        <header style="margin-bottom:var(--s-8)"><div class="eyebrow">Examinations</div><h1 style="margin:6px 0 4px">Import results from Excel</h1><p class="text-muted">Valid rows matching a student and an enrolled subject are imported. Unmatched rows require manual review.</p></header>
        <?php if ($pendingCount > 0): ?><section class="card" style="border-left:5px solid var(--gold-600);margin-bottom:var(--s-6)"><div class="row row--between" style="align-items:center;flex-wrap:wrap"><h2><?= (int) $pendingCount ?> rows awaiting review</h2><a href="admin-pending-reviews.php" class="btn btn--gold">Open review queue</a></div></section><?php endif; ?>
        <section class="card" style="margin-bottom:var(--s-8)">
            <form method="post" enctype="multipart/form-data" id="import-form"><?= csrf_field() ?><div class="field"><label for="workbook">Excel workbook (.xlsx, max 8 MB)</label><input id="workbook" name="workbook" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></div><div class="row gap-3" style="margin-top:var(--s-5)"><button type="submit" class="btn btn--gold">Upload and process</button><a href="admin-excel-import.php?template=1" class="btn btn--ghost">Download CSV template</a></div></form>
            <p class="card__hint" style="margin-top:var(--s-5);line-height:1.7"><strong>Required:</strong> Exam Number and Subject Name. <strong>Student identifier:</strong> email, student ID, or exact student name. Optional columns: Subject Code, Weight, Result, Grade, Status.</p>
        </section>
        <section class="card"><div class="card__head"><h2 class="card__title">Recent imports</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>File</th><th>By</th><th>Total</th><th>Matched</th><th>Pending</th><th>Rejected</th></tr></thead><tbody><?php if ($batches === []): ?><tr><td colspan="7" class="text-muted">No imports yet.</td></tr><?php else: foreach ($batches as $batch): ?><tr><td><?= e((string) $batch['created_at']) ?></td><td><?= e((string) $batch['original_filename']) ?></td><td><?= e((string) ($batch['admin_name'] ?? 'System')) ?></td><td><?= (int) $batch['total_rows'] ?></td><td><?= (int) $batch['matched_rows'] ?></td><td><?= (int) $batch['pending_rows'] ?></td><td><?= (int) $batch['rejected_rows'] ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
    </main>
</div>
<script src="assets/js/wisdom-ui.js" nonce="<?= e(nonce()) ?>" defer></script>
</body>
</html>
