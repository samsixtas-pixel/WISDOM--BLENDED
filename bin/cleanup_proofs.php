<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Services\FileUploader;
use Wisdom\Services\PaymentService;

$removed = App::get(PaymentService::class)->cleanupOldProofs(App::get(FileUploader::class));
printf("Removed %d payment proof(s).\n", $removed);