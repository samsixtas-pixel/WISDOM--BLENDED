<?php
/** Active notices shown on signed-in pages. */
$noticeService = $noticeService ?? \Wisdom\Core\App::get(\Wisdom\Services\NoticeService::class);
$currentUser = \Wisdom\Core\Guard::user();
$activeNotices = $noticeService->activeForCurrentUser($currentUser?->getId());
if ($activeNotices === []) {
    return;
}
?>
<div class="notice-stack">
<?php foreach ($activeNotices as $notice): ?>
    <article class="notice-card" role="status">
        <div class="row" style="align-items:flex-start"><span class="clay clay--gold clay--sm" aria-hidden="true">i</span><div><div class="eyebrow">Notice from administration</div><h3><?= e($notice->getTitle()) ?></h3><p><?= e($notice->getBody()) ?></p><?php if ($notice->getExpiresAt() !== null): ?><small class="text-muted">Expires <?= e(date('D j M · H:i', strtotime($notice->getExpiresAt()))) ?></small><?php endif; ?>
            <form method="post" action="notice_dismiss.php"><?= csrf_field() ?><input type="hidden" name="notice_id" value="<?= (int) $notice->getId() ?>"><input type="hidden" name="redirect_to" value="<?= e($_SERVER['REQUEST_URI'] ?? 'dashboard.php') ?>"><button class="btn btn--ghost btn--sm" type="submit">Dismiss</button></form>
        </div></div>
    </article>
<?php endforeach; ?>
</div>
