<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Repositories\UserRepository;
use Wisdom\Services\AvatarService;

Guard::requireLogin();
$id = Request::intGet('id');
if ($id <= 0) {
    http_response_code(404);
    exit;
}

$user = App::get(UserRepository::class)->find($id);
$path = $user === null ? null : App::get(AvatarService::class)->pathFor($user->getAvatar());
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

if ($path === null) {
    $palette = ['#0d2b45', '#17395a', '#1f5262', '#2d6a7a', '#8f6b0d', '#234d72'];
    $bg = $palette[($user?->getId() ?? 0) % count($palette)];
    $initial = strtoupper(substr($user?->getName() ?? '?', 0, 1)) ?: '?';
    header('Content-Type: image/svg+xml; charset=utf-8');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="32" fill="' . $bg . '"/><text x="32" y="43" text-anchor="middle" font-family="Georgia,serif" font-size="32" font-weight="700" fill="#f4e5b3">' . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</text></svg>';
    exit;
}

$mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
if (!is_string($mime) || !in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'], true)) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
readfile($path);