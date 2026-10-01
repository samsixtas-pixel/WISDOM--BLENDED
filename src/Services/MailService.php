<?php
declare(strict_types=1);

namespace Wisdom\Services;

final class MailService
{
    public function __construct(
        private string $fromAddress,
        private string $fromName,
        private bool $logOnly,
        private string $logDir,
    ) {
    }

    public function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if ($textBody === '') {
            $textBody = trim(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $htmlBody) ?? ''));
        }
        return $this->logOnly
            ? $this->writeToLog($to, $subject, $htmlBody, $textBody)
            : $this->sendViaMail($to, $subject, $htmlBody, $textBody);
    }

    private function writeToLog(string $to, string $subject, string $html, string $text): bool
    {
        if (!is_dir($this->logDir) && !mkdir($this->logDir, 0750, true) && !is_dir($this->logDir)) {
            error_log('MailService: cannot create ' . $this->logDir);
            return false;
        }
        $stamp = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', substr($subject, 0, 40)) ?: 'mail';
        $file = sprintf('%s/%s-%s.eml', rtrim($this->logDir, '/'), $stamp, $slug);
        $headers = [
            'From: ' . $this->formatFrom(), 'To: ' . $to, 'Subject: ' . $subject,
            'MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8', 'Date: ' . date('r'),
        ];
        $raw = implode("\r\n", $headers) . "\r\n\r\n" . $html . "\r\n\r\n-----\r\nPlain text:\r\n" . $text;
        return file_put_contents($file, $raw, LOCK_EX) !== false;
    }

    private function sendViaMail(string $to, string $subject, string $html, string $text): bool
    {
        $boundary = 'wdb_' . bin2hex(random_bytes(8));
        $headers = [
            'From: ' . $this->formatFrom(), 'Reply-To: ' . $this->fromAddress,
            'MIME-Version: 1.0', 'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'Content-Transfer-Encoding: 8bit',
        ];
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n";
        $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n--{$boundary}--";
        return @mail($to, $subject, $body, implode("\r\n", $headers), '-f' . $this->fromAddress);
    }

    private function formatFrom(): string
    {
        return sprintf('"%s" <%s>', $this->fromName, $this->fromAddress);
    }

    public function brandedTemplate(string $title, string $introHtml, string $ctaLabel = '', string $ctaUrl = ''): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $ctaBlock = '';
        if ($ctaLabel !== '' && $ctaUrl !== '') {
            $safeLabel = htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8');
            $safeUrl = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');
            $ctaBlock = "<p style=\"margin:28px 0 8px\"><a href=\"{$safeUrl}\" style=\"display:inline-block;padding:13px 22px;background:#c9a227;color:#061a2c;font-weight:700;text-decoration:none;border-radius:10px\">{$safeLabel}</a></p><p style=\"font-size:12px;color:#7b8899;word-break:break-all\">Or paste this into your browser:<br>{$safeUrl}</p>";
        }
        return "<!doctype html><html><body style=\"margin:0;padding:24px;background:#f7f2e6;font-family:system-ui,sans-serif;color:#0e1520\"><table role=\"presentation\" width=\"100%\" style=\"max-width:560px;margin:0 auto;background:#fbf9f4;border-radius:16px;border:1px solid #e3e0d6\"><tr><td style=\"padding:28px;text-align:center\"><div style=\"font-family:Georgia,serif;font-size:22px;font-weight:700;letter-spacing:.14em;color:#0d2b45\">WISDOM</div><div style=\"font-size:11px;letter-spacing:.36em;color:#1f5262\">BLENDED CLASSES</div></td></tr><tr><td style=\"padding:8px 28px 28px\"><h1 style=\"font-family:Georgia,serif;font-size:22px;color:#0d2b45\">{$safeTitle}</h1><div style=\"font-size:15px;line-height:1.6;color:#2a3544\">{$introHtml}</div>{$ctaBlock}</td></tr></table></body></html>";
    }
}
