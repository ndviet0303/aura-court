<?php

namespace App\Services\Finance;

use App\Models\Reservation;
use App\Models\VenueCreditAccount;
use App\Models\VenueCreditEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of venue credit (resale-venue-credit D2, D3). Each move locks the customer's account row last,
 * records one entry per (source, type) and posts the owner journal for moves that change what the owner owes.
 * There is no top-up and no transfer between customers: credit comes only from an owner-collected resale.
 */
class VenueCreditService
{
    /** Balance and reserved signs per entry type (design D2); callers pass a positive amount. */
    private const DELTAS = ['issue' => [1, 0], 'reserve' => [-1, 1], 'release' => [1, -1], 'capture' => [0, -1], 'spend' => [-1, 0], 'return' => [1, 0], 'cash_out' => [-1, 0]];

    public function __construct(private readonly FinancialPostingService $financials) {}

    /** A resale sold through the owner's account: the seller is owed the asking price. */
    public function issue(int $organizationId, int $userId, int $amount, string $sourceType, int|string $sourceId, ?string $reference = null, array $metadata = []): VenueCreditEntry
    {
        return $this->apply('issue', $organizationId, $userId, $amount, $sourceType, $sourceId, $reference, null, $metadata);
    }

    /** Holds credit against an unpaid booking; the QR asks only for the rest. */
    public function reserve(Reservation $reservation, int $amount): VenueCreditEntry
    {
        return $this->apply('reserve', (int) $reservation->organization_id, (int) $reservation->user_id, $amount, 'reservation', $reservation->id, $reservation->code);
    }

    /** Gives held credit back when the hold lapses or is cancelled; null when nothing is held or it was captured. */
    public function release(Reservation $reservation): ?VenueCreditEntry
    {
        return $this->settleReserve($reservation, 'release');
    }

    /** The booking is confirmed: held credit becomes the owner's revenue; null when nothing is held or it was released. */
    public function capture(Reservation $reservation): ?VenueCreditEntry
    {
        return $this->settleReserve($reservation, 'capture');
    }

    public function spend(int $organizationId, int $userId, int $amount, string $sourceType, int|string $sourceId, ?int $createdBy = null, ?string $reference = null): VenueCreditEntry
    {
        return $this->apply('spend', $organizationId, $userId, $amount, $sourceType, $sourceId, $reference, $createdBy);
    }

    /** A POS refund pays the credit part back as credit. */
    public function returnCredit(int $organizationId, int $userId, int $amount, string $sourceType, int|string $sourceId, ?int $createdBy = null, ?string $reference = null): VenueCreditEntry
    {
        return $this->apply('return', $organizationId, $userId, $amount, $sourceType, $sourceId, $reference, $createdBy);
    }

    public function cashOut(int $organizationId, int $userId, int $amount, int $codeId, int $cashierId, string $reference): VenueCreditEntry
    {
        return $this->apply('cash_out', $organizationId, $userId, $amount, 'venue_credit_cash_out_code', $codeId, $reference, $cashierId);
    }

    public function balance(int $organizationId, int $userId): int
    {
        return (int) VenueCreditAccount::query()->withoutGlobalScope('tenant_isolation')
            ->where('organization_id', $organizationId)->where('user_id', $userId)->value('balance');
    }

    /**
     * A customer's credit at each organization with its latest entries (venue-credit "Venue credit visibility").
     *
     * @return Collection<int, VenueCreditAccount>
     */
    public function accountsOf(int $userId): Collection
    {
        // ponytail: the latest 50 entries per organization; page them once customers outgrow that.
        return VenueCreditAccount::query()->withoutGlobalScope('tenant_isolation')->where('user_id', $userId)
            ->with('organization:id,name')->orderBy('organization_id')->get()
            ->each(fn (VenueCreditAccount $account) => $account->setRelation('entries', $account->entries()->withoutGlobalScope('tenant_isolation')->latest('id')->limit(50)->get()));
    }

    /** What an organization still owes its customers in credit, held credit included. */
    public function outstanding(int $organizationId): int
    {
        return (int) VenueCreditAccount::query()->withoutGlobalScope('tenant_isolation')->where('organization_id', $organizationId)->sum(DB::raw('balance + reserved'));
    }

    /** @return LengthAwarePaginator<int, VenueCreditEntry> */
    public function entriesOf(int $organizationId, int $perPage): LengthAwarePaginator
    {
        return VenueCreditEntry::query()->withoutGlobalScope('tenant_isolation')->where('organization_id', $organizationId)
            ->with('user:id,name')->latest('id')->paginate($perPage);
    }

    /** Credit held by an unpaid booking, 0 when none. */
    public function reservedFor(Reservation $reservation): int
    {
        return (int) $this->entry('reservation', $reservation->id, 'reserve')?->reserved_delta;
    }

    private function settleReserve(Reservation $reservation, string $type): ?VenueCreditEntry
    {
        $reserve = $this->entry('reservation', $reservation->id, 'reserve');
        if (! $reserve) {
            return null;
        }

        return DB::transaction(function () use ($reserve, $type): ?VenueCreditEntry {
            $this->lockAccount((int) $reserve->organization_id, (int) $reserve->user_id);
            $settled = VenueCreditEntry::query()->withoutGlobalScope('tenant_isolation')
                ->where('source_type', 'reservation')->where('source_id', $reserve->source_id)->whereIn('type', ['release', 'capture'])->first();
            if ($settled) {
                return $settled->type === $type ? $settled : null;
            }
            return $this->apply($type, (int) $reserve->organization_id, (int) $reserve->user_id, (int) $reserve->reserved_delta, 'reservation', $reserve->source_id, $reserve->reference);
        });
    }

    private function apply(string $type, int $organizationId, int $userId, int $amount, string $sourceType, int|string $sourceId, ?string $reference, ?int $createdBy = null, array $metadata = []): VenueCreditEntry
    {
        abort_if($amount <= 0, 422, 'Số credit phải lớn hơn 0.');
        [$balanceDelta, $reservedDelta] = [self::DELTAS[$type][0] * $amount, self::DELTAS[$type][1] * $amount];

        return DB::transaction(function () use ($type, $organizationId, $userId, $balanceDelta, $reservedDelta, $sourceType, $sourceId, $reference, $createdBy, $metadata): VenueCreditEntry {
            $account = $this->lockAccount($organizationId, $userId);
            $existing = $this->entry($sourceType, $sourceId, $type);
            if ($existing) {
                return $existing;
            }
            $balance = (int) $account->balance + $balanceDelta;
            $reserved = (int) $account->reserved + $reservedDelta;
            abort_if($balance < 0 || $reserved < 0, 422, 'Số dư credit không đủ.');

            $account->update(['balance' => $balance, 'reserved' => $reserved]);
            $entry = VenueCreditEntry::create([
                'organization_id' => $organizationId,
                'venue_credit_account_id' => $account->id,
                'user_id' => $userId,
                'type' => $type,
                'balance_delta' => $balanceDelta,
                'reserved_delta' => $reservedDelta,
                'balance_after' => $balance,
                'reserved_after' => $reserved,
                'source_type' => $sourceType,
                'source_id' => (string) $sourceId,
                'reference' => $reference,
                'created_by' => $createdBy,
                'metadata' => $metadata ?: null,
                'created_at' => now(),
            ]);
            // issue is booked by the resale receipt journal itself; reserve and release leave what the owner owes unchanged.
            if (in_array($type, ['capture', 'spend', 'return', 'cash_out'], true)) {
                $this->financials->postVenueCredit($entry);
            }

            return $entry;
        });
    }

    private function lockAccount(int $organizationId, int $userId): VenueCreditAccount
    {
        VenueCreditAccount::query()->insertOrIgnore([
            'organization_id' => $organizationId, 'user_id' => $userId, 'balance' => 0, 'reserved' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return VenueCreditAccount::query()->withoutGlobalScope('tenant_isolation')
            ->where('organization_id', $organizationId)->where('user_id', $userId)->lockForUpdate()->firstOrFail();
    }

    private function entry(string $sourceType, int|string $sourceId, string $type): ?VenueCreditEntry
    {
        return VenueCreditEntry::query()->withoutGlobalScope('tenant_isolation')
            ->where('source_type', $sourceType)->where('source_id', (string) $sourceId)->where('type', $type)->first();
    }
}
