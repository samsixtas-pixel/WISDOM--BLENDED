<?php
declare(strict_types=1);

namespace Wisdom\Core;

final class Reference
{
    private const POOL = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generate(string $prefix = 'WIS'): string
    {
        $suffix = '';
        for ($i = 0; $i < 6; $i++) {
            $suffix .= self::POOL[random_int(0, strlen(self::POOL) - 1)];
        }
        return sprintf('%s-%s-%s', $prefix, date('Y'), $suffix);
    }
}