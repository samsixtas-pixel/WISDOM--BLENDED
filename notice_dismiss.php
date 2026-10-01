<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Csrf;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Services\NoticeService;

Guard::requireLogin();

if (!Request::isPost() || !Csrf::verifyRequest()) {
    redirect('dashboard.php');
}

$id = Request::intPost('notice_id');
if ($id > 0) {
    App::get(NoticeService::class)->dismiss($id);
}

$back = Request::post('redirect_to');
$host = parse_url($back, PHP_URL_HOST);
if (
    $back === ''
    || str_starts_with($back, '//')
    || str_contains($back, '\\')
    || preg_match('/[\r\n]/', $back) === 1
    || ($host !== null && $host !== false)
) {
    $back = 'dashboard.php';
}

redirect($back);