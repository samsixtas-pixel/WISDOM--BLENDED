<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';
use Wisdom\Core\App;
use Wisdom\Core\Guard;
use Wisdom\Models\LiveClass;
use Wisdom\Services\LiveClassService;
$user = Guard::requireLogin();
if ($user->isSecretary()) redirect('admin.php');
$active = 'classes';
$service = App::get(LiveClassService::class);
$classes = $user->can('admin.access') ? $service->all() : $service->forStudent($user);
$pageTitle = 'Live classes';
?>
<!doctype html>
<html lang="en"><head><?php require __DIR__ . '/partials/head.php'; ?></head>
<body><div class="shell"><?php require __DIR__ . '/partials/nav.php'; ?><main class="shell__main page-fade"><?php require __DIR__ . '/partials/notice.php'; ?><div class="eyebrow">Your learning schedule</div><h1>Live classes</h1><p class="text-muted">Sessions for subjects associated with your programme.</p>
<?php if ($classes === []): ?><section class="card empty-state"><h2>No sessions scheduled</h2><p class="text-muted">Upcoming sessions will appear here.</p></section><?php else: ?><div class="class-list"><?php foreach ($classes as $class): $subject=$class instanceof LiveClass?$class->getSubjectName():(string)($class['subject_name']??'');$level=$class instanceof LiveClass?$class->getLevel():(string)($class['level']??'');$when=$class instanceof LiveClass?$class->getScheduledAt():(string)($class['scheduled_at']??'');$url=$class instanceof LiveClass?$class->getLink():(string)($class['link']??''); ?><section class="card class-card"><div class="eyebrow"><?= e($level) ?></div><h2><?= e($subject) ?></h2><p><?= e(date('D, j M Y · H:i',strtotime($when))) ?></p><?php if(filter_var($url,FILTER_VALIDATE_URL)&&preg_match('#^https?://#i',$url)): ?><a class="btn btn--gold" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">Join session</a><?php endif; ?></section><?php endforeach; ?></div><?php endif; ?></main></div><script src="assets/js/wisdom-ui.js" defer></script></body></html>
