<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\Guard;

$user = Guard::user();
if ($user === null) {
	redirect('landing.php');
}

redirect($user->isSecretary() ? 'admin.php' : 'dashboard.php');
