<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$directory = dirname(__DIR__) . '/storage/imports';
$cutoff = time() - 30 * 86400;
$removed = 0;
if (is_dir($directory)) {
    foreach (new DirectoryIterator($directory) as $entry) {
        if ($entry->isDot() || !$entry->isFile() || $entry->getFilename() === '.gitkeep') continue;
        if ($entry->getMTime() < $cutoff && @unlink($entry->getPathname())) $removed++;
    }
}
printf("[%s] Removed %d import workbooks older than 30 days.\n", date('Y-m-d H:i:s'), $removed);
