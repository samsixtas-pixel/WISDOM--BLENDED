<?php
declare(strict_types=1);

namespace Wisdom\Repositories;

use Wisdom\Core\Database;

final class SettingRepository
{
    public function __construct(private Database $db)
    {
    }

    public function get(string $key, string $default = ''): string
    {
        $value = $this->db->fetchValue(
            'SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1',
            [$key]
        );

        return $value === null ? $default : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        $this->db->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $value]
        );
    }
}