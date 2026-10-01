<?php
declare(strict_types=1);

namespace Wisdom\Repositories;

use Wisdom\Core\Database;

final class ExamImportRepository
{
    public function __construct(private Database $db)
    {
    }

    public function createBatch(int $adminId, string $filename, int $total): int
    {
        return $this->db->insert(
            'INSERT INTO exam_import_batches (admin_id, original_filename, total_rows) VALUES (?, ?, ?)',
            [$adminId, function_exists('mb_substr') ? mb_substr($filename, 0, 255) : substr($filename, 0, 255), $total]
        );
    }

    public function updateBatchCounts(int $batchId, int $matched, int $pending, int $rejected): void
    {
        $this->db->execute(
            'UPDATE exam_import_batches SET matched_rows = ?, pending_rows = ?, rejected_rows = ? WHERE id = ?',
            [$matched, $pending, $rejected, $batchId]
        );
    }

    /** @param array<string,string> $raw */
    public function addPending(int $batchId, int $rowNumber, array $raw, string $reason, ?int $suggestedUserId = null): int
    {
        return $this->db->insert(
            'INSERT INTO exam_pending_reviews (batch_id, row_number, raw_data, reason, suggested_user_id) VALUES (?, ?, ?, ?, ?)',
            [$batchId, $rowNumber, json_encode($raw, JSON_THROW_ON_ERROR), function_exists('mb_substr') ? mb_substr($reason, 0, 255) : substr($reason, 0, 255), $suggestedUserId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function pendingReviews(int $limit = 200): array
    {
        return $this->db->fetchAll(
            "SELECT pr.*, b.original_filename, u.name AS suggested_name FROM exam_pending_reviews pr "
            . 'LEFT JOIN exam_import_batches b ON b.id = pr.batch_id '
            . 'LEFT JOIN users u ON u.id = pr.suggested_user_id '
            . "WHERE pr.status = 'pending' ORDER BY pr.created_at ASC LIMIT ?",
            [max(1, min(500, $limit))]
        );
    }

    public function pendingCount(): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM exam_pending_reviews WHERE status = 'pending'");
    }

    /** @return list<array<string,mixed>> */
    public function recentBatches(int $limit = 20): array
    {
        return $this->db->fetchAll(
            'SELECT b.*, u.name AS admin_name FROM exam_import_batches b LEFT JOIN users u ON u.id = b.admin_id ORDER BY b.created_at DESC LIMIT ?',
            [max(1, min(100, $limit))]
        );
    }

    /** @return array<string,mixed>|null */
    public function findPending(int $id): ?array
    {
        return $this->db->fetchRow("SELECT * FROM exam_pending_reviews WHERE id = ? AND status = 'pending' LIMIT 1", [$id]);
    }

    public function markPendingApproved(int $id, int $adminId): bool
    {
        $row = $this->db->fetchRow('SELECT batch_id FROM exam_pending_reviews WHERE id = ? AND status = \'pending\' LIMIT 1', [$id]);
        if ($row === null) return false;
        $updated = $this->db->execute(
            "UPDATE exam_pending_reviews SET status = 'approved', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'pending'",
            [$adminId, $id]
        ) === 1;
        if ($updated && $row['batch_id'] !== null) {
            $this->db->execute('UPDATE exam_import_batches SET matched_rows = matched_rows + 1, pending_rows = GREATEST(0, pending_rows - 1) WHERE id = ?', [(int) $row['batch_id']]);
        }
        return $updated;
    }

    public function markPendingRejected(int $id, int $adminId): bool
    {
        $row = $this->db->fetchRow('SELECT batch_id FROM exam_pending_reviews WHERE id = ? AND status = \'pending\' LIMIT 1', [$id]);
        if ($row === null) return false;
        $updated = $this->db->execute(
            "UPDATE exam_pending_reviews SET status = 'rejected', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'pending'",
            [$adminId, $id]
        ) === 1;
        if ($updated && $row['batch_id'] !== null) {
            $this->db->execute('UPDATE exam_import_batches SET rejected_rows = rejected_rows + 1, pending_rows = GREATEST(0, pending_rows - 1) WHERE id = ?', [(int) $row['batch_id']]);
        }
        return $updated;
    }
}
