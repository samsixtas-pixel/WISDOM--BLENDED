<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Core\AppException;
use Wisdom\Models\Payment;
use Wisdom\Models\User;
use Wisdom\Repositories\AuditLogRepository;
use Wisdom\Repositories\PaymentRepository;

/** Fee computation and payment-proof submission for a student. */
final class FeeService
{
    public function __construct(
        private PaymentRepository $payments,
        private FileUploader $uploader,
        private AuditLogRepository $audit,
        private array $rates,
    ) {
    }

    public function rateFor(User $user): int
    {
        return $user->getLevel() === 'CPSP II' ? (int) $this->rates['advanced_rate'] : (int) $this->rates['standard_rate'];
    }

    public function totalFor(User $user): int
    {
        return count($user->getSubjects()) * $this->rateFor($user);
    }

    public function isFullyPaid(User $user): bool
    {
        return $this->payments->hasApprovedFor($user->getId(), 'programme')
            && $this->payments->hasApprovedFor($user->getId(), 'examination');
    }

    public function halfFor(User $user): int
    {
        return intdiv($this->totalFor($user), 2);
    }

    /**
     * @param array{name?:string,tmp_name?:string,error?:int,size?:int}|null $file
     * @throws AppException
     */
    public function submit(User $user, string $category, string $paymentType, ?array $file): void
    {
        if (!in_array($category, Payment::CATEGORIES, true)) {
            throw new AppException('Choose the type of fees you are paying.');
        }
        if (!in_array($paymentType, Payment::TYPES, true)) {
            throw new AppException('Choose a full or half payment.');
        }
        if ($this->totalFor($user) === 0) {
            throw new AppException('Your subjects are not available. Please sign in again.');
        }

        $amount = $paymentType === 'half' ? $this->halfFor($user) : $this->totalFor($user);
        $storedFilename = $this->uploader->store($file);

        $id = $this->payments->create($user->getId(), $storedFilename, $amount, $paymentType, $category);
        $this->audit->record($user->getId(), 'payment.submitted', "payment #$id, $category, $paymentType");
    }

    /** @return list<array<string,mixed>> */
    public function historyFor(User $user): array
    {
        return $this->payments->forUser($user->getId());
    }
}
