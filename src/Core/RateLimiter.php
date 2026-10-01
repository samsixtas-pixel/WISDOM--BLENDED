<?php
declare(strict_types=1);

namespace Wisdom\Core;

/** File-backed fixed-window limiter with an exclusive lock for concurrent workers. */
final class RateLimiter
{
    public function __construct(private string $storageDir)
    {
    }

    /** @param-out int $retryAfterSeconds */
    public function attempt(string $key, int $maxHits, int $windowSeconds, ?int &$retryAfterSeconds = null): bool
    {
        if ($maxHits < 1 || $windowSeconds < 1) {
            throw new \InvalidArgumentException('Rate-limit settings must be positive.');
        }
        $retryAfterSeconds = 0;
        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0750, true) && !is_dir($this->storageDir)) {
            throw new \RuntimeException('Rate-limit storage is unavailable.');
        }

        $file = $this->storageDir . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Rate-limit storage is unavailable.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Rate-limit storage is unavailable.');
            }
            rewind($handle);
            $raw = stream_get_contents($handle);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            $now = time();
            $start = is_array($data) ? (int) ($data['start'] ?? 0) : 0;
            $hits = is_array($data) ? (int) ($data['hits'] ?? 0) : 0;
            if ($start <= 0 || $now - $start >= $windowSeconds || $now < $start) {
                $start = $now;
                $hits = 0;
            }

            $hits++;
            $retryAfterSeconds = max(1, $start + $windowSeconds - $now);
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, json_encode(['start' => $start, 'hits' => $hits], JSON_THROW_ON_ERROR)) === false) {
                throw new \RuntimeException('Rate-limit storage is unavailable.');
            }
            fflush($handle);
            chmod($file, 0640);

            return $hits <= $maxHits;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
