<?php

namespace App\Domain\BookingTransfer\Actions;

use App\Contracts\Repositories\BookingTransferListingRepositoryInterface;
use App\Contracts\Repositories\ConversationRepositoryInterface;
use App\Domain\BookingTransfer\Rules\TransferEligibility;
use App\Domain\Chat\Actions\NotifyChatRecipients;
use App\Domain\Chat\Actions\OpenConversation;
use App\Domain\Chat\DTOs\OpenConversationData;
use App\Models\BookingTransferListing;
use App\Models\BookingTransferListingItem;
use App\Models\Conversation;
use App\Models\User;

/**
 * FR-142, UC-9.6, BR-182: "Liên hệ nhận booking" opens the pair's chat with the seller and posts the chosen bookings
 * as a read-only card (D11). Nothing is held and no payment window starts.
 */
class ContactTransferListing
{
    public function __construct(
        private readonly BookingTransferListingRepositoryInterface $listings,
        private readonly ConversationRepositoryInterface $conversations,
        private readonly OpenConversation $open,
        private readonly NotifyChatRecipients $notify,
        private readonly TransferEligibility $eligibility,
    ) {}

    /** @param  list<int>  $reservationIds */
    public function execute(BookingTransferListing $listing, User $actor, array $reservationIds): Conversation
    {
        // PRE-1: the one who asks for a booking meets the same account conditions as the parties to a transfer.
        $this->eligibility->assertActor($actor);
        if ((int) $listing->seller_id === (int) $actor->id) {
            TransferEligibility::fail('TRANSFER_SELF', 'Bạn không thể liên hệ tin chuyển nhượng của chính mình.');
        }
        $items = $this->listings->withDetails($listing)->items->whereIn('reservation_id', $reservationIds);
        if ($items->count() !== count($reservationIds)) {
            TransferEligibility::fail('TRANSFER_ITEM_NOT_IN_LISTING', 'Lượt đã chọn không thuộc tin chuyển nhượng này.');
        }
        // Bước 3: only bookings still on offer take contacts; held, withdrawn, transferred or past the cutoff do not.
        TransferEligibility::failIfRefused($items->mapWithKeys(fn (BookingTransferListingItem $item): array => [
            (int) $item->reservation_id => $item->state() === 'available'
                ? null
                : ['code' => 'TRANSFER_NOT_AVAILABLE', 'message' => 'Lượt này không còn nhận liên hệ.'],
        ])->all());

        $conversation = $this->open->execute($actor, new OpenConversationData(Conversation::TYPE_CUSTOMER, (int) $listing->seller_id, null, null));
        $message = $this->conversations->addMessage($conversation, [
            'sender_user_id' => $actor->id,
            'sender_side' => Conversation::SIDE_CUSTOMER,
            'transfer' => [
                'listing_id' => (int) $listing->id,
                'items' => $items->values()->map(fn (BookingTransferListingItem $item): array => [
                    'reservation_id' => (int) $item->reservation_id,
                    'venue_name' => $item->reservation?->court?->venue?->name,
                    'court_name' => $item->reservation?->court?->code,
                    'starts_at' => $item->reservation?->starts_at?->toIso8601String(),
                    'ends_at' => $item->reservation?->ends_at?->toIso8601String(),
                    'price' => (int) $item->price,
                ])->all(),
            ],
        ]);
        $this->notify->execute($conversation, $message);

        return $conversation;
    }
}
