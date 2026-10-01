<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Services\FileUploader;
use Wisdom\Services\PaymentService;

Guard::requirePermission('payment.review');
Guard::requirePasswordResetHandled();
$found = App::get(PaymentService::class)->proofPath(App::get(FileUploader::class), Request::intGet('id'));
if ($found === null) {
    render_error_page(404, 'Proof not found', 'The payment proof is no longer available.');
}
$mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($found['path']);
if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
    render_error_page(415, 'Unsupported file', 'This proof type cannot be displayed.');
}
$dataUri = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($found['path']));
$isImage = str_starts_with($mime, 'image/');
$pageTitle = 'Payment proof';
$noIndex = true;
?><!doctype html><html lang="en"><head><?php require __DIR__ . '/partials/head.php'; ?><style nonce="<?= e(nonce()) ?>">body{margin:0;background:#f0ede4}.bar{position:sticky;top:0;z-index:2;display:flex;justify-content:space-between;align-items:center;padding:14px 20px;background:#fdfbf6e6;border-bottom:1px solid var(--line);backdrop-filter:blur(12px)}.bar strong{color:var(--navy-800)}.cancel{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border:0;border-radius:10px;background:var(--navy-700);color:#fff;font-weight:700;cursor:pointer}.stage{display:grid;place-items:center;min-height:calc(100vh - 76px);padding:30px 20px 60px}.stage img,.stage iframe{width:min(100%,900px);max-height:calc(100vh - 160px);border:0;border-radius:14px;background:#fff;box-shadow:0 20px 60px #061a2c2e}.stage img{width:auto}</style></head><body><header class="bar"><strong>Payment proof</strong><button class="cancel" id="cancel" type="button" aria-label="Close proof viewer">Close</button></header><main class="stage"><?php if ($isImage): ?><img src="<?= e($dataUri) ?>" alt="Payment proof"><?php else: ?><iframe src="<?= e($dataUri) ?>" title="Payment proof"></iframe><?php endif; ?></main><script nonce="<?= e(nonce()) ?>">document.getElementById('cancel').addEventListener('click',function(){window.close();setTimeout(function(){if(!window.closed)history.back()},60)});document.addEventListener('keydown',function(e){if(e.key==='Escape')document.getElementById('cancel').click()});</script></body></html>
