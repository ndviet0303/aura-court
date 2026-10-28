<?php

namespace Tests\Feature\Module08;

use App\Models\AffectedReservationCase;
use App\Models\BookingTransfer;
use App\Models\BookingTransferItem;
use App\Models\BookingTransferListingItem;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Court;
use App\Models\OutboxMessage;
use App\Models\OwnerPaymentAttempt;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BooksCourts;
use Tests\Feature\Module02\Concerns\SeedsBookings;
use Tests\TestCase;

/** Module 08.2 FR-139..FR-147, BR-176..BR-192: customers transfer whole bookings to each other; the seller confirms. */
class BookingTransferTest extends TestCase
{
    use BooksCourts;
    use RefreshDatabase;
    use SeedsBookings;

    private const TZ = 'Asia/Ho_Chi_Minh';

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00', self::TZ));
        config(['features.flags.marketplace_slot_resale' => true, 'features.pilot_organizations' => ['*']]);
        $this->court = $this->bookableCourt();
    }

    public function test_fr_139_candidates_are_my_upcoming_bookings_with_cap_group_and_reason(): void
    {
        $seller = $this->customer();
        $open = $this->owned($seller, '2026-11-05 18:00', ['recurring_group' => 'g-1']);
        $closing = $this->owned($seller, '2026-11-02 10:30');
        $this->owned($seller, '2026-11-01 18:00');
        $this->owned($this->customer(), '2026-11-06 18:00');

        $rows = $this->actingAs($seller)->getJson('/api/booking-transfers/candidates')->assertOk()->json('data');

        $this->assertSame([$closing->id, $open->id], array_column($rows, 'reservation_id'));
        $this->assertSame(['code' => 'TRANSFER_CUTOFF', 'message' => 'Đã đến mốc ngừng chuyển nhượng (2 giờ trước giờ bắt đầu).'], $rows[0]['refusal']);
        $this->assertFalse($rows[0]['eligible']);
        $this->assertTrue($rows[1]['eligible']);
        $this->assertSame(100000, $rows[1]['max_price']);
        $this->assertSame(100000, $rows[1]['paid_amount']);
        $this->assertSame('g-1', $rows[1]['recurring_group']);
        $this->assertSame($this->court->code, $rows[1]['court']['name']);
        $this->assertSame($this->court->venue->name, $rows[1]['venue']['name']);
    }

    public function test_br_176_conditions_are_checked_when_posting_and_nothing_is_posted(): void
    {
        $seller = $this->customer();
        $ok = $this->owned($seller, '2026-11-05 18:00');
        $held = $this->owned($seller, '2026-11-05 20:00', status: 'held');
        $disputed = $this->owned($seller, '2026-11-06 18:00', ['is_disputed' => true]);
        $affected = $this->owned($seller, '2026-11-06 20:00');
        AffectedReservationCase::create([
            'reservation_id' => $affected->id, 'venue_id' => $this->court->venue_id, 'court_id' => $this->court->id,
            'source_type' => AffectedReservationCase::SOURCE_OPERATIONAL_STOP, 'source_id' => 1, 'status' => AffectedReservationCase::STATUS_PENDING,
        ]);
        $other = $this->owned($this->customer(), '2026-11-07 18:00');
        $used = $this->owned($seller, '2026-11-02 09:05');
        $this->attend($this->staffOf($this->court->venue), $used->check_in_code)->assertCreated();

        $response = $this->postListing($seller, [$ok->id => 90000, $held->id => 1, $disputed->id => 1, $affected->id => 1, $other->id => 1, $used->id => 1])
            ->assertStatus(422)->assertJsonPath('code', 'TRANSFER_NOT_CONFIRMED');

        $codes = array_column($response->json('items'), 'code', 'reservation_id');
        $this->assertSame([
            $held->id => 'TRANSFER_NOT_CONFIRMED',
            $disputed->id => 'TRANSFER_INCIDENT_OPEN',
            $affected->id => 'TRANSFER_INCIDENT_OPEN',
            $other->id => 'TRANSFER_NOT_HOLDER',
            $used->id => 'TRANSFER_USED',
        ], $codes);
        $this->assertSame(0, BookingTransferListingItem::count());

        config(['features.pilot_organizations' => ['0']]);
        $this->postListing($seller, [$ok->id => 90000])->assertStatus(422)->assertJsonPath('items.0.code', 'TRANSFER_FEATURE_OFF');
        config(['features.pilot_organizations' => ['*']]);
        $this->postListing($this->customer(['phone' => null]), [$ok->id => 1])->assertStatus(422)->assertJsonPath('code', 'PROFILE_INCOMPLETE');
        $this->postListing($seller, [$ok->id => 90000], 'Bận đột xuất')->assertCreated()
            ->assertJsonPath('listing.note', 'Bận đột xuất')
            ->assertJsonPath('listing.items.0.state', 'available')
            ->assertJsonPath('listing.items.0.price', 90000);
        $this->postListing($seller, [$ok->id => 90000])->assertStatus(422)->assertJsonPath('code', 'TRANSFER_LISTED');
    }

    public function test_br_177_cutoff_blocks_at_exactly_two_hours(): void
    {
        [$seller, $buyer] = [$this->customer(), $this->customer()];
        $booking = $this->owned($seller, '2026-11-02 11:00');
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:59:59', self::TZ));
        $listingId = $this->postListing($seller, [$booking->id => 100000])->assertCreated()->json('listing.id');
        $transferId = $this->createTransfer($seller, $buyer, [$booking->id => 100000])->assertCreated()->json('transfer.id');

        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00', self::TZ));

        $this->confirm($seller, $transferId)->assertStatus(422)->assertJsonPath('items.0.code', 'TRANSFER_CUTOFF');
        $this->assertSame((int) $seller->id, (int) $booking->fresh()->user_id);
        $this->actingAs($buyer)->getJson("/api/booking-transfers/listings/{$listingId}")->assertOk()->assertJsonPath('listing.items.0.state', 'expired');
        $this->actingAs($buyer)->getJson('/api/booking-transfers/listings')->assertOk()->assertJsonCount(0, 'data');
        $this->command($seller, "/api/booking-transfers/{$transferId}/cancel")->assertOk();
        $this->createTransfer($seller, $buyer, [$booking->id => 100000])->assertStatus(422)->assertJsonPath('code', 'TRANSFER_CUTOFF');
    }

    public function test_br_178_a_booking_is_transferred_once_and_the_buyer_cannot_pass_it_on(): void
    {
        [$seller, $buyer] = [$this->customer(), $this->customer()];
        $booking = $this->owned($seller, '2026-11-05 18:00');
        $this->transferNow($seller, $buyer, [$booking->id => 50000]);

        $this->postListing($seller, [$booking->id => 1])->assertStatus(422)->assertJsonPath('code', 'TRANSFER_NOT_HOLDER');
        $this->postListing($buyer, [$booking->id => 1])->assertStatus(422)->assertJsonPath('code', 'TRANSFER_ALREADY_TRANSFERRED');
        $this->createTransfer($buyer, $this->customer(), [$booking->id => 1])->assertStatus(422)->assertJsonPath('code', 'TRANSFER_ALREADY_TRANSFERRED');
        $this->assertSame(1, BookingTransferItem::where('reservation_id', $booking->id)->where('status', 'completed')->count());
    }

    public function test_br_181_price_cap_is_what_was_paid_after_the_voucher(): void
    {
        $court = $this->payableCourt();
        $program = $this->publicVoucher($court, now()->addMonth());
        $seller = $this->customer();
        $code = $this->checkout($seller, $court, '2026-11-05 18:00', '2026-11-05 19:00', 80000, ['voucher_program_id' => $program->id])
            ->assertCreated()->json('reservation.code');
        $this->transfer($court, $this->paymentCode($code), 80000)->assertSuccessful();
        $booking = Reservation::where('code', $code)->firstOrFail();
        $this->assertSame('confirmed', $booking->status);

        $this->postListing($seller, [$booking->id => 80001])->assertStatus(422)
            ->assertJsonPath('items.0.code', 'TRANSFER_PRICE_CAP')
            ->assertJsonPath('items.0.message', 'Giá chuyển nhượng vượt số tiền đã thanh toán (80.000₫).');
        $this->postListing($seller, [$booking->id => 80000])->assertCreated();
        $this->createTransfer($seller, $this->customer(), [$booking->id => 80001])->assertStatus(422)->assertJsonPath('code', 'TRANSFER_PRICE_CAP');
        $this->assertSame(80000, (int) $booking->fresh()->total_amount);
    }

    public function test_br_183_only_the_holder_creates_a_transfer_for_a_named_other_account(): void
    {
        [$seller, $buyer] = [$this->customer(), $this->customer()];
        $booking = $this->owned($seller, '2026-11-05 18:00');
        $otherListing = $this->postListing($buyer, [$this->owned($buyer, '2026-11-06 18:00')->id => 1])->json('listing.id');

        $this->createTransfer($seller, $seller, [$booking->id => 1])->assertStatus(422)->assertJsonPath('code', 'TRANSFER_SELF');
        $this->createTransfer($seller, User::factory()->unverified()->create(), [$booking->id => 1])->assertStatus(422)->assertJsonPath('code', 'TRANSFER_BUYER_INELIGIBLE');
        $this->createTransfer($buyer, $this->customer(), [$booking->id => 1])->assertStatus(422)->assertJsonPath('code', 'TRANSFER_NOT_HOLDER');
        $this->createTransfer($seller, $buyer, [$booking->id => 1], $otherListing)->assertForbidden()->assertJsonPath('code', 'TRANSFER_FORBIDDEN');

        $transfer = $this->createTransfer($seller, $buyer, [$booking->id => 70000])->assertCreated()->json('transfer');
        $this->assertSame('pending', $transfer['status']);
        $this->assertSame(70000, $transfer['total']);
        $this->assertSame(['id' => $buyer->id, 'name' => $buyer->name, 'avatar_url' => null], $transfer['buyer']);
        $this->assertTrue($transfer['is_seller']);
        $this->assertNull($transfer['items'][0]['refusal']);
        $this->actingAs($buyer)->getJson("/api/booking-transfers/{$transfer['id']}")->assertOk()->assertJsonPath('transfer.is_seller', false);
        $this->actingAs($this->customer())->getJson("/api/booking-transfers/{$transfer['id']}")->assertForbidden();
        $this->assertTrue(OutboxMessage::where('message_key', "booking_transfer.{$transfer['id']}.created.buyer")->exists());
    }

    public function test_br_184_a_pending_transfer_holds_only_its_bookings_until_cancelled(): void
    {
        [$seller, $buyer, $late] = [$this->customer(), $this->customer(), $this->customer()];
        $first = $this->owned($seller, '2026-11-05 18:00');
        $second = $this->owned($seller, '2026-11-05 20:00');
        $listingId = $this->postListing($seller, [$first->id => 100000, $second->id => 100000])->json('listing.id');
        $transferId = $this->createTransfer($seller, $buyer, [$first->id => 90000], $listingId)->assertCreated()->json('transfer.id');

        $this->createTransfer($seller, $late, [$first->id => 100000])->assertStatus(422)->assertJsonPath('code', 'TRANSFER_HELD');
        $this->actingAs($late)->getJson("/api/booking-transfers/listings/{$listingId}")->assertOk()
            ->assertJsonPath('listing.items.0.state', 'held')
            ->assertJsonPath('listing.items.1.state', 'available');
        $this->command($seller, "/api/booking-transfers/listings/{$listingId}/withdraw", ['reservation_ids' => [$first->id]])
            ->assertStatus(422)->assertJsonPath('code', 'TRANSFER_HELD');

        $this->command($buyer, "/api/booking-transfers/{$transferId}/cancel")->assertOk()->assertJsonPath('transfer.status', 'cancelled');
        $this->assertTrue(OutboxMessage::where('message_key', "booking_transfer.{$transferId}.cancelled.other")->exists());
        $this->createTransfer($seller, $late, [$first->id => 100000])->assertCreated();
        $this->command($seller, "/api/booking-transfers/listings/{$listingId}/withdraw")->assertOk()
            ->assertJsonPath('listing.items.0.state', 'held')
            ->assertJsonPath('listing.items.1.state', 'withdrawn');
    }

    public function test_br_185_br_187_no_payment_is_collected_and_the_booking_amounts_stay(): void
    {
        [$seller, $buyer] = [$this->customer(), $this->customer()];
        $booking = $this->owned($seller, '2026-11-05 18:00');
        $before = $booking->only(['code', 'total_amount', 'court_id', 'status']);

        $this->transferNow($seller, $buyer, [$booking->id => 40000]);

        $this->assertSame(0, OwnerPaymentAttempt::count());
        $this->assertSame($before, $booking->fresh()->only(['code', 'total_amount', 'court_id', 'status']));
        $this->assertSame(100000, (int) $booking->slots()->sum('unit_price'));
        $this->assertSame(1, Reservation::count());
    }

    public function test_br_186_only_the_seller_confirms_and_everything_is_checked_again(): void
    {
        [$seller, $buyer] = [$this->customer(), $this->customer()];
        $booking = $this->owned($seller, '2026-11-05 18:00');
        $transferId = $this->createTransfer($seller, $buyer, [$booking->id => 1])->json('transfer.id');

        $this->confirm($buyer, $transferId)->assertForbidden()->assertJsonPath('code', 'TRANSFER_FORBIDDEN');
        $booking->update(['metadata' => ['is_disputed' => true]]);
        $this->actingAs($seller)->getJson("/api/booking-transfers/{$transferId}")->assertJsonPath('transfer.items.0.refusal.code', 'TRANSFER_INCIDENT_OPEN');
        $this->confirm($seller, $transferId)->assertStatus(422)->assertJsonPath('items.0.code', 'TRANSFER_INCIDENT_OPEN');
        $this->assertSame((int) $seller->id, (int) $booking->fresh()->user_id);
        $this->assertSame('pending', BookingTransfer::find($transferId)->status);

        $this->command($seller, "/api/booking-transfers/{$transferId}/cancel")->assertOk();
        $this->confirm($seller, $transferId)->assertStatus(409)->assertJsonPath('code', 'TRANSFER_NOT_IN_TRANSACTION');
    }

    public function test_br_188_new_check_in_code_old_code_rejected(): void
    {
        [$seller, $buyer] = [$this->customer(), $this->customer()];
        $booking = $this->owned($seller, '2026-11-02 12:00');
        $staff = $this->staffOf($this->court->venue);
        $old = $booking->check_in_code;
        $this->assertSame($booking->code, $old);

        $this->transferNow($seller, $buyer, [$booking->id => 1]);
        $fresh = $booking->fresh();

        $this->assertNotSame($old, $fresh->check_in_code);
        $this->assertStringStartsWith('CHK', $fresh->check_in_code);
        $this->assertSame($booking->code, $fresh->code);
        $this->assertSame(['name' => $buyer->name, 'phone' => $buyer->phone, 'email' => $buyer->email], $fresh->metadata['holder']);
        $this->assertSame([(int) $buyer->id], ReservationEntitlement::where('reservation_id', $booking->id)->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values()->all());

        Carbon::setTestNow(Carbon::parse('2026-11-02 11:55:00', self::TZ));
        $this->attend($staff, 'AURA_BOOKING_'.$old)->assertNotFound();
        $this->attend($staff, $old)->assertNotFound();
        $this->attend($staff, 'AURA_BOOKING_'.$fresh->check_in_code)->assertCreated()
            ->assertJsonPath('reservation.customer_name', $buyer->name)
            ->assertJsonPath('reservation.customer_phone', $buyer->phone)
            ->assertJsonPath('reservation.check_in_code', $fresh->check_in_code);
    }

    public function test_br_189_confirm_replay_returns_the_stored_result(): void
    {
        [$seller, $buyer] = [$this->customer(), $this->customer()];
        $booking = $this->owned($seller, '2026-11-05 18:00');
        $transferId = $this->createTransfer($seller, $buyer, [$booking->id => 1])->json('transfer.id');

        $first = $this->confirm($seller, $transferId)->assertOk()->json('transfer');
        $code = $booking->fresh()->check_in_code;
        $outbox = OutboxMessage::count();
        $second = $this->confirm($seller, $transferId)->assertOk()->json('transfer');

        $this->assertSame($first, $second);
        $this->assertSame('completed', $second['status']);
        $this->assertSame($code, $booking->fresh()->check_in_code);
        $this->assertSame($outbox, OutboxMessage::count());
        $this->assertSame(1, BookingTransferItem::where('reservation_id', $booking->id)->where('status', 'completed')->count());
        $this->command($seller, "/api/booking-transfers/{$transferId}/cancel")->assertStatus(409)->assertJsonPath('code', 'TRANSFER_NOT_IN_TRANSACTION');
    }

    public function test_br_190_transferred_bookings_leave_the_listing_and_the_rest_stay_with_the_seller(): void
    {
        [$seller, $buyer] = [$this->customer(), $this->customer()];
        $first = $this->owned($seller, '2026-11-05 18:00');
        $second = $this->owned($seller, '2026-11-05 20:00');
        $listingId = $this->postListing($seller, [$first->id => 100000, $second->id => 100000])->json('listing.id');

        $this->transferNow($seller, $buyer, [$first->id => 100000], $listingId);

        $this->actingAs($buyer)->getJson("/api/booking-transfers/listings/{$listingId}")->assertOk()
            ->assertJsonPath('listing.items.0.state', 'transferred')
            ->assertJsonPath('listing.items.1.state', 'available');
        $this->assertSame((int) $seller->id, (int) $second->fresh()->user_id);
        $this->createTransfer($seller, $this->customer(), [$second->id => 100000])->assertCreated();
    }

    public function test_br_191_notifies_seller_buyer_owner(): void
    {
        [$seller, $buyer] = [$this->customer(), $this->customer()];
        $owner = $this->ownerOf($this->court->venue->organization);
        $booking = $this->owned($seller, '2026-11-05 18:00');

        $transferId = $this->transferNow($seller, $buyer, [$booking->id => 1]);

        $sent = OutboxMessage::where('event_type', 'booking_transfer.completed')->get()
            ->mapWithKeys(fn (OutboxMessage $message) => [(int) $message->payload['user_id'] => $message->payload['body']]);
        $this->assertEqualsCanonicalizing([(int) $seller->id, (int) $buyer->id, (int) $owner->id], $sent->keys()->all());
        foreach ($sent as $body) {
            $this->assertStringContainsString($booking->code, $body);
            $this->assertStringContainsString('09:00 02/11/2026', $body);
        }
        $this->assertNotNull(BookingTransfer::find($transferId)->confirmed_at);
    }

    public function test_fr_141_search_and_detail_show_the_market_without_seller_contact(): void
    {
        [$seller, $viewer] = [$this->customer(), $this->customer()];
        $booking = $this->owned($seller, '2026-11-05 18:00');
        $listingId = $this->postListing($seller, [$booking->id => 100000], 'Nhượng sân tối thứ năm')->json('listing.id');
        $withdrawn = $this->owned($seller, '2026-11-06 18:00');
        $gone = $this->postListing($seller, [$withdrawn->id => 1])->json('listing.id');
        $this->command($seller, "/api/booking-transfers/listings/{$gone}/withdraw", ['reservation_ids' => [$withdrawn->id]])
            ->assertOk()->assertJsonPath('listing.items.0.state', 'withdrawn');

        $search = fn (string $q) => array_column($this->actingAs($viewer)->getJson('/api/booking-transfers/listings?q='.urlencode($q))->assertOk()->json('data'), 'id');
        $this->assertSame([$listingId], $search(''));
        $this->assertSame([$listingId], $search('thứ năm'));
        $this->assertSame([$listingId], $search($this->court->code));
        $this->assertSame([$listingId], $search(mb_substr($this->court->venue->name, 0, 6)));
        $this->assertSame([], $search('không có'));

        $detail = $this->actingAs($viewer)->getJson("/api/booking-transfers/listings/{$listingId}")->assertOk()->json('listing');
        $this->assertSame(['id' => $seller->id, 'name' => $seller->name, 'avatar_url' => null], $detail['seller']);
        $this->assertFalse($detail['is_mine']);
        $this->assertArrayNotHasKey('contacts', $detail);
        $json = json_encode($detail);
        foreach ([$seller->email, $seller->phone, $booking->code] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertSame(1, $this->actingAs($viewer)->getJson('/api/booking-transfers/listings')->json('meta.total'));
    }

    public function test_fr_142_contact_opens_chat_with_snapshot_no_hold(): void
    {
        [$seller, $buyer] = [$this->customer(), $this->customer()];
        $booking = $this->owned($seller, '2026-11-05 18:00');
        $listingId = $this->postListing($seller, [$booking->id => 90000])->json('listing.id');

        $conversationId = $this->command($buyer, "/api/booking-transfers/listings/{$listingId}/contact", ['reservation_ids' => [$booking->id]])
            ->assertCreated()->json('conversation_id');

        $conversation = Conversation::findOrFail($conversationId);
        $this->assertSame(Conversation::TYPE_CUSTOMER, $conversation->type);
        $message = ConversationMessage::where('conversation_id', $conversationId)->sole();
        $this->assertSame($listingId, $message->transfer['listing_id']);
        $this->assertSame(['reservation_id' => $booking->id, 'venue_name' => $this->court->venue->name, 'court_name' => $this->court->code, 'price' => 90000],
            collect($message->transfer['items'][0])->only(['reservation_id', 'venue_name', 'court_name', 'price'])->all());
        $this->actingAs($seller)->getJson("/api/conversations/{$conversationId}/messages")->assertOk()
            ->assertJsonPath('data.0.transfer.listing_id', $listingId)
            ->assertJsonPath('conversation.last_message.preview', 'Hỏi nhận chuyển nhượng booking');
        $this->assertSame(0, BookingTransferItem::count());
        $this->actingAs($buyer)->getJson("/api/booking-transfers/listings/{$listingId}")->assertJsonPath('listing.items.0.state', 'available');
        $contacts = $this->actingAs($seller)->getJson("/api/booking-transfers/listings/{$listingId}")->json('listing.contacts');
        $this->assertSame([['id' => $buyer->id, 'name' => $buyer->name, 'avatar_url' => null], $conversationId, [$booking->id]],
            [$contacts[0]['user'], $contacts[0]['conversation_id'], $contacts[0]['reservation_ids']]);

        $this->command($seller, "/api/booking-transfers/listings/{$listingId}/contact", ['reservation_ids' => [$booking->id]])
            ->assertStatus(422)->assertJsonPath('code', 'TRANSFER_SELF');
        $this->command($buyer, "/api/booking-transfers/listings/{$listingId}/contact", ['reservation_ids' => [$booking->id + 1000]])
            ->assertStatus(422)->assertJsonPath('code', 'TRANSFER_ITEM_NOT_IN_LISTING');

        // Bước 3: a withdrawn booking no longer takes contacts.
        $this->command($seller, "/api/booking-transfers/listings/{$listingId}/withdraw", ['reservation_ids' => [$booking->id]])->assertOk();
        $this->command($buyer, "/api/booking-transfers/listings/{$listingId}/contact", ['reservation_ids' => [$booking->id]])
            ->assertStatus(422)->assertJsonPath('code', 'TRANSFER_NOT_AVAILABLE')
            ->assertJsonPath('items.0.reservation_id', $booking->id);
        $this->assertSame(1, ConversationMessage::where('conversation_id', $conversationId)->count());
    }

    public function test_uc_9_6_pre1_contact_needs_a_complete_profile(): void
    {
        $seller = $this->customer();
        $booking = $this->owned($seller, '2026-11-05 18:00');
        $listingId = $this->postListing($seller, [$booking->id => 90000])->json('listing.id');

        $this->command($this->customer(['phone' => null]), "/api/booking-transfers/listings/{$listingId}/contact", ['reservation_ids' => [$booking->id]])
            ->assertStatus(422)->assertJsonPath('code', 'PROFILE_INCOMPLETE');
        $this->assertSame(0, ConversationMessage::count());
    }

    public function test_uc_9_3_a2_the_market_keeps_held_listings_with_each_booking_state(): void
    {
        [$seller, $buyer, $viewer] = [$this->customer(), $this->customer(), $this->customer()];
        $held = $this->owned($seller, '2026-11-05 18:00');
        $heldListing = $this->postListing($seller, [$held->id => 100000])->json('listing.id');
        $this->createTransfer($seller, $buyer, [$held->id => 100000], $heldListing)->assertCreated();
        [$sold, $open] = [$this->owned($seller, '2026-11-06 18:00'), $this->owned($seller, '2026-11-06 20:00')];
        $mixed = $this->postListing($seller, [$sold->id => 100000, $open->id => 100000])->json('listing.id');
        $this->transferNow($seller, $buyer, [$sold->id => 100000], $mixed);
        $done = $this->owned($seller, '2026-11-07 18:00');
        $doneListing = $this->postListing($seller, [$done->id => 100000])->json('listing.id');
        $this->transferNow($seller, $buyer, [$done->id => 100000], $doneListing);

        $market = collect($this->actingAs($viewer)->getJson('/api/booking-transfers/listings')->assertOk()->json('data'))
            ->mapWithKeys(fn (array $listing): array => [$listing['id'] => array_column($listing['items'], 'state')]);

        // A fully held listing stays (the hold may be cancelled); one with nothing left to offer leaves (BR-190).
        $this->assertEquals([$mixed => ['transferred', 'available'], $heldListing => ['held']], $market->all());
        $this->assertArrayNotHasKey($doneListing, $market->all());
    }

    public function test_fr_146_the_buyer_holds_the_booking_and_the_seller_no_longer_sees_it(): void
    {
        [$seller, $buyer] = [$this->customer(), $this->customer()];
        $booking = $this->owned($seller, '2026-11-05 18:00', ['booker' => ['name' => $seller->name, 'phone' => $seller->phone, 'email' => $seller->email]]);

        $this->transferNow($seller, $buyer, [$booking->id => 1]);

        $this->assertSame([], $this->actingAs($seller)->getJson('/api/me/reservations')->assertOk()->json('data'));
        $this->actingAs($seller)->getJson("/api/reservations/{$booking->code}")->assertNotFound();
        $mine = $this->actingAs($buyer)->getJson('/api/me/reservations')->assertOk()->json('data.0');
        $this->assertSame($booking->id, $mine['id']);
        $this->assertSame($buyer->name, $mine['holder']['name']);
        $this->assertSame($booking->fresh()->check_in_code, $mine['check_in_code']);
        $detail = $this->actingAs($buyer)->getJson("/api/reservations/{$booking->code}")->assertOk()->json('reservation');
        $this->assertArrayNotHasKey('booker', $detail);
        $this->assertArrayNotHasKey('booker', $detail['metadata']);
        $this->assertStringNotContainsString((string) $seller->phone, json_encode($detail));
        $this->assertArrayNotHasKey('check_in_code', $booking->fresh()->toArray());
    }

    public function test_d2_migration_removes_old_listings_with_notice_and_keeps_completed_transfers(): void
    {
        [$seller, $buyer] = [$this->customer(), $this->customer()];
        $listed = $this->owned($seller, '2026-11-05 18:00');
        $sold = $this->owned($seller, '2026-11-06 18:00');
        $migration = require database_path('migrations/2026_10_28_000001_cut_over_booking_transfers.php');
        $migration->down();
        $entitlement = fn (Reservation $r): int => (int) DB::table('reservation_entitlements')->where('reservation_id', $r->id)->value('id');
        $row = fn (Reservation $r, string $status) => [
            'organization_id' => $r->organization_id, 'reservation_entitlement_id' => $entitlement($r), 'seller_id' => $seller->id,
            'original_price' => 100000, 'asking_price' => 90000, 'status' => $status, 'cutoff_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('marketplace_listings')->insert($row($listed, 'listed'));
        $soldListing = DB::table('marketplace_listings')->insertGetId($row($sold, 'sold'));
        DB::table('entitlement_transfers')->insert([
            'organization_id' => $sold->organization_id, 'marketplace_listing_id' => $soldListing, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'seller_entitlement_id' => $entitlement($sold), 'buyer_amount' => 90000, 'marketplace_fee' => 0, 'seller_refund' => 0,
            'idempotency_key' => (string) Str::uuid(), 'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        // The old flow moved a fully sold booking to its buyer but left the seller as booker.
        DB::table('reservations')->where('id', $sold->id)->update(['user_id' => $buyer->id]);

        $migration->up();

        $notice = OutboxMessage::where('event_type', 'booking_transfer.listing_removed')->sole();
        $this->assertSame((int) $seller->id, (int) $notice->payload['user_id']);
        $copied = BookingTransfer::sole();
        $this->assertSame(['completed', (int) $seller->id, (int) $buyer->id, 90000], [$copied->status, (int) $copied->seller_id, (int) $copied->buyer_id, (int) $copied->total]);
        $this->assertSame([$sold->id], BookingTransferItem::where('status', 'completed')->pluck('reservation_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame($listed->code, Reservation::find($listed->id)->check_in_code);
        $handed = Reservation::find($sold->id);
        $this->assertSame([$buyer->name, $buyer->phone], [$handed->holder['name'], $handed->holder['phone']]);
        $this->assertStringStartsWith('CHK', $handed->check_in_code);
        $this->assertNotSame($sold->code, $handed->check_in_code);
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('marketplace_listings'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('venue_credit_accounts'));
        $this->postListing($buyer, [$sold->id => 1])->assertStatus(422)->assertJsonPath('code', 'TRANSFER_ALREADY_TRANSFERRED');
    }

    public function test_d7_recurring_group_is_shared_by_one_submit(): void
    {
        $court = $this->payableCourt();
        $user = $this->customer();
        $submit = fn (array $days) => $this->command($user, '/api/recurring-bookings', [
            'court_id' => $court->id,
            'occurrences' => array_map(fn (string $day): array => ['starts_at' => "{$day} 18:00", 'ends_at' => "{$day} 19:00", 'expected_total' => 100000], $days),
        ])->assertCreated()->json('data.*.reservation.code');

        $first = $submit(['2026-11-03', '2026-11-05']);
        $second = $submit(['2026-11-10']);

        $group = fn (string $code) => Reservation::where('code', $code)->value('metadata')['recurring_group'] ?? null;
        $this->assertTrue(Str::isUuid((string) $group($first[0])));
        $this->assertSame($group($first[0]), $group($first[1]));
        $this->assertNotSame($group($first[0]), $group($second[0]));
    }

    /** @param  array<string, mixed>  $metadata */
    private function owned(User $user, string $start, array $metadata = [], string $status = 'confirmed'): Reservation
    {
        $booking = $this->booking($this->court, Carbon::parse($start, self::TZ), status: $status, metadata: $metadata);
        $booking->update(['user_id' => $user->id]);
        ReservationEntitlement::where('reservation_id', $booking->id)->update(['user_id' => $user->id]);

        return $booking->fresh(['slots']);
    }

    /** @param  array<int, int>  $prices  reservation id => price */
    private function postListing(User $seller, array $prices, ?string $note = null): TestResponse
    {
        return $this->command($seller, '/api/booking-transfers/listings', ['note' => $note, 'items' => $this->items($prices)]);
    }

    /** @param  array<int, int>  $prices */
    private function createTransfer(User $seller, User $buyer, array $prices, ?int $listingId = null): TestResponse
    {
        return $this->command($seller, '/api/booking-transfers', ['listing_id' => $listingId, 'buyer_id' => $buyer->id, 'items' => $this->items($prices)]);
    }

    private function confirm(User $user, int $transferId): TestResponse
    {
        return $this->command($user, "/api/booking-transfers/{$transferId}/confirm");
    }

    /** @param  array<int, int>  $prices */
    private function transferNow(User $seller, User $buyer, array $prices, ?int $listingId = null): int
    {
        $id = $this->createTransfer($seller, $buyer, $prices, $listingId)->assertCreated()->json('transfer.id');
        $this->confirm($seller, $id)->assertOk()->assertJsonPath('transfer.status', 'completed');

        return $id;
    }

    /** @param  array<int, int>  $prices */
    private function items(array $prices): array
    {
        return array_map(fn (int $id, int $price): array => ['reservation_id' => $id, 'price' => $price], array_keys($prices), $prices);
    }

    private function attend(User $staff, string $code): TestResponse
    {
        return $this->actingAs($staff)->postJson('/api/attendance/check-in', ['code' => $code], [
            'X-Organization-ID' => (string) $this->court->venue->organization_id,
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
    }
}
