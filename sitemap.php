<?php
declare(strict_types=1);

/** XML sitemap generated from the real request host (no hard-coded domain). */

require __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$host   = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
$origin = $host === '' ? '' : $scheme . '://' . $host . $base;

$pages = [
    ''             => ['1.0', 'weekly'],
    'landing.php'  => ['1.0', 'weekly'],
    'register.php' => ['0.8', 'monthly'],
    'login.php'    => ['0.5', 'monthly'],
    'contact.php'  => ['0.5', 'monthly'],
    'privacy.php'  => ['0.3', 'yearly'],
    'terms.php'    => ['0.3', 'yearly'],
];

$today = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($pages as $path => [$priority, $frequency]) {
    $location = ($origin === '' ? '' : $origin . '/') . $path;
    echo '  <url><loc>', htmlspecialchars($location, ENT_XML1 | ENT_QUOTES, 'UTF-8'), '</loc>',
        '<lastmod>', $today, '</lastmod>',
        '<changefreq>', $frequency, '</changefreq>',
        '<priority>', $priority, '</priority></url>', "\n";
}
echo '</urlset>', "\n";
