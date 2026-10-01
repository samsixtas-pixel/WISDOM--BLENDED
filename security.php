<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Guard;

Guard::requirePermission('user.manage');
Guard::requirePasswordResetHandled();
$checks = [
    'PHP version' => PHP_VERSION,
    'mysqli' => extension_loaded('mysqli') ? 'available' : 'missing',
    'fileinfo' => extension_loaded('fileinfo') ? 'available' : 'missing',
    'CSRF' => 'enabled',
    'HTTPS' => \Wisdom\Core\Session::isHttps() ? 'enabled' : 'not active',
    'OPcache' => extension_loaded('Zend OPcache') ? ((bool) (@opcache_get_status(false)['opcache_enabled'] ?? false) ? 'enabled' : 'available but disabled') : 'not available',
    'Storage writable' => is_writable(WISDOM_ROOT . '/storage') ? 'yes' : 'no',
    'Debug mode' => App::config('app.debug') ? 'enabled' : 'disabled',
];
$pageTitle = 'Security report';
?><!doctype html><html lang="en"><head><?php require __DIR__ . '/partials/head.php'; ?></head><body><div class="shell"><?php require __DIR__ . '/partials/nav.php'; ?><main class="shell__main page-fade"><?php require __DIR__ . '/partials/admin_flash.php'; ?><div class="eyebrow">Operations</div><h1>Security report</h1><section class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Check</th><th>Value</th></tr></thead><tbody><?php foreach ($checks as $label => $value): ?><tr><td><?= e($label) ?></td><td><?= e($value) ?></td></tr><?php endforeach; ?></tbody></table></div></section></main></div></body></html>