<?php
declare(strict_types=1);

use Wisdom\Core\App;

define('WISDOM_ROOT', dirname(__DIR__));

require_once WISDOM_ROOT . '/src/helpers.php';

if (is_file(WISDOM_ROOT . '/vendor/autoload.php')) {
    require_once WISDOM_ROOT . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Wisdom\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $file = WISDOM_ROOT . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

App::boot(require __DIR__ . '/config.php');
