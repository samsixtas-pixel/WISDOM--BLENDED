<?php
/** Shared document metadata and styles. $pageTitle/$pageDesc are optional. */
$pageTitle = isset($pageTitle) && is_string($pageTitle) ? trim($pageTitle) : '';
$pageDesc = isset($pageDesc) && is_string($pageDesc) && $pageDesc !== ''
    ? $pageDesc
    : 'WISDOM Blended Classes — learn, think, grow.';
$fullTitle = $pageTitle === '' ? 'WISDOM Blended Classes' : $pageTitle . ' · WISDOM';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0d2b45">
<meta name="description" content="<?= e($pageDesc) ?>">
<title><?= e($fullTitle) ?></title>
<link rel="preconnect" href="https://wa.me" crossorigin>
<link rel="stylesheet" href="assets/css/wisdom.css" fetchpriority="high">
