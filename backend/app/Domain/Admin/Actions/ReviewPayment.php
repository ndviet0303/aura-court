<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Booking;
use App\Models\LegacyBookingMapping;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Payment\PaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;

class ReviewPayment
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly PaymentService $paymentsService,
        private readonly LegacyBookingBackfillService $legacyBackfill,
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

        $booking = $payment->booking;
        if ($data['status'] === 'paid' && $booking instanceof Booking && $booking->status === 'pending_payment' && $payment->amount >= $booking->total_amount && $booking->payment_expires_at?->isFuture()) {
            $payment->booking()->update(['status' => 'confirmed']);
            $booking->slots()->where('status', 'held')->update(['status' => 'confirmed']);
            $this->confirmMirror($booking);
            $this->paymentsService->notifyConfirmed($booking->fresh(['user', 'court']));
        }

        return $payment->fresh(['booking']);
    }

    /** Phase A readers use the V2 mirror, so a V1 confirmation confirms it too (same steps as ReservationService::confirm). */
    private function confirmMirror(Booking $booking): void
    {
        $mapping = $booking->legacyMapping()->withoutGlobalScope('tenant_isolation')->first();
        $reservation = $mapping instanceof LegacyBookingMapping ? $mapping->reservation()->withoutGlobalScope('tenant_isolation')->first() : null;
        if ($reservation instanceof Reservation && $reservation->status === 'held') {
            $reservation->forceFill(['status' => 'confirmed', 'confirmed_at' => now()])->save();
            $reservation->slots()->withoutGlobalScope('tenant_isolation')->where('status', 'held')->update(['status' => 'confirmed']);
            $reservation->entitlements()->withoutGlobalScope('tenant_isolation')->where('status', 'held')->update(['status' => 'active']);
        }
        $this->legacyBackfill->syncProjection($booking->fresh());
    }
}
