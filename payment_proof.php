<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Services\FileUploader;
use Wisdom\Services\PaymentService;

Guard::requirePermission('payment.review');

$id = Request::intGet('id');
$found = App::get(PaymentService::class)->proofPath(App::get(FileUploader::class), $id);

if ($found === null) {
    http_response_code(404);
    exit('Payment proof not found.');
}

$mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($found['path']);
if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
    http_response_code(415);
    exit('Unsupported proof type.');
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="payment-proof-' . $id . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'');
readfile($found['path']);
