<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;

$backupRoot = WISDOM_ROOT . '/storage/backups';
$keep = max(1, (int) (getenv('BACKUP_KEEP') ?: 7));
if (!is_dir($backupRoot) && !mkdir($backupRoot, 0750, true) && !is_dir($backupRoot)) {
    fwrite(STDERR, "Cannot create {$backupRoot}\n");
    exit(1);
}

$stamp = date('Ymd-His');
$tmp = $backupRoot . '/.' . $stamp;
if (!mkdir($tmp, 0750, true) && !is_dir($tmp)) {
    fwrite(STDERR, "Cannot create {$tmp}\n");
    exit(1);
}

$db = (array) App::config('db', []);
$dumpFile = $tmp . '/database.sql';
$dbUser = (string) ($db['user'] ?? 'root');
$dbPass = (string) ($db['pass'] ?? '');
$connectionArgs = $dbUser === 'root' && $dbPass === '' && file_exists('/opt/lampp/var/mysql/mysql.sock')
    ? '--socket=/opt/lampp/var/mysql/mysql.sock'
    : sprintf('-h %s -P %s', escapeshellarg((string) ($db['host'] ?? '127.0.0.1')), escapeshellarg((string) ($db['port'] ?? 3306)));
$dumpCommand = sprintf(
    'MYSQL_PWD=%s /opt/lampp/bin/mysqldump --single-transaction --quick --routines --triggers %s -u %s %s > %s 2>&1',
    escapeshellarg($dbPass),
    $connectionArgs,
    escapeshellarg($dbUser),
    escapeshellarg((string) ($db['name'] ?? 'wisdom_db')),
    escapeshellarg($dumpFile)
);
exec($dumpCommand, $dumpOutput, $dumpStatus);
if ($dumpStatus !== 0) {
    $fallbackCommand = sprintf(
        'MYSQL_PWD=%s /opt/lampp/bin/mysqldump --single-transaction --quick %s -u %s %s > %s 2>&1',
        escapeshellarg($dbPass),
        $connectionArgs,
        escapeshellarg($dbUser),
        escapeshellarg((string) ($db['name'] ?? 'wisdom_db')),
        escapeshellarg($dumpFile)
    );
    exec($fallbackCommand, $fallbackOutput, $fallbackStatus);
    if ($fallbackStatus !== 0) {
        fwrite(STDERR, "mysqldump failed: " . implode("\n", $dumpOutput) . "\n" . implode("\n", $fallbackOutput) . "\n");
        exec('rm -rf ' . escapeshellarg($tmp));
        exit(1);
    }
}

$storage = WISDOM_ROOT . '/storage';
$storageArchive = $tmp . '/storage.tar.gz';
$tarCommand = sprintf(
    'tar --exclude=%s/backups -czf %s -C %s . 2>&1',
    escapeshellarg($storage),
    escapeshellarg($storageArchive),
    escapeshellarg($storage)
);
exec($tarCommand, $tarOutput, $tarStatus);
if ($tarStatus !== 0) {
    fwrite(STDERR, "storage archive failed: " . implode("\n", $tarOutput) . "\n");
    exec('rm -rf ' . escapeshellarg($tmp));
    exit(1);
}

$final = $backupRoot . '/wisdom-backup-' . $stamp . '.tar.gz';
$packageCommand = sprintf(
    'tar -czf %s -C %s . && rm -rf %s',
    escapeshellarg($final),
    escapeshellarg($tmp),
    escapeshellarg($tmp)
);
exec($packageCommand, $packageOutput, $packageStatus);
if ($packageStatus !== 0 || !is_file($final)) {
    fwrite(STDERR, "Backup packaging failed.\n");
    exit(1);
}

printf("[%s] Backup written: %s (%s)\n", date('Y-m-d H:i:s'), basename($final), humanSize((int) filesize($final)));
$existing = glob($backupRoot . '/wisdom-backup-*.tar.gz') ?: [];
usort($existing, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
while (count($existing) > $keep) {
    $oldest = array_pop($existing);
    if ($oldest !== false) {
        @unlink($oldest);
        printf("[%s] Rotated out: %s\n", date('Y-m-d H:i:s'), basename($oldest));
    }
}

function humanSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $unit = 0;
    while ($bytes >= 1024 && $unit < 3) {
        $bytes /= 1024;
        $unit++;
    }
    return number_format($bytes, $unit === 0 ? 0 : 1) . ' ' . $units[$unit];
}
