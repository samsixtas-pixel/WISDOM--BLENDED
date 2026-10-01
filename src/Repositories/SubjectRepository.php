<?php
declare(strict_types=1);

namespace Wisdom\Repositories;

use Wisdom\Core\Database;
use Wisdom\Models\User;

/**
 * The catalogue of programmes/subjects. Kept in the database (not hard-coded
 * in a controller) so an administrator could later manage it through SQL
 * without a code change, while the app still validates against it.
 */
final class SubjectRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<string> */
    public function levels(): array
    {
        return User::LEVELS;
    }

    /** @return array<string,list<string>> level => subjects */
    public function catalogue(): array
    {
        $rows = $this->db->fetchAll('SELECT level, name FROM subjects ORDER BY level, name');
        $catalogue = array_fill_keys(User::LEVELS, []);
        foreach ($rows as $row) {
            $catalogue[(string) $row['level']][] = (string) $row['name'];
        }

        return $catalogue;
    }

    /** @return list<string> */
    public function forLevel(string $level): array
    {
        return $this->catalogue()[$level] ?? [];
    }

    public function rateForLevel(string $level, array $fees): int
    {
        return $level === 'CPSP II' ? (int) $fees['advanced_rate'] : (int) $fees['standard_rate'];
    }
}
