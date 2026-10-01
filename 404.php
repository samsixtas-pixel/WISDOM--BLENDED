<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

http_response_code(404);

$errorCode  = '404';
$errorTitle = 'We could not find that page';
$errorBody  = 'The address may be mistyped, or the page may have been moved.';

require __DIR__ . '/partials/error-page.php';
