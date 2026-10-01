<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Repositories\SubjectRepository;
use Wisdom\Repositories\UserRepository;

Guard::requirePermission('admin.access');
Guard::requirePasswordResetHandled();
Guard::throttle('suggest', 180, 60);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
$type = Request::get('type');
$query = trim(Request::get('q'));
try {
    if ($type === 'student') {
        $items = [];
        foreach (App::get(UserRepository::class)->search($query, 12) as $user) {
            if (!$user->isStaff()) {
                $items[] = ['value' => (string) $user->getId(), 'label' => $user->getName(), 'meta' => (string) $user->getLevel() . ' · ' . $user->getEmail()];
            }
        }
        echo json_encode(['items' => $items], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($type === 'subject') {
        $needle = function_exists('mb_strtolower') ? mb_strtolower($query) : strtolower($query);
        $items = [];
        foreach (App::get(SubjectRepository::class)->catalogue() as $level => $subjects) {
            foreach ($subjects as $subject) {
                $value = function_exists('mb_strtolower') ? mb_strtolower($subject) : strtolower($subject);
                if ($needle === '' || str_contains($value, $needle)) {
                    $items[] = ['value' => $subject, 'label' => $subject, 'meta' => (string) $level];
                }
            }
        }
        echo json_encode(['items' => array_slice($items, 0, 12)], JSON_THROW_ON_ERROR);
        exit;
    }
    echo json_encode(['items' => []], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('admin_suggest failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['items' => []], JSON_THROW_ON_ERROR);
}
