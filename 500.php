<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

http_response_code(500);

$errorCode  = '500';
$errorTitle = 'Something went wrong';
$errorBody  = 'The problem has been logged. Please try again in a moment.';

require __DIR__ . '/partials/error-page.php';
