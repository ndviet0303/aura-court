<?php

use App\Models\User;
use App\Notifications\AuraBookingNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Resale moves to owner-collected payment (resale-venue-credit D9): every peer-to-peer checkout lock is released,
 * back to listed or withdrawn past its cutoff, and the buyer who held it hears why. Old sold transfers stay as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now()->utc();
        DB::table('marketplace_listings')->where('status', 'checkout')->orderBy('id')->get(['id', 'cutoff_at', 'metadata'])
            ->each(function (object $listing) use ($now): void {
                $metadata = json_decode((string) $listing->metadata, true) ?: [];
                $buyerId = (int) ($metadata['locked_by'] ?? 0);
                $metadata['last_release'] = [
                    'by' => 'cutover',
                    'at' => $now->toIso8601String(),
                    'buyer_id' => $buyerId,
                    'payment_reported' => (bool) ($metadata['payment_reported_at'] ?? false),
                    'reason' => null,
                ];
                unset($metadata['locked_by'], $metadata['locked_until'], $metadata['reference'], $metadata['payment_reported_at']);
                DB::table('marketplace_listings')->where('id', $listing->id)->update([
                    'status' => $listing->cutoff_at && $now->lt($listing->cutoff_at) ? 'listed' : 'withdrawn',
                    'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                ]);
                // Database channel only: a migration must not depend on the mail transport.
                User::query()->find($buyerId)?->notify(new AuraBookingNotification('resale_released', 'Giữ chỗ nhượng sân đã được huỷ',
                    'Chợ nhượng sân đổi sang thanh toán qua tài khoản của chủ sân nên giữ chỗ của bạn đã được huỷ. Nếu bạn đã chuyển tiền cho người bán, hãy mở khiếu nại để được hỗ trợ.',
                    '/resale', ['database']));
            });
    }

    public function down(): void
    {
        // Released locks are not restored: the peer-to-peer flow they belonged to no longer exists.
    }
};
