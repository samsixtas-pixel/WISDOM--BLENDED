<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Services\UserService;

Guard::requirePermission('user.search');
Guard::throttle('user.search', 40, 60);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$term = Request::get('q');
$limit = (int) App::config('admin.search_limit', 100);
$users = App::get(UserService::class)->search($term, $limit);

echo json_encode([
    'query' => $term,
    'count' => count($users),
    'users' => array_map(static fn ($user): array => [
        'id'          => $user->getId(),
        'name'        => $user->getName(),
        'email'       => $user->getEmail(),
        'sex'         => $user->getSex(),
        'level'       => $user->getLevel(),
        'role'        => $user->roleLabel(),
        'is_approved' => $user->isApproved(),
        'is_active'   => $user->isActive(),
        'created_at'  => $user->getCreatedAt(),
    ], $users),
], JSON_THROW_ON_ERROR);
