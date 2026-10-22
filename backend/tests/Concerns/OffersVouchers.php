<?php

namespace Tests\Concerns;

use App\Models\Court;
use App\Models\PromotionProgram;
use App\Models\User;
use App\Models\VoucherCode;
use App\Models\WalletAccount;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Promotion programs and booking checkouts for tests. Programs run from a day ago for a week unless the attributes
 * say otherwise; a percentage is given in hundredths (percent_bp: 1250 = 12.5%). Checkout uses the legacy wallet
 * path (direct_owner_payments is off in tests), so a booking is confirmed and its voucher used at once.
 *
 *     $program = $this->publicProgram($court, ['percent_bp' => 2000, 'max_discount' => 50000]);
 *     $program = $this->privateProgram($court, ['ABCDEFGHJK'], ['amount' => 30000]);
 *     $this->checkout($customer, $court, '2026-10-26 10:00', '2026-10-26 11:00', 80000, ['voucher_program_id' => $program->id]);
 */
trait OffersVouchers
{
    /**
     * @param  Court|list<Court>  $courts
     * @param  array<string, mixed>  $attributes
     */
    protected function publicProgram(Court|array $courts, array $attributes = []): PromotionProgram
    {
        return $this->program($courts, ['type' => PromotionProgram::TYPE_PUBLIC] + $attributes + ['max_uses' => 10]);
    }

    /**
     * @param  Court|list<Court>  $courts
     * @param  list<string>  $codes
     * @param  array<string, mixed>  $attributes
     */
    protected function privateProgram(Court|array $courts, array $codes, array $attributes = []): PromotionProgram
    {
        $program = $this->program($courts, ['type' => PromotionProgram::TYPE_PRIVATE, 'code_count' => count($codes)] + $attributes);
        foreach ($codes as $code) {
            VoucherCode::create(['program_id' => $program->id, 'code' => $code]);
        }

        return $program;
    }

    /** A verified customer with a phone and a wallet holding $balance at the court's organization. */
    protected function customer(Court $court, int $balance = 1000000): User
    {
        $user = User::factory()->create();
        WalletAccount::create([
            'organization_id' => $court->venue->organization_id,
            'user_id' => $user->id,
            'currency' => 'VND',
            'balance' => $balance,
            'status' => 'active',
        ]);

        return $user;
    }

    /**
     * Checkout of [$start, $end), the venue's local times, agreeing to pay $expectedTotal.
     *
     * @param  array<string, mixed>  $extra  voucher_program_id or voucher_code
     */
    protected function checkout(User $customer, Court $court, string $start, string $end, int $expectedTotal, array $extra = []): TestResponse
    {
        return $this->actingAs($customer)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'starts_at' => $start.':00+07:00',
            'ends_at' => $end.':00+07:00',
            'expected_total' => $expectedTotal,
            ...$extra,
        ]);
    }

    /**
     * @param  Court|list<Court>  $courts
     * @param  array<string, mixed>  $attributes
     */
    private function program(Court|array $courts, array $attributes): PromotionProgram
    {
        $courts = is_array($courts) ? $courts : [$courts];
        $program = PromotionProgram::create($attributes + [
            'venue_id' => $courts[0]->venue_id,
            'name' => 'Ưu đãi '.(PromotionProgram::count() + 1),
            'discount_kind' => isset($attributes['amount']) ? PromotionProgram::KIND_FIXED : PromotionProgram::KIND_PERCENT,
            'percent_bp' => isset($attributes['amount']) ? null : 1000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
        ]);
        $program->courts()->attach(array_map(fn (Court $court): int => (int) $court->id, $courts));

        return $program;
    }
}
