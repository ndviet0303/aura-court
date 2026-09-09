<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Services\Booking\DiscountService;
use App\Services\Booking\WaitlistService;
use App\Services\Finance\FinancialPostingService;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelBooking
{
    public function __construct(
        private readonly DiscountService $discounts,
        private readonly WaitlistService $waitlists,
        private readonly WalletPaymentService $walletPayments,
        private readonly FinancialPostingService $financials,
    ) {}

    public function execute(Booking $booking, ?string $reason = null): Booking
    {
        if (in_array($booking->status, ['cancelled', 'expired', 'refunded'], true)) {
            return $booking->load(['court', 'slots', 'refunds']);
        }
        $firstSlot = $booking->slots()->orderBy('starts_at')->firstOrFail();
        if ($booking->status === 'confirmed' && now()->greaterThan($firstSlot->starts_at->copy()->subHours(2))) {
            abort(409, 'Booking chỉ được hủy trước giờ chơi ít nhất 2 giờ.');
        }

        $slotRefs = $booking->slots()->whereIn('status', ['held', 'confirmed'])->get()->map(fn (BookingSlot $slot) => [$slot->court_id, $slot->starts_at])->all();
        $wasPending = $booking->status === 'pending_payment';
        $wasConfirmed = $booking->status === 'confirmed';

        $result = DB::transaction(function () use ($booking, $reason, $wasPending, $wasConfirmed) {
            $newStatus = $wasConfirmed ? 'refunded' : 'cancelled';
            $booking->update(['status' => $newStatus, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $booking->slots()->whereIn('status', ['held', 'confirmed'])->update(['status' => 'cancelled']);

            // Sync cancellation to mapped V2 reservations
            foreach ($booking->legacyMapping as $mapping) {
                $mapping->reservation?->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                $mapping->reservation?->slots()->whereIn('status', ['held', 'confirmed'])->update(['status' => 'cancelled']);
                $mapping->reservation?->entitlements()->whereIn('status', ['held', 'active'])->update(['status' => 'cancelled']);
            }
            if ($wasConfirmed && (int) $booking->total_amount > 0) {
                $booking->loadMissing('court.venue');
                $orgId = $booking->court?->venue?->organization_id;
                $wallet = $this->walletPayments->getOrCreateWallet($booking->user_id, $orgId);
                $this->walletPayments->credit(
                    $wallet,
                    (int) $booking->total_amount,
                    'booking_refund',
                    $booking->id,
                    "Refund for {$booking->code}"
                );
                $this->financials->postWalletBookingRefund($booking->fresh(['court.venue']), (int) $booking->total_amount, $wallet->id);
            }

            if ($wasPending && $booking->coupon_id && ! $booking->coupon_usage_released_at) {
                $this->discounts->release($booking->coupon);
                $booking->update(['coupon_usage_released_at' => now()]);
            }

            return $booking->fresh(['court', 'slots', 'refunds']);
        });

        foreach ($slotRefs as [$courtId, $startsAt]) {
            $this->waitlists->notifyForSlot($courtId, $startsAt);
        }

        return $result;
    }
}
