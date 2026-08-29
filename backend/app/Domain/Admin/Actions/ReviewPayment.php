<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentService;

class ReviewPayment
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly PaymentService $paymentsService,
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

        if ($data['status'] === 'paid' && $payment->booking && $payment->booking->status === 'pending_payment' && $payment->amount >= $payment->booking->total_amount && $payment->booking->payment_expires_at?->isFuture()) {
            $payment->booking()->update(['status' => 'confirmed']);
            $payment->booking->slots()->where('status', 'held')->update(['status' => 'confirmed']);
            $this->paymentsService->notifyConfirmed($payment->booking->fresh(['user', 'court']));
        }

        return $payment->fresh(['booking']);
    }
}
