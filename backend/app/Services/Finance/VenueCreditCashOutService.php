<?php

namespace App\Services\Finance;

use App\Models\CashShift;
use App\Models\Organization;
use App\Models\User;
use App\Models\VenueCreditCashOutCode;
use App\Services\POS\PosService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Counter cash-out of venue credit (design D7): the customer makes a one-time code, a cashier with an open shift looks
 * it up and confirms after handing over the cash. Only the code's hash is stored.
 */
class VenueCreditCashOutService
{
    /** Upper-case letters and digits without the look-alikes 0, O, 1 and I. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const TTL_MINUTES = 10;

    /** A wrong, expired, used or foreign code all read the same, so a cashier learns nothing about another customer. */
    private const REFUSED = 'Mã đổi tiền không hợp lệ hoặc đã hết hạn.';

    public function __construct(
        private readonly VenueCreditService $credits,
        private readonly PosService $pos,
    ) {}

    /**
     * A new code replaces the customer's live one at this organization. The balance is only checked: the customer is
     * at the counter, and confirmation checks it again.
     *
     * @return array{code: string, amount: int, expires_at: string}
     */
    public function create(User $customer, Organization $organization, int $amount): array
    {
        return DB::transaction(function () use ($customer, $organization, $amount): array {
            abort_if($this->credits->balance($organization->id, $customer->id) < $amount, 422, 'Số dư credit không đủ.');
            $this->codes()->where('organization_id', $organization->id)->where('user_id', $customer->id)
                ->whereNull('used_at')->whereNull('cancelled_at')->update(['cancelled_at' => now()]);

            do {
                $code = implode('', array_map(fn (): string => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)], range(1, 8)));
            } while ($this->codes()->where('code_hash', $this->hash($code))->exists());

            $row = VenueCreditCashOutCode::create([
                'organization_id' => $organization->id,
                'user_id' => $customer->id,
                'code_hash' => $this->hash($code),
                'amount' => $amount,
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            ]);

            return ['code' => $code, 'amount' => $amount, 'expires_at' => $row->expires_at->toIso8601String()];
        });
    }

    /** @return array{customer_name: string, amount: int, expires_at: string} */
    public function lookup(CashShift $shift, User $cashier, string $code): array
    {
        $this->assertCounter($shift, $cashier);
        $row = $this->live($shift, $this->codes()->where('code_hash', $this->hash($code))->first());

        return ['customer_name' => (string) $row->user?->name, 'amount' => $row->amount, 'expires_at' => $row->expires_at->toIso8601String()];
    }

    /**
     * Locks the code, then the credit account (inside cashOut), then the shift (inside recordCashMovement), so two
     * cashiers confirming the same code produce exactly one cash-out.
     *
     * @return array{customer_name: string, amount: int, balance: int}
     */
    public function confirm(CashShift $shift, User $cashier, string $code): array
    {
        $this->assertCounter($shift, $cashier);

        return DB::transaction(function () use ($shift, $cashier, $code): array {
            $row = $this->live($shift, $this->codes()->where('code_hash', $this->hash($code))->lockForUpdate()->first());
            $reference = 'venue_credit:'.$row->id;
            $entry = $this->credits->cashOut($row->organization_id, $row->user_id, $row->amount, $row->id, $cashier->id, $reference);
            $this->pos->recordCashMovement($shift, $cashier, 'cash_out', $row->amount, $reference);
            $row->update(['used_at' => now(), 'used_by' => $cashier->id, 'cash_shift_id' => $shift->id]);

            return ['customer_name' => (string) $row->user?->name, 'amount' => $row->amount, 'balance' => $entry->balance_after];
        });
    }

    /** An open shift at a venue the cashier operates (BR-20/21). */
    private function assertCounter(CashShift $shift, User $cashier): void
    {
        abort_unless($shift->status === 'open', 409, 'Ca đã đóng. Hãy mở ca trước khi đổi credit ra tiền mặt.');
        abort_unless($shift->venue_id !== null && $cashier->canOperateVenue((int) $shift->venue_id), 403, 'Bạn không được giao cơ sở này.');
    }

    private function live(CashShift $shift, ?VenueCreditCashOutCode $row): VenueCreditCashOutCode
    {
        abort_unless($row !== null && $row->isLive() && (int) $row->organization_id === (int) $shift->organization_id, 422, self::REFUSED);

        return $row;
    }

    /**
     * Codes are read across tenants by hash; the organization check in live() keeps them apart.
     *
     * @return Builder<VenueCreditCashOutCode>
     */
    private function codes(): Builder
    {
        return VenueCreditCashOutCode::query()->withoutGlobalScope('tenant_isolation');
    }

    private function hash(string $code): string
    {
        return hash('sha256', strtoupper(trim($code)));
    }
}
