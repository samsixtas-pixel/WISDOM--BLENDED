<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Core\AppException;
use Wisdom\Core\Database;
use Wisdom\Models\Payment;
use Wisdom\Models\User;
use Wisdom\Repositories\AuditLogRepository;
use Wisdom\Repositories\PaymentRepository;
use Wisdom\Repositories\UserRepository;

/** Administrator review of submitted payment proofs. */
final class PaymentService
{
    public function __construct(
        private Database $db,
        private PaymentRepository $payments,
        private UserRepository $users,
        private AuditLogRepository $audit,
    ) {
    }

    /** @throws AppException */
    public function review(User $admin, int $paymentId, string $status): void
    {
        if (!in_array($status, [Payment::STATUS_APPROVED, Payment::STATUS_REJECTED], true)) {
            throw new AppException('Choose approve or reject.');
        }

        $payment = $this->payments->find($paymentId);
        if ($payment === null || !$payment->isPending()) {
            throw new AppException('That payment has already been reviewed.');
        }

        $this->db->transaction(function () use ($payment, $paymentId, $status): void {
            if (!$this->payments->updateStatus($paymentId, $status)) {
                throw new AppException('This payment was just reviewed by another administrator. Refresh and check its current status.');
            }
            if ($payment->isProgrammeFee()) {
                $this->users->setApproved($payment->getUserId(), $status === Payment::STATUS_APPROVED);
            }
        });

        $this->audit->record($admin->getId(), 'payment.reviewed', "payment #$paymentId -> $status");
    }

    /** @return list<array<string,mixed>> */
    public function allWithPayer(int $limit = 200): array
    {
        return $this->payments->allWithPayer($limit);
    }

    public function allPaged(\Wisdom\Core\ListQuery $query): array
    {
        return $this->payments->paginate($query);
    }

    public function pendingCount(): int
    {
        return $this->payments->countPending();
    }

    public function dailySubmissionCounts(int $days = 12): array
    {
        return $this->payments->dailySubmissionCounts($days);
    }

    /** Safely resolve a stored proof to an absolute filesystem path, or null if missing. */
    public function proofPath(FileUploader $uploader, int $paymentId): ?array
    {
        $payment = $this->payments->find($paymentId);
        if ($payment === null) {
            return null;
        }
        if ($payment->isProofDeleted()) {
            return null;
        }
        $path = $uploader->pathFor($payment->getProofPath());

        return is_file($path) ? ['path' => $path, 'payment' => $payment] : null;
    }

    public function cleanupOldProofs(FileUploader $uploader, int $hours = 24): int
    {
        $removed = 0;
        foreach ($this->payments->findApprovedAwaitingCleanup($hours) as $payment) {
            $path = $uploader->pathFor($payment->getProofPath());
            if (is_file($path) && !unlink($path)) {
                continue;
            }
            $this->payments->markProofDeleted($payment->getId());
            $removed++;
        }
        return $removed;
    }
}
