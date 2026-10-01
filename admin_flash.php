<?php
$successMsg = \Wisdom\Core\Session::pullFlash('success');
$errorMsg = \Wisdom\Core\Session::pullFlash('error');
if ($successMsg !== ''): ?><div id="js-success-modal" hidden data-title="<?= e($successMsg) ?>" data-body="The change has been recorded." data-confirm="Done"></div><?php endif; ?>
<?php if ($errorMsg !== ''): ?><div class="alert alert--error" role="alert"><?= e($errorMsg) ?></div><?php endif; ?>
