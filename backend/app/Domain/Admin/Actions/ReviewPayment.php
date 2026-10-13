<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use App\Models\User;

class ReviewPayment
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
    ) {}

    public function execute(Payment $payment, array $data, ?User $reviewer = null): Payment
    {
        abort_unless($payment->status === 'manual_review', 409, 'Giao dịch đã được xử lý.');

        $this->payments->update($payment, [
            'status' => $data['status'],
            'payload' => array_merge($payment->payload ?? [], [
                'review_note' => $data['note'] ?? null,
                'reviewed_at' => now()->toIso8601String(),
                'reviewed_by' => $reviewer?->id,
            ]),
        ]);

        return $payment->fresh(['booking']);
    }
}
