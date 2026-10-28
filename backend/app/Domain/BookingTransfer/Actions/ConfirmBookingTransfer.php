<?php

namespace App\Domain\BookingTransfer\Actions;

use App\Contracts\Repositories\BookingTransferListingRepositoryInterface;
use App\Contracts\Repositories\BookingTransferRepositoryInterface;
use App\Domain\BookingTransfer\Rules\TransferEligibility;
use App\Models\BookingTransfer;
use App\Models\BookingTransferItem;
use App\Models\User;
use App\Services\Reservation\ReservationIntake;
use Illuminate\Support\Facades\DB;

/**
 * FR-145..FR-147, UC-9.9, BR-186..BR-191: after checking the money themselves, the seller confirms. Every booking is
 * checked again under row locks; then, in one transaction, the bookings and their entitlements move to the buyer with
 * a new check-in code, the listing marks them transferred and the parties and owners are told. A repeat on a
 * completed transfer returns it as stored (BR-189). The booking's own amounts never change (BR-187).
 */
class ConfirmBookingTransfer
{
    public function __construct(
        private readonly BookingTransferRepositoryInterface $transfers,
        private readonly BookingTransferListingRepositoryInterface $listings,
        private readonly TransferEligibility $eligibility,
        private readonly ReservationIntake $intake,
        private readonly NotifyTransferParties $notify,
    ) {}

    public function execute(BookingTransfer $transfer, User $actor): BookingTransfer
    {
        if ((int) $transfer->seller_id !== (int) $actor->id) {
            TransferEligibility::fail('TRANSFER_FORBIDDEN', 'Chỉ người chuyển nhượng được xác nhận giao dịch.', 403);
        }

        $transfer = DB::transaction(function () use ($transfer, $actor): BookingTransfer {
            $locked = $this->transfers->lock($transfer);
            if ($locked->status === BookingTransfer::STATUS_COMPLETED) {
                return $locked;
            }
            if ($locked->status !== BookingTransfer::STATUS_PENDING) {
                TransferEligibility::fail('TRANSFER_NOT_IN_TRANSACTION', 'Giao dịch đã bị huỷ, không thể xác nhận chuyển nhượng.', 409);
            }
            $this->eligibility->assertActor($actor);
            $buyer = $locked->buyer;
            $this->eligibility->assertBuyer($actor, $buyer);

            $ids = $locked->items->pluck('reservation_id')->map(fn ($id): int => (int) $id)->all();
            $reservations = $this->transfers->reservations($ids, true);
            $refusals = [];
            foreach ($locked->items as $item) {
                /** @var BookingTransferItem $item */
                $refusals[$item->reservation_id] = $this->eligibility->refusal($reservations->get($item->reservation_id), $actor, (int) $item->price);
            }
            TransferEligibility::failIfRefused($refusals);

            $holder = $this->intake->booker($buyer);
            foreach ($reservations as $reservation) {
                $this->transfers->moveHolding($reservation, (int) $buyer->id, $holder);
            }
            $this->transfers->close($locked, BookingTransfer::STATUS_COMPLETED, ['confirmed_at' => now()]);
            $this->listings->markTransferred($ids);
            $this->notify->execute($this->transfers->withDetails($locked), NotifyTransferParties::COMPLETED);

            return $locked;
        });

        return $this->transfers->withDetails($transfer);
    }
}
