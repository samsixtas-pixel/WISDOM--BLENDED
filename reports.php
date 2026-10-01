<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Services\ReportService;

$admin = Guard::requirePermission('report.view');
Guard::requirePasswordResetHandled();
$reportService = App::get(ReportService::class);
$reports = $reportService->available();
$active = 'reports';
$pageTitle = 'Reports';

$selectedKey = Request::get('report') ?: array_key_first($reports);
$selected = $reports[$selectedKey] ?? null;

if ($selected !== null && Request::get('download') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $selected->key() . '.csv"');
    echo $selected->toCsv();
    exit;
}
?>
<!doctype html><html lang="en"><head><?php require __DIR__ . '/partials/head.php'; ?>
<style>/*
body{
    margin:0;
    padding:30px;
    color:#172033;
    background:#f4f7fb;
    font:15px/1.5 system-ui,sans-serif;
    }
main{
    max-width:1100px;margin:auto}
=======register page css=====
.subjects-fieldset{margin:18px 0 0;padding:12px 14px;border:1px solid var(--line);border-radius:9px}
.subjects-fieldset legend{padding:0 6px;font-weight:650}
.subjects-list{display:grid;gap:4px;max-height:220px;overflow-y:auto;margin-top:6px}
.subject-option{display:flex;align-items:center;gap:9px;margin:0;padding:6px 8px;border-radius:6px;font-weight:400;cursor:pointer}
.subject-option:hover{background:#f4f7fb}
.subject-option input{width:18px;height:18px;padding:0;margin:0;flex:0 0 auto;accent-color:var(--primary)}=======
a{color:#315efb;text-decoration:none}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0}
.tabs a{padding:9px 15px;border-radius:8px;background:#fff;border:1px solid #d5dce8}
.tabs a.active{color:#fff;background:#315efb;border-color:#315efb}
.panel{margin:18px 0;padding:24px;overflow:auto;border-radius:16px;background:#fff;box-shadow:0 8px 25px #1c264b10}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;text-align:left;border-bottom:1px solid #e5e7eb}
th{background:#f4f7fb}
.button{display:inline-block;margin-top:14px;padding:10px 16px;border-radius:8px;color:#fff;background:#315efb}
</style>*/</head><body><div class="shell"><?php require __DIR__ . '/partials/nav.php'; ?><main class="shell__main page-fade">
<?php require __DIR__ . '/partials/admin_flash.php'; ?>
<p><a href="admin.php">&larr; Admin workspace</a></p>
<h1>Reports</h1>
<nav class="tabs">
<?php foreach ($reports as $key => $report): ?>
    <a href="reports.php?report=<?= e($key) ?>" class="<?= $key === $selectedKey ? 'active' : '' ?>"><?= e($report->title()) ?></a>
<?php endforeach; ?>
</nav>
<?php if ($selected === null): ?>
    <section class="panel"><p>No reports are available.</p></section>
<?php else: ?>
<section class="panel">
    <h2><?= e($selected->title()) ?></h2>
    <table>
        <thead><tr><?php foreach ($selected->headers() as $header): ?><th><?= e($header) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php $rows = $selected->rows(); ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="<?= count($selected->headers()) ?>">No data yet.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <tr><?php foreach ($row as $cell): ?><td><?= e((string) $cell) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    <a class="button" href="reports.php?report=<?= e($selectedKey) ?>&download=csv">Download CSV</a>
</section>
<?php endif; ?>
</main></div><script src="assets/js/wisdom-ui.js" defer></script></body></html>
