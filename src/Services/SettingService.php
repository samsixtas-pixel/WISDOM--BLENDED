<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Models\User;
use Wisdom\Repositories\AuditLogRepository;
use Wisdom\Repositories\SettingRepository;

final class SettingService
{
    public const KEY_BANK_NUMBER = 'payment.bank_number';
    public const KEY_LIPA_NUMBER = 'payment.lipa_number';
    /** @var array<string,string> */
    private array $cache = [];

    public function __construct(
        private SettingRepository $settings,
        private AuditLogRepository $audit,
    ) {
    }

    public function bankNumber(): string
    {
        return $this->get(self::KEY_BANK_NUMBER);
    }

    public function lipaNumber(): string
    {
        return $this->get(self::KEY_LIPA_NUMBER);
    }

    /** @param array<string,string> $values */
    public function update(User $admin, array $values): void
    {
        foreach ($values as $key => $value) {
            $this->settings->set($key, trim($value));
            $this->cache[$key] = trim($value);
        }
        $this->audit->record($admin->getId(), 'settings.updated', implode(',', array_keys($values)));
    }

    private function get(string $key): string
    {
        return $this->cache[$key] ??= $this->settings->get($key, '');
    }
}