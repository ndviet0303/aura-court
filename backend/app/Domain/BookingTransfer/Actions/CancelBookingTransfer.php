<?php

namespace App\Domain\BookingTransfer\Actions;

use App\Contracts\Repositories\BookingTransferRepositoryInterface;
use App\Domain\BookingTransfer\Rules\TransferEligibility;
use App\Models\BookingTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** D9: either party cancels a pending transfer, which frees its bookings; the other party is told. */
class CancelBookingTransfer
{
    public function __construct(
        private readonly BookingTransferRepositoryInterface $transfers,
        private readonly NotifyTransferParties $notify,
    ) {}

    public function execute(BookingTransfer $transfer, User $actor): BookingTransfer
    {
        if (! $transfer->isParty($actor)) {
            TransferEligibility::fail('TRANSFER_FORBIDDEN', 'Bạn không tham gia giao dịch chuyển nhượng này.', 403);
        }

        $transfer = DB::transaction(function () use ($transfer, $actor): BookingTransfer {
            $locked = $this->transfers->lock($transfer);
            if ($locked->status === BookingTransfer::STATUS_CANCELLED) {
                return $locked;
            }
            if ($locked->status !== BookingTransfer::STATUS_PENDING) {
                TransferEligibility::fail('TRANSFER_NOT_IN_TRANSACTION', 'Giao dịch đã hoàn tất, không thể huỷ.', 409);
            }
            $this->transfers->close($locked, BookingTransfer::STATUS_CANCELLED, ['cancelled_at' => now(), 'cancelled_by' => $actor->id]);
            $this->notify->execute($this->transfers->withDetails($locked), NotifyTransferParties::CANCELLED, $actor);

            return $locked;
        });

        return $this->transfers->withDetails($transfer);
    }
}
