<?php
declare(strict_types=1);

namespace Wisdom\Repositories;

use Wisdom\Contracts\RepositoryInterface;
use Wisdom\Core\Database;
use Wisdom\Core\Reference;
use Wisdom\Models\Payment;

final class PaymentRepository implements RepositoryInterface
{
    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?Payment
    {
        $row = $this->db->fetchRow('SELECT * FROM payments WHERE id = ? LIMIT 1', [$id]);

        return $row === null ? null : Payment::fromRow($row);
    }

    public function create(int $userId, string $proofPath, int $amount, string $type, string $category): int
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return $this->db->insert(
                    'INSERT INTO payments (user_id, reference, proof_path, amount, payment_type, category, status) VALUES (?, ?, ?, ?, ?, ?, \'pending\')',
                    [$userId, Reference::generate(), $proofPath, $amount, $type, $category]
                );
            } catch (\mysqli_sql_exception $exception) {
                if ($exception->getCode() !== 1062 || $attempt === 4) {
                    throw $exception;
                }
            }
        }
        throw new \RuntimeException('Could not allocate a payment reference.');
    }

    /** @return list<array<string,mixed>> */
    public function forUser(int $userId): array
    {
        return $this->db->fetchAll('SELECT * FROM payments WHERE user_id = ? ORDER BY submitted_at DESC', [$userId]);
    }

    public function hasApprovedFor(int $userId, string $category): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM payments WHERE user_id = ? AND category = ? AND status = \'approved\'',
            [$userId, $category]
        ) > 0;
    }

    /** Joined with the payer's name/email for the admin table. */
    public function allWithPayer(int $limit = 200): array
    {
        return $this->db->fetchAll(
            'SELECT payments.*, users.name, users.email FROM payments '
            . 'JOIN users ON users.id = payments.user_id '
            . 'ORDER BY payments.submitted_at DESC LIMIT ?',
            [$limit]
        );
    }

    public function paginate(\Wisdom\Core\ListQuery $query): array
    {
        $sorts = ['id' => 'payments.id', 'submitted_at' => 'payments.submitted_at', 'amount' => 'payments.amount', 'status' => 'payments.status', 'category' => 'payments.category'];
        $sort = $sorts[$query->sort] ?? 'payments.submitted_at';
        $dir = $query->dir === 'asc' ? 'ASC' : 'DESC';
        $clauses = [];
        $params = [];
        $status = $query->filter('status');
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) { $clauses[] = 'payments.status = ?'; $params[] = $status; }
        $category = $query->filter('category');
        if (in_array($category, ['programme', 'examination'], true)) { $clauses[] = 'payments.category = ?'; $params[] = $category; }
        $search = trim($query->filter('q'));
        if ($search !== '') { $like = '%' . Database::escapeLike($search) . '%'; $clauses[] = '(payments.reference LIKE ? ESCAPE \'\\\\\' OR users.name LIKE ? ESCAPE \'\\\\\' OR users.email LIKE ? ESCAPE \'\\\\\')'; array_push($params, $like, $like, $like); }
        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
        $total = (int) $this->db->fetchValue('SELECT COUNT(*) FROM payments JOIN users ON users.id = payments.user_id' . $where, $params);
        $rows = $this->db->fetchAll('SELECT payments.*, users.name, users.email FROM payments JOIN users ON users.id = payments.user_id' . $where . " ORDER BY {$sort} {$dir} LIMIT ? OFFSET ?", array_merge($params, [$query->perPage, $query->offset()]));
        return ['rows' => $rows, 'total' => $total, 'page' => $query->page, 'perPage' => $query->perPage];
    }

    public function countPending(): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM payments WHERE status = 'pending'");
    }

    public function dailySubmissionCounts(int $days = 12): array
    {
        $rows = $this->db->fetchAll('SELECT DATE(submitted_at) AS d, COUNT(*) AS c FROM payments WHERE submitted_at >= (CURRENT_DATE - INTERVAL ? DAY) GROUP BY DATE(submitted_at) ORDER BY d ASC', [$days - 1]);
        $map = [];
        foreach ($rows as $row) $map[(string) $row['d']] = (int) $row['c'];
        $result = [];
        $today = new \DateTimeImmutable('today');
        for ($i = $days - 1; $i >= 0; $i--) { $date = $today->sub(new \DateInterval('P' . $i . 'D'))->format('Y-m-d'); $result[$date] = $map[$date] ?? 0; }
        return $result;
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->db->execute(
            'UPDATE payments SET status = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = \'pending\'',
            [$status, $id]
        ) > 0;
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM payments');
    }

    public function findApprovedAwaitingCleanup(int $hours = 24, int $limit = 100): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM payments WHERE status = 'approved' AND reviewed_at IS NOT NULL AND reviewed_at <= (NOW() - INTERVAL ? HOUR) AND proof_deleted_at IS NULL ORDER BY reviewed_at ASC LIMIT ?",
            [$hours, $limit]
        );
        return array_map(static fn (array $row): Payment => Payment::fromRow($row), $rows);
    }

    public function markProofDeleted(int $id): void
    {
        $this->db->execute('UPDATE payments SET proof_deleted_at = CURRENT_TIMESTAMP WHERE id = ?', [$id]);
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM payments WHERE id = ?', [$id]) > 0;
    }
}
