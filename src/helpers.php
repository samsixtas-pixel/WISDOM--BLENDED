<?php
declare(strict_types=1);

use Wisdom\Core\Csrf;
use Wisdom\Core\App;

/** Escape a value for safe HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_once_field(string $formKey): string
{
    if (!in_array($formKey, ['payment.submit', 'exam.create', 'team.create'], true)) {
        return '';
    }
    return '<input type="hidden" name="csrf_once" value="' . e(Csrf::issueToken($formKey)) . '">';
}

/** Per-request CSP nonce for inline <script> tags. */
function nonce(): string
{
    return App::nonce();
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function money(int|float $amount): string
{
    return number_format((float) $amount, 0, '.', ',');
}

/** Unicode-aware length that works even when mbstring is not installed. */
function str_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : (int) preg_match_all('/./us', $value);
}

function render_error_page(int $status, string $title, string $message, string $hint = ''): never
{
    http_response_code($status);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }

    try {
        $baseUrl = rtrim((string) App::config('app.base_url', ''), '/');
    } catch (Throwable) {
        $baseUrl = '';
    }

    $safeTitle = e($title);
    $safeMessage = e($message);
    $safeHint = $hint === '' ? '' : e($hint);
    $logoHref = e($baseUrl . '/assets/img/logo.png');
    $homeHref = e($baseUrl . '/landing.php');

    echo <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$safeTitle} · WISDOM</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#fdfbf6;color:#0e1520;font:16px/1.6 system-ui,sans-serif}.card{width:min(100%,560px);padding:48px 32px;border:1px solid #e3e0d6;border-radius:20px;background:#fbf9f4;box-shadow:0 32px 80px #061a2c2e;text-align:center}.code{margin:0;color:#0d2b45;font:700 5rem/1 Georgia,serif}.rule{height:2px;max-width:120px;margin:24px auto;background:linear-gradient(90deg,#c9a227 0 30%,transparent 30%)}h1{margin:12px 0 16px;color:#0d2b45;font:700 1.4rem Georgia,serif}.hint{color:#5a6a7d;font-size:14px}.btn{display:inline-block;padding:12px 20px;border-radius:10px;background:#c9a227;color:#061a2c;font-weight:700;text-decoration:none}</style></head><body>
<main class="card"><p class="code">{$status}</p><div class="rule"></div><h1>{$safeTitle}</h1><p>{$safeMessage}</p><p class="hint">{$safeHint}</p><a class="btn" href="{$homeHref}">Return home</a></main>
</body></html>
HTML;
    exit;
}

function whatsapp_url(string $message = ''): string
{
    try {
        $phone = (string) App::config('contact.phone_wa', '255673266852');
    } catch (Throwable) {
        $phone = '255673266852';
    }

    return 'https://wa.me/' . $phone . ($message === '' ? '' : '?text=' . rawurlencode($message));
}

function sortable_th(string $label, string $column, \Wisdom\Core\ListQuery $query): string
{
    $direction = $query->sort === $column && $query->dir === 'asc' ? 'desc' : 'asc';
    $arrow = $query->sort === $column ? ($query->dir === 'asc' ? ' ↑' : ' ↓') : '';
    $params = $_GET;
    $params['sort'] = $column;
    $params['dir'] = $direction;
    unset($params['page']);
    return '<th><a class="sortable-th" href="?' . e(http_build_query($params)) . '">' . e($label . $arrow) . '</a></th>';
}

function render_pager(\Wisdom\Core\ListQuery $query, int $total): string
{
    $pages = max(1, (int) ceil($total / $query->perPage));
    if ($pages <= 1) return '';
    $html = '<nav class="pager" aria-label="Pagination">';
    for ($page = 1; $page <= $pages; $page++) {
        $params = $_GET;
        $params['page'] = $page;
        $class = $page === $query->page ? ' class="is-active"' : '';
        $html .= '<a' . $class . ' href="?' . e(http_build_query($params)) . '">' . $page . '</a>';
    }
    return $html . '</nav>';
}
