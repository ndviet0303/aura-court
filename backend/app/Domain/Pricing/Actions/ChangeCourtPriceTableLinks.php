<?php

namespace App\Domain\Pricing\Actions;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Domain\Pricing\DTOs\PriceTableLinkChange;
use App\Events\CourtPricesTookEffect;
use App\Models\Court;
use App\Models\PriceTable;
use App\Services\Pricing\PriceCoverageService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies a table to courts or ends their link of one kind at a switch time s (design D2). The newest change wins:
 * each court's same-kind link in effect at s ends there and its same-kind links starting at or after s are removed.
 * The links left must cover every bookable minute from s on (D3), checked in the same transaction, so a gap changes
 * nothing. Price management and the court screen both call it; CourtPricesTookEffect follows the commit (D16).
 */
class ChangeCourtPriceTableLinks
{
    public function __construct(
        private readonly PriceTableRepositoryInterface $priceTables,
        private readonly PriceCoverageService $coverage,
    ) {}

    /** @return CarbonImmutable the switch time */
    public function execute(PriceTableLinkChange $change): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $switchAt = $change->switchAt ?? $now;
        $field = $change->priceTableId === null ? 'ends_at' : 'starts_at';
        if ($switchAt->lt($now)) {
            throw ValidationException::withMessages([$field => 'Thời điểm chuyển bảng giá không được ở quá khứ.']);
        }

        DB::transaction(function () use ($change, $switchAt): void {
            $courts = $this->priceTables->lockCourts($change->courtIds);
            $table = $change->priceTableId === null ? null : ($this->priceTables->findForUpdate($change->priceTableId) ?? abort(404));
            if ($table?->status === PriceTable::STATUS_RETIRED) {
                throw ValidationException::withMessages(['price_table' => 'Bảng giá đã ngừng sử dụng nên không áp được cho sân.']);
            }
            if ($courts->count() !== count($change->courtIds)
                || ($table && $courts->contains(fn (Court $court): bool => (int) $court->venue_id !== (int) $table->venue_id))) {
                throw ValidationException::withMessages(['court_ids' => 'Sân được chọn không thuộc cơ sở của bảng giá.']);
            }

            $this->priceTables->cutLinks($change->courtIds, $change->kind, $switchAt);
            if ($table) {
                $this->priceTables->createLinks($table, $change->courtIds, $switchAt);
            }
            // Only courts that take bookings must be covered; an inactive or closed court may keep a gap.
            $operating = $courts->filter(fn (Court $court): bool => in_array($court->status, [Court::STATUS_ACTIVE, Court::STATUS_PAUSED], true));
            $this->coverage->assertCovered(array_values($operating->modelKeys()), $switchAt);
        });

        // The public court list is cached by this key and shows only courts with a normal link in effect. Both wait
        // for the court screen's transaction when it calls this inside one (module 2).
        DB::afterCommit(fn () => Cache::increment('courts_version'));
        CourtPricesTookEffect::dispatch($change->courtIds, $switchAt);

        return $switchAt;
    }
}
