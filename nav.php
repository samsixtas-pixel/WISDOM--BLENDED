<?php
/** Shared role-aware desktop rail and mobile navigation. */
if ((!isset($user) || !$user instanceof \Wisdom\Models\User) && isset($admin) && $admin instanceof \Wisdom\Models\User) $user = $admin;
if (!isset($user) || !$user instanceof \Wisdom\Models\User) $user = \Wisdom\Core\Guard::user();
if (!$user instanceof \Wisdom\Models\User) throw new \RuntimeException('partials/nav.php requires an authenticated user.');
$active = isset($active) && is_string($active) ? $active : '';
$isStudent = !$user->isStaff();
$isAdmin = $user->isAdmin();
$pendingPayments = $totalUsers = $pendingResetCount = $pendingReviews = 0;
if ($isAdmin) {
    $counts = $_SESSION['_nav_counts'] ?? null;
    if (!is_array($counts) || (int) ($counts['at'] ?? 0) < time() - 30) {
        try {
            $counts = [
                'at' => time(),
                'payments' => \Wisdom\Core\App::get(\Wisdom\Services\PaymentService::class)->pendingCount(),
                'users' => \Wisdom\Core\App::get(\Wisdom\Repositories\UserRepository::class)->count(),
                'resets' => \Wisdom\Core\App::get(\Wisdom\Services\PasswordResetService::class)->pendingRequestCount(),
                'reviews' => (bool) \Wisdom\Core\App::config('features.excel_import', false) ? \Wisdom\Core\App::get(\Wisdom\Services\ExcelImportService::class)->pendingCount() : 0,
            ];
        } catch (\Throwable) {
            $counts = ['at' => time(), 'payments' => 0, 'users' => 0, 'resets' => 0, 'reviews' => 0];
        }
        $_SESSION['_nav_counts'] = $counts;
    }
    $pendingPayments = max(0, (int) ($counts['payments'] ?? 0));
    $totalUsers = max(0, (int) ($counts['users'] ?? 0));
    $pendingResetCount = max(0, (int) ($counts['resets'] ?? 0));
    $pendingReviews = max(0, (int) ($counts['reviews'] ?? 0));
}
$excelImportEnabled = (bool) \Wisdom\Core\App::config('features.excel_import', false);
$icon = [
    'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg>',
    'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>',
    'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20c0-3 3-5 6-5s6 2 6 5M15 15c3 0 6 1.5 6 5"/></svg>',
    'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>',
    'card' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M7 15h4"/></svg>',
    'exam' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
    'upload' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 15V3M7 8l5-5 5 5M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>',
    'list' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/></svg>',
    'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 11v2a2 2 0 0 0 2 2h3l7 5V4l-7 5H6a2 2 0 0 0-2 2Z"/><path d="M19 9a5 5 0 0 1 0 6"/></svg>',
    'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3 19 6v5c0 4.5-2.7 8-7 10-4.3-2-7-5.5-7-10V6l7-3Z"/></svg>',
    'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 20V6M10 20v-8M16 20V4M22 20H2"/></svg>',
    'video' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="5" width="20" height="13" rx="2"/><path d="M8 21h8M12 18v3"/></svg>',
    'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="3"/><path d="M20 12a8 8 0 0 0-.1-1.3l2.1-1.6-2-3.4-2.4 1a8 8 0 0 0-2.2-1.3L14.9 2h-4l-.4 2.6a8 8 0 0 0-2.3 1.3l-2.4-1-2 3.4 2.1 1.6a8 8 0 0 0 0 2.6L3.7 14l2 3.4 2.4-1a8 8 0 0 0 2.3 1.3L11 20h4l.4-2.6a8 8 0 0 0 2.2-1.3l2.4 1 2-3.4-2.1-1.6z"/></svg>',
    'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>',
];
if ($isStudent) {
    $newResultsCount = 0;
    try { $newResultsCount = \Wisdom\Core\App::get(\Wisdom\Repositories\UserRepository::class)->countNewResultsFor($user->getId()); } catch (\Throwable) {}
    $groups = [
        '' => [['dashboard','dashboard.php','Dashboard',$icon['home'],null],['profile','profile.php','My profile',$icon['user'],null]],
        'Learning' => [['exams','exams.php','Results',$icon['exam'],$newResultsCount ?: null],['classes','classes.php','Live classes',$icon['video'],null]],
        'Account' => [['fees','fees.php','Fees',$icon['card'],null],['contact','contact.php','Contact support',$icon['bell'],null],['settings','settings.php','Settings',$icon['settings'],null]],
    ];
} elseif ($isAdmin) {
    $groups = [
        'Overview' => [['admin','admin.php','Dashboard',$icon['home'],null]],
        'Tasks' => [['admin-payments','admin-payments.php','Fees verification',$icon['card'],$pendingPayments ?: null],['admin-exams','admin-exams.php','Examinations',$icon['exam'],null],['admin-notices','admin-notices.php','Notices',$icon['bell'],null]],
        'People' => [['admin-users','admin-users.php','All users',$icon['users'],$totalUsers ?: null],['admin-add-user','admin-add-user.php','Add user',$icon['plus'],null]],
        'Security' => [['admin-password-resets','admin-password-resets.php','Reset requests',$icon['shield'],$pendingResetCount ?: null]],
        'Insights' => [['reports','reports.php','Reports',$icon['chart'],null]],
        'Account' => [['profile','profile.php','My profile',$icon['user'],null],['contact','contact.php','Contact support',$icon['bell'],null],['settings','settings.php','Settings',$icon['settings'],null]],
    ];
    if ($excelImportEnabled) {
        $groups['Tasks'][] = ['admin-excel-import','admin-excel-import.php','Import results',$icon['upload'],null];
        $groups['Review'] = [['admin-pending-reviews','admin-pending-reviews.php','Pending reviews',$icon['list'],$pendingReviews ?: null]];
    }
} else {
    $groups = [
        'Examinations' => [['admin','admin.php','Examinations desk',$icon['home'],null],['admin-exams','admin-exams.php','Examinations',$icon['exam'],null]],
        'Account' => [['profile','profile.php','My profile',$icon['user'],null],['contact','contact.php','Contact support',$icon['bell'],null],['settings','settings.php','Settings',$icon['settings'],null]],
    ];
}
?>
<header class="mobile-bar" role="banner">
    <button class="hamburger" id="hamburger" type="button" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
    <a href="<?= $isStudent ? 'dashboard.php' : 'admin.php' ?>" class="mobile-bar__logo"><img src="assets/img/logo.png" alt="" width="40" height="40" loading="eager" decoding="async" fetchpriority="high"><div class="mobile-bar__wordmark"><div class="wordmark">WISDOM</div><div class="wordmark-sub">BLENDED CLASSES</div></div></a>
</header>
<aside id="sidebar" class="rail" role="navigation" aria-label="Main navigation">
    <div class="rail__brand"><img src="assets/img/logo.png" alt="WISDOM" class="rail__logo" width="48" height="48"><div class="rail__wordmark"><div class="wordmark">WISDOM</div><div class="wordmark-sub">Blended Classes</div></div><button class="rail__close" id="rail-close" type="button" aria-label="Close navigation"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
    <?php foreach ($groups as $title => $links): ?><div class="rail__section"><?php if ($title !== ''): ?><p class="rail__section-title"><?= e($title) ?></p><?php endif; ?><?php foreach ($links as [$key,$href,$label,$svg,$badge]): ?><a class="rail__link <?= $active === $key ? 'is-active' : '' ?>" href="<?= e($href) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>><span aria-hidden="true"><?= $svg ?></span><span><?= e($label) ?></span><?php if ($badge !== null): ?><span class="rail-badge" aria-label="<?= (int) $badge ?> items"><?= (int) $badge ?></span><?php endif; ?></a><?php endforeach; ?></div><?php endforeach; ?>
    <div class="rail__foot"><div class="rail__user"><img class="rail__user-avatar" src="avatar.php?id=<?= (int) $user->getId() ?>&amp;v=<?= e(substr((string) ($user->getAvatar() ?? 'default'),0,12)) ?>" alt="" loading="lazy" decoding="async" width="40" height="40"><div><div class="rail__user-name"><?= e($user->getName()) ?></div><div class="rail__user-role"><?= e($user->roleLabel()) ?></div></div></div><a class="rail__link" href="logout.php" style="margin-top:8px"><?= $icon['logout'] ?><span>Log out</span></a></div>
</aside>
