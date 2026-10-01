<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

http_response_code(403);

$errorCode  = '403';
$errorTitle = 'That area is restricted';
$errorBody  = 'Your account does not have permission to open this page.';

require __DIR__ . '/partials/error-page.php';
