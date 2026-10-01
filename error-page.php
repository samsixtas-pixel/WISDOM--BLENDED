<?php
/**
 * Branded error page partial.
 *
 * @var string $errorCode  e.g. '404'
 * @var string $errorTitle short headline
 * @var string $errorBody  one-line explanation
 */
$errorCode  = isset($errorCode) ? (string) $errorCode : '500';
$errorTitle = isset($errorTitle) ? (string) $errorTitle : 'Something went wrong';
$errorBody  = isset($errorBody) ? (string) $errorBody : 'Please try again in a moment.';

$base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
$home = 'landing.php';
$homeLabel = 'Go to the home page';
try {
    $current = \Wisdom\Core\Guard::user();
    if ($current !== null) {
        $home = $current->isStaff() ? 'admin.php' : 'dashboard.php';
        $homeLabel = $current->isStaff() ? 'Back to the workspace' : 'Back to my dashboard';
    }
} catch (\Throwable) {
    // Never let the error page itself fail.
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#0d2b45">
<title><?= e($errorCode) ?> · <?= e($errorTitle) ?> | WISDOM</title>
<link rel="icon" href="<?= e($base) ?>/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e($base) ?>/assets/css/wisdom.css">
</head>
<body class="error-page">
<main class="error-card">
    <div class="error-code"><?= e($errorCode) ?></div>
    <h1><?= e($errorTitle) ?></h1>
    <p class="text-muted"><?= e($errorBody) ?></p>
    <p>
        <a class="btn btn-gold" href="<?= e($base . '/' . $home) ?>"><?= e($homeLabel) ?></a>
        <a class="btn btn-ghost" href="<?= e($base . '/contact.php') ?>">Contact support</a>
    </p>
</main>
</body>
</html>
