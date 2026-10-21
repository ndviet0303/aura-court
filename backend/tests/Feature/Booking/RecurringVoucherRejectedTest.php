<?php

namespace Tests\Feature\Booking;

use App\Models\MonthlyInvoice;
use App\Models\OwnerPaymentAttempt;
use App\Models\RecurringSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\BooksDirectOwnerCourt;
use Tests\TestCase;

/** Recurring bookings take no voucher, coupon or points (design D21, D28): naming one is refused and creates nothing. */
class RecurringVoucherRejectedTest extends TestCase
{
    use BooksDirectOwnerCourt;
    use RefreshDatabase;

    public function test_recurring_preview_and_create_naming_a_code_or_points_are_refused_and_create_nothing(): void
    {
        $fixture = $this->directOwnerCourt(200000, ['max_days_ahead' => 400]);
        $headers = ['X-Organization-ID' => (string) $fixture['organization']->id];
        $payload = [
            'court_id' => $fixture['court']->id,
            'frequency' => 'monthly',
            'starts_at' => Carbon::now(config('app.timezone'))->addMonths(2)->startOfMonth()->addDays(14)->setTime(9, 0)->toIso8601String(),
            'occurrences' => 1,
        ];
        $preview = $this->actingAs($fixture['payer'])->withHeaders($headers)
            ->postJson('/api/recurring-series/preview', $payload)
            ->assertOk()
            ->json();
        $accepted = ['accepted' => [
            'total' => $preview['total_amount'],
            'occurrences' => array_map(static fn (array $o): array => ['starts_at' => $o['starts_at'], 'price' => $o['price']], $preview['occurrences']),
        ]];
        $messages = [
            'coupon_code' => 'Voucher và mã giảm giá không áp dụng cho đặt sân định kỳ.',
            'loyalty_points' => 'Đặt sân định kỳ không dùng điểm tích lũy.',
        ];

        foreach ([['coupon_code' => 'SUMMER10'], ['loyalty_points' => 100]] as $benefit) {
            $field = array_key_first($benefit);
            $this->actingAs($fixture['payer'])->withHeaders($headers)
                ->postJson('/api/recurring-series/preview', $payload + $benefit)
                ->assertUnprocessable()
                ->assertJsonPath("errors.{$field}.0", $messages[$field]);
            $this->actingAs($fixture['payer'])->withHeaders($headers + ['Idempotency-Key' => (string) Str::uuid()])
                ->postJson('/api/recurring-series', $payload + $accepted + $benefit)
                ->assertUnprocessable()
                ->assertJsonPath("errors.{$field}.0", $messages[$field]);
        }

        $this->assertDatabaseCount(RecurringSeries::class, 0);
        $this->assertDatabaseCount(MonthlyInvoice::class, 0);
        $this->assertDatabaseCount(OwnerPaymentAttempt::class, 0);
    }
}
