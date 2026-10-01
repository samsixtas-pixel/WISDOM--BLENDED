<?php
declare(strict_types=1);

/**
 * Uptime / health probe for UptimeRobot, BetterStack, etc.
 * Returns HTTP 200 with {"status":"ok"} when the app and database respond.
 */

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Database;

$healthy = true;
$detail  = 'ok';

try {
    App::get(Database::class)->fetchValue('SELECT 1');
} catch (\Throwable $exception) {
    $healthy = false;
    $detail  = 'database unavailable';
}

http_response_code($healthy ? 200 : 503);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'status' => $healthy ? 'ok' : 'degraded',
    'detail' => $detail,
    'time'   => date('c'),
], JSON_THROW_ON_ERROR);
