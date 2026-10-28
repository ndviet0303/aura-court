<?php

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Module 08.2 D0-D5, D14: a booking moves to its new holder when the two customers have paid each other and the
 * seller confirms, so the owner-collected resale and venue credit go. A resale payment still pending expires; money
 * that arrives later becomes the owner's transfer_after_expiry exception. Open listings carry no per-booking price or
 * note, so they are removed and their sellers (and anyone holding one) are told to post again. A completed transfer
 * is kept as a completed booking transfer, so that booking counts as transferred once (BR-178); a booking the old flow
 * already moved to its buyer gets that buyer as holder and a new check-in code (D5, D6). A partly sold old multi-slot
 * booking stayed with its seller, so it keeps the seller as holder and its code, yet counts as transferred. Ledger history stays.
 * A chat message can carry a read-only snapshot of the listing a buyer asks about (D11).
 */
return new class extends Migration
{
    public function up(): void
    {
        // D4: what a listing or transfer is in now is read from these rows; the 2-hour cutoff is computed when read.
        Schema::create('booking_transfer_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->index()->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE booking_transfer_listings ADD CONSTRAINT booking_transfer_listings_note_check CHECK (char_length(note) <= 1000)');

        Schema::create('booking_transfer_listing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('booking_transfer_listings')->restrictOnDelete();
            $table->foreignId('reservation_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->index()->constrained()->restrictOnDelete();
            $table->bigInteger('price');
            $table->string('status', 16)->default('listed');
            $table->timestamps();
            $table->unique(['listing_id', 'reservation_id']);
        });
        DB::statement("ALTER TABLE booking_transfer_listing_items ADD CONSTRAINT booking_transfer_listing_items_status_check CHECK (status IN ('listed', 'withdrawn', 'transferred'))");
        DB::statement('ALTER TABLE booking_transfer_listing_items ADD CONSTRAINT booking_transfer_listing_items_price_check CHECK (price >= 0)');
        DB::statement("CREATE UNIQUE INDEX booking_transfer_listing_items_listed_unique ON booking_transfer_listing_items (reservation_id) WHERE status = 'listed'");

        Schema::create('booking_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->nullable()->index()->constrained('booking_transfer_listings')->nullOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('pending');
            $table->bigInteger('total');
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['seller_id', 'status']);
            $table->index(['buyer_id', 'status']);
        });
        DB::statement("ALTER TABLE booking_transfers ADD CONSTRAINT booking_transfers_status_check CHECK (status IN ('pending', 'completed', 'cancelled'))");
        DB::statement('ALTER TABLE booking_transfers ADD CONSTRAINT booking_transfers_total_check CHECK (total >= 0)');
        DB::statement('ALTER TABLE booking_transfers ADD CONSTRAINT booking_transfers_parties_check CHECK (seller_id <> buyer_id)');

        Schema::create('booking_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_transfer_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->index()->constrained()->restrictOnDelete();
            $table->bigInteger('price');
            $table->string('status', 16)->default('pending');
            $table->timestamps();
            $table->unique(['booking_transfer_id', 'reservation_id']);
        });
        DB::statement("ALTER TABLE booking_transfer_items ADD CONSTRAINT booking_transfer_items_status_check CHECK (status IN ('pending', 'completed', 'cancelled'))");
        DB::statement('ALTER TABLE booking_transfer_items ADD CONSTRAINT booking_transfer_items_price_check CHECK (price >= 0)');
        // BR-184: one open transaction per booking; BR-178: one completed transfer per booking.
        DB::statement("CREATE UNIQUE INDEX booking_transfer_items_pending_unique ON booking_transfer_items (reservation_id) WHERE status = 'pending'");
        DB::statement("CREATE UNIQUE INDEX booking_transfer_items_completed_unique ON booking_transfer_items (reservation_id) WHERE status = 'completed'");

        // D5: QR, scan and check-in read this code, so a transfer can issue a new one without touching code.
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('check_in_code', 40)->nullable();
        });
        DB::statement('UPDATE reservations SET check_in_code = code');
        DB::statement('ALTER TABLE reservations ALTER COLUMN check_in_code SET NOT NULL');
        Schema::table('reservations', function (Blueprint $table) {
            $table->unique('check_in_code');
        });

        // D11: a contact message carries a read-only snapshot of the listing and the bookings asked about.
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->json('transfer')->nullable();
        });

        DB::table('owner_payment_attempts')->where('purpose', 'resale')->where('status', 'pending')
            ->update(['status' => 'expired', 'updated_at' => now()]);
        $this->notifyRemovedListings();
        $this->copyCompletedTransfers();

        Schema::dropIfExists('entitlement_transfers');
        Schema::dropIfExists('marketplace_listings');
        Schema::dropIfExists('venue_credit_cash_out_codes');
        Schema::dropIfExists('venue_credit_entries');
        Schema::dropIfExists('venue_credit_accounts');

        DB::statement("UPDATE venue_booking_policies SET settings = (settings::jsonb - 'transfer')::json WHERE settings::jsonb -> 'transfer' IS NOT NULL");
        DB::statement("UPDATE organizations SET settings = (settings::jsonb - 'resale_fee_percent')::json WHERE settings::jsonb -> 'resale_fee_percent' IS NOT NULL");
    }

    /** Rebuilds the empty old tables; their rows, the removed settings and the check-in codes are not restored. */
    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropColumn('transfer');
        });
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('check_in_code');
        });
        Schema::dropIfExists('booking_transfer_items');
        Schema::dropIfExists('booking_transfers');
        Schema::dropIfExists('booking_transfer_listing_items');
        Schema::dropIfExists('booking_transfer_listings');

        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_entitlement_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->index()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('original_price');
            $table->unsignedBigInteger('asking_price');
            $table->unsignedSmallInteger('fee_bps')->default(500);
            $table->string('status', 24)->default('listed')->index();
            $table->dateTime('cutoff_at');
            $table->dateTime('sold_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'cutoff_at']);
        });
        DB::statement("ALTER TABLE marketplace_listings ADD CONSTRAINT marketplace_listings_status_check CHECK (status IN ('checkout', 'listed', 'sold', 'withdrawn'))");
        DB::statement("CREATE UNIQUE INDEX marketplace_listings_active_entitlement_unique ON marketplace_listings (reservation_entitlement_id) WHERE status IN ('listed', 'checkout')");

        Schema::create('entitlement_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('marketplace_listing_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('buyer_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_entitlement_id')->index()->constrained('reservation_entitlements')->restrictOnDelete();
            $table->foreignId('buyer_entitlement_id')->nullable()->unique()->constrained('reservation_entitlements')->nullOnDelete();
            $table->unsignedBigInteger('buyer_amount');
            $table->unsignedBigInteger('marketplace_fee');
            $table->unsignedBigInteger('seller_refund');
            $table->string('buyer_payment_reference', 160)->nullable();
            $table->string('seller_refund_reference', 160)->nullable();
            $table->string('idempotency_key', 255);
            $table->string('status', 24)->default('pending')->index();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'idempotency_key']);
        });
        DB::statement("ALTER TABLE entitlement_transfers ADD CONSTRAINT entitlement_transfers_status_check CHECK (status IN ('compensated', 'completed', 'pending'))");

        (require database_path('migrations/2026_10_25_000001_create_venue_credit_tables.php'))->up();
    }

    /** Database channel only, through the outbox: a migration must not depend on the mail transport. */
    private function notifyRemovedListings(): void
    {
        $notifications = app(DurableNotificationServiceInterface::class);
        DB::table('marketplace_listings')->whereIn('status', ['listed', 'checkout'])->orderBy('id')
            ->get(['id', 'organization_id', 'seller_id', 'status', 'metadata'])
            ->each(function (object $listing) use ($notifications): void {
                $notifications->recordOutboxNotification("booking_transfer.cutover.{$listing->id}.{$listing->seller_id}", (int) $listing->seller_id,
                    'booking_transfer.listing_removed', [
                        'title' => 'Tin chuyển nhượng đã được gỡ',
                        'body' => 'Chuyển nhượng booking đổi sang cách mới: bạn và người nhận tự thanh toán cho nhau, rồi bạn xác nhận chuyển nhượng. Tin đăng cũ của bạn đã được gỡ, vui lòng đăng lại nếu vẫn muốn chuyển nhượng.',
                        'action_url' => '/resale',
                    ], (int) $listing->organization_id);

                $holderId = (int) (json_decode((string) $listing->metadata, true)['locked_by'] ?? 0);
                if ($listing->status === 'checkout' && $holderId > 0) {
                    $notifications->recordOutboxNotification("booking_transfer.cutover.{$listing->id}.{$holderId}", $holderId,
                        'booking_transfer.listing_removed', [
                            'title' => 'Giữ chỗ nhận chuyển nhượng đã được huỷ',
                            'body' => 'Chuyển nhượng booking đổi sang cách mới nên giữ chỗ của bạn đã được huỷ. Nếu bạn đã chuyển tiền qua mã QR của chủ sân, hãy liên hệ chủ sân để được hoàn tiền.',
                            'action_url' => '/resale',
                        ], (int) $listing->organization_id);
                }
            });
    }

    private function copyCompletedTransfers(): void
    {
        DB::table('entitlement_transfers as et')
            ->join('reservation_entitlements as re', 're.id', '=', 'et.seller_entitlement_id')
            ->where('et.status', 'completed')
            ->whereColumn('et.seller_id', '<>', 'et.buyer_id')
            ->orderBy('et.id')
            ->get(['et.seller_id', 'et.buyer_id', 'et.buyer_amount', 'et.created_at', 'et.updated_at', 're.reservation_id', 're.organization_id'])
            ->each(function (object $old): void {
                // An old multi-slot booking may have sold more than one slot; it still counts as transferred once.
                if (DB::table('booking_transfer_items')->where('reservation_id', $old->reservation_id)->where('status', 'completed')->exists()) {
                    return;
                }
                $transferId = DB::table('booking_transfers')->insertGetId([
                    'seller_id' => $old->seller_id,
                    'buyer_id' => $old->buyer_id,
                    'status' => 'completed',
                    'total' => $old->buyer_amount,
                    'confirmed_at' => $old->updated_at,
                    'created_at' => $old->created_at,
                    'updated_at' => $old->updated_at,
                ]);
                DB::table('booking_transfer_items')->insert([
                    'booking_transfer_id' => $transferId,
                    'reservation_id' => $old->reservation_id,
                    'organization_id' => $old->organization_id,
                    'price' => $old->buyer_amount,
                    'status' => 'completed',
                    'created_at' => $old->created_at,
                    'updated_at' => $old->updated_at,
                ]);
                $this->handOver((int) $old->reservation_id, (int) $old->buyer_id);
            });
    }

    /** D5, D6: the old flow moved user_id to the buyer once every slot was sold; that buyer now holds it with a new code. */
    private function handOver(int $reservationId, int $buyerId): void
    {
        $reservation = DB::table('reservations')->where('id', $reservationId)->where('user_id', $buyerId)->first(['metadata']);
        $buyer = DB::table('users')->where('id', $buyerId)->first(['name', 'phone', 'email']);
        if ($reservation === null || $buyer === null) {
            return;
        }
        // Same shape as EloquentBookingTransferRepository::newCheckInCode.
        do {
            $code = 'CHK'.strtoupper(Str::random(13));
        } while (DB::table('reservations')->where('check_in_code', $code)->orWhere('code', $code)->exists());

        $holder = ['name' => (string) $buyer->name, 'phone' => (string) $buyer->phone, 'email' => (string) $buyer->email];
        DB::table('reservations')->where('id', $reservationId)->update([
            'metadata' => json_encode(['holder' => $holder] + (array) json_decode((string) $reservation->metadata, true)),
            'check_in_code' => $code,
        ]);
    }
};
