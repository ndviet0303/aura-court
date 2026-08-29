<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\RefundRepositoryInterface;
use App\Models\Refund;
use App\Models\User;
use App\Services\Finance\FinancialPostingService;

class ProcessRefund
{
    public function __construct(
        private readonly RefundRepositoryInterface $refunds,
        private readonly FinancialPostingService $financials,
    ) {}

    public function execute(Refund $refund, array $data, ?User $processor = null): Refund
    {
        abort_unless($refund->status === 'pending', 409, 'Yêu cầu hoàn tiền đã được xử lý.');

        $this->refunds->update($refund, [
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'processed_by' => $processor?->id,
            'processed_at' => now(),
        ]);

        if ($data['status'] === 'processed') {
            $hasActiveSlots = $refund->booking->slots()->whereIn('status', ['held', 'confirmed'])->exists();
            $refund->booking()->update(['status' => $hasActiveSlots ? 'confirmed' : 'refunded']);
            $organizationId = $refund->booking->loadMissing('court.venue')->court?->venue?->organization_id;
            if ($organizationId) {
                $this->financials->postRefund($organizationId, $refund->booking->code, (int) $refund->amount, (string) $refund->id, ['legacy_refund_id' => $refund->id]);
            }
        } else {
            $hasActiveSlots = $refund->booking->slots()->whereIn('status', ['held', 'confirmed'])->exists();
            $refund->booking()->update(['status' => $hasActiveSlots ? 'confirmed' : 'cancelled']);
        }

        return $refund->fresh(['booking']);
    }
}
