<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Services\AuthService;

App::get(AuthService::class)->logout();
redirect('login.php?logged_out=1');
