<?php
declare(strict_types=1);

namespace Wisdom\Repositories;

use Wisdom\Contracts\RepositoryInterface;
use Wisdom\Core\Database;
use Wisdom\Models\Admin;
use Wisdom\Models\Student;
use Wisdom\Models\User;

/** All SQL for the users table lives here — nowhere else builds this SQL. */
final class UserRepository implements RepositoryInterface
{
    private const COLUMNS = 'id, name, email, avatar, sex, password, level, subjects, role, is_approved, is_active, terms_accepted_at, results_ack_at, force_password_reset, created_at';

    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?User
    {
        $row = $this->db->fetchRow('SELECT ' . self::COLUMNS . ' FROM users WHERE id = ? LIMIT 1', [$id]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->fetchRow('SELECT ' . self::COLUMNS . ' FROM users WHERE email = ? LIMIT 1', [$email]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function emailExists(string $email): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM users WHERE email = ? LIMIT 1', [$email]) !== null;
    }

    /** @param list<string> $subjects */
    public function create(string $name, string $email, string $sex, string $passwordHash, string $level, array $subjects): int
    {
        return $this->db->insert(
            'INSERT INTO users (name, email, sex, password, level, subjects, role, is_approved, is_active, terms_accepted_at) '
            . "VALUES (?, ?, ?, ?, ?, ?, 'student', 0, 1, CURRENT_TIMESTAMP)",
            [$name, $email, $sex, $passwordHash, $level, json_encode($subjects, JSON_THROW_ON_ERROR)]
        );
    }

    public function createTeamMember(string $name, string $email, string $passwordHash, string $role): int
    {
        return $this->db->insert(
            "INSERT INTO users (name, email, sex, password, level, subjects, role, is_approved, is_active) "
            . "VALUES (?, ?, 'prefer not to say', ?, 'CPSP I', '[]', ?, 1, 1)",
            [$name, $email, $passwordHash, $role]
        );
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $this->db->execute('UPDATE users SET password = ? WHERE id = ?', [$hash, $id]);
    }

    public function updateAvatar(int $id, ?string $filename): void
    {
        $this->db->execute('UPDATE users SET avatar = ? WHERE id = ?', [$filename, $id]);
    }

    public function setForcePasswordReset(int $id, bool $flag): void
    {
        $this->db->execute('UPDATE users SET force_password_reset = ? WHERE id = ?', [(int) $flag, $id]);
    }

    public function setPasswordAndClearReset(int $id, string $hash): void
    {
        $this->db->execute('UPDATE users SET password = ?, force_password_reset = 0 WHERE id = ?', [$hash, $id]);
    }

    public function setPasswordForcedReset(int $id, string $hash): void
    {
        $this->db->execute('UPDATE users SET password = ?, force_password_reset = 1 WHERE id = ?', [$hash, $id]);
    }

    /** @param list<string> $subjects */
    public function updateProfile(int $id, string $name, string $sex, array $subjects): void
    {
        $this->db->execute('UPDATE users SET name = ?, sex = ?, subjects = ? WHERE id = ? AND role = \'student\'', [$name, $sex, json_encode($subjects, JSON_THROW_ON_ERROR), $id]);
    }

    public function setResultsAck(int $userId): void
    {
        $this->db->execute('UPDATE users SET results_ack_at = CURRENT_TIMESTAMP WHERE id = ?', [$userId]);
    }

    public function countNewResultsFor(int $userId): int
    {
        $ack = $this->db->fetchValue('SELECT results_ack_at FROM users WHERE id = ?', [$userId]);
        if ($ack === null) return (int) $this->db->fetchValue("SELECT COUNT(*) FROM exams WHERE user_id = ? AND status = 'published'", [$userId]);
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM exams WHERE user_id = ? AND status = 'published' AND created_at > ?", [$userId, (string) $ack]);
    }

    public function setApproved(int $id, bool $approved): void
    {
        $this->db->execute('UPDATE users SET is_approved = ? WHERE id = ?', [(int) $approved, $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->execute('UPDATE users SET is_active = ? WHERE id = ?', [(int) $active, $id]);
    }

    /**
     * Live search across name/email/level for the admin panel.
     * All wildcards are escaped and the term is always bound as a parameter.
     *
     * @return list<User>
     */
    public function search(string $term, int $limit = 100): array
    {
        $term = trim($term);
        if ($term === '') {
            return $this->all($limit);
        }

        $limit = max(1, min(200, $limit));
        $words = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = [];
        foreach ($words as $word) {
            $clean = trim((string) preg_replace('/[+\-><()~*"@]+/', ' ', $word));
            if (str_len($clean) >= 3) {
                $parts[] = '+' . $clean . '*';
            }
        }

        if ($parts !== []) {
            try {
                $rows = $this->db->fetchAll(
                    'SELECT ' . self::COLUMNS . ' FROM users '
                    . 'WHERE role = \'student\' AND MATCH(name, email) AGAINST (? IN BOOLEAN MODE) '
                    . 'ORDER BY created_at DESC LIMIT ?',
                    [implode(' ', $parts), $limit]
                );
                if ($rows !== []) {
                    return array_map(fn (array $row): User => $this->hydrate($row), $rows);
                }
            } catch (\Throwable $exception) {
                error_log('FULLTEXT search fallback: ' . $exception->getMessage());
            }
        }

        $like = '%' . Database::escapeLike($term) . '%';
        $rows = $this->db->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM users '
            . 'WHERE role = \'student\' AND (name LIKE ? ESCAPE \'\\\\\' OR email LIKE ? ESCAPE \'\\\\\' OR level LIKE ? ESCAPE \'\\\\\') '
            . 'ORDER BY created_at DESC LIMIT ?',
            [$like, $like, $like, $limit]
        );

        return array_map(fn (array $row): User => $this->hydrate($row), $rows);
    }

    public function paginate(\Wisdom\Core\ListQuery $query): array
    {
        $sorts = ['id' => 'users.id', 'name' => 'users.name', 'email' => 'users.email', 'created_at' => 'users.created_at', 'role' => 'users.role', 'level' => 'users.level'];
        $sort = $sorts[$query->sort] ?? 'users.created_at';
        $dir = $query->dir === 'asc' ? 'ASC' : 'DESC';
        $clauses = [];
        $params = [];
        $role = $query->filter('role');
        if (in_array($role, [User::ROLE_STUDENT, User::ROLE_ADMIN, User::ROLE_SECRETARY], true)) { $clauses[] = 'users.role = ?'; $params[] = $role; }
        $level = $query->filter('level');
        if (in_array($level, User::LEVELS, true)) { $clauses[] = 'users.level = ?'; $params[] = $level; }
        $status = $query->filter('status');
        if ($status === 'active') $clauses[] = 'users.is_active = 1';
        if ($status === 'suspended') $clauses[] = 'users.is_active = 0';
        if ($status === 'approved') $clauses[] = 'users.is_approved = 1';
        if ($status === 'pending') $clauses[] = 'users.is_approved = 0';
        $search = trim($query->filter('q'));
        if ($search !== '') { $like = '%' . Database::escapeLike($search) . '%'; $clauses[] = '(users.name LIKE ? ESCAPE \'\\\\\' OR users.email LIKE ? ESCAPE \'\\\\\')'; $params[] = $like; $params[] = $like; }
        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
        $total = (int) $this->db->fetchValue('SELECT COUNT(*) FROM users' . $where, $params);
        $rows = $this->db->fetchAll('SELECT ' . self::COLUMNS . ' FROM users' . $where . " ORDER BY {$sort} {$dir} LIMIT ? OFFSET ?", array_merge($params, [$query->perPage, $query->offset()]));
        return ['rows' => array_map(fn (array $row): User => $this->hydrate($row), $rows), 'total' => $total, 'page' => $query->page, 'perPage' => $query->perPage];
    }

    /** @return list<User> */
    public function all(int $limit = 500): array
    {
        $rows = $this->db->fetchAll('SELECT ' . self::COLUMNS . ' FROM users WHERE role = \'student\' ORDER BY created_at DESC LIMIT ?', [$limit]);

        return array_map(fn (array $row): User => $this->hydrate($row), $rows);
    }

    /** @return list<User> */
    public function staff(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE role IN (?, ?) ORDER BY created_at DESC',
            [User::ROLE_ADMIN, User::ROLE_SECRETARY]
        );

        return array_map(fn (array $row): User => $this->hydrate($row), $rows);
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM users');
    }

    public function countPendingApproval(): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM users WHERE is_approved = 0 AND role = 'student'");
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM users WHERE id = ? AND role = \'student\'', [$id]) > 0;
    }

    private function hydrate(array $row): User
    {
        $subjects = array_values(array_filter((array) json_decode((string) $row['subjects'], true) ?: [], 'is_string'));

        return User::fromRow($row, $subjects);
    }
}
