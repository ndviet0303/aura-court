<?php

namespace App\Services\Promotion;

use App\Contracts\Repositories\PromotionProgramRepositoryInterface;
use App\Contracts\Repositories\VoucherUseRepositoryInterface;
use App\Domain\Pricing\DTOs\RentalQuote;
use App\Domain\Pricing\DTOs\VoucherChoice;
use App\Exceptions\VoucherNoLongerApplies;
use App\Models\Court;
use App\Models\PromotionProgram;
use App\Models\Reservation;
use App\Models\VoucherCode;
use App\Models\VoucherUse;
use Carbon\CarbonInterface;

/**
 * The one place for voucher eligibility and the discount ("Voucher eligibility and discount"; design D17, D20, D27),
 * used by the engine, VoucherUsageService and the voucher list. The first rule that fails gives the reason, in this
 * order: not_found, stopped, not_started, expired, court_not_covered, then used or held_elsewhere for a private code
 * or exhausted for a public program, then minimum_not_met. Held and used counts come from voucher_uses, and the use
 * or code that $heldBy itself holds never counts against it (D17).
 */
class VoucherEligibility
{
    public function __construct(
        private readonly PromotionProgramRepositoryInterface $programs,
        private readonly VoucherUseRepositoryInterface $uses,
    ) {}

    /** $base with $choice applied at $at, or VoucherNoLongerApplies carrying $base. */
    public function apply(Court $court, VoucherChoice $choice, RentalQuote $base, CarbonInterface $at, ?Reservation $heldBy = null): RentalQuote
    {
        [$program, $code] = $this->resolve($court, $choice) ?? throw new VoucherNoLongerApplies('not_found', $base);

        return $this->check($program, $code, $court, $base, $at, $heldBy);
    }

    /**
     * The program and code $choice names for the court's venue, or null (not_found). A program id naming a private
     * program or another venue's program is not found; a code is matched after trim and upper case.
     *
     * @return array{0: PromotionProgram, 1: ?VoucherCode}|null
     */
    public function resolve(Court $court, VoucherChoice $choice, bool $forUpdate = false): ?array
    {
        $code = $choice->code === null ? null : $this->programs->findCode(strtoupper(trim($choice->code)));
        $programId = $choice->programId ?? $code?->program_id;
        if ($programId === null) {
            return null;
        }
        $program = $forUpdate ? $this->programs->findForUpdate((int) $programId) : $this->programs->find((int) $programId);
        $type = $code === null ? PromotionProgram::TYPE_PUBLIC : PromotionProgram::TYPE_PRIVATE;
        if ($program === null || $program->type !== $type || (int) $program->venue_id !== (int) $court->venue_id) {
            return null;
        }

        return [$program, $code];
    }

    /** $base with the program's discount and terms in its snapshot, or VoucherNoLongerApplies carrying $base. */
    public function check(PromotionProgram $program, ?VoucherCode $code, Court $court, RentalQuote $base, CarbonInterface $at, ?Reservation $heldBy = null): RentalQuote
    {
        $reason = $this->failure($program, $code, $court, $base->rental, $at, $heldBy === null ? null : (int) $heldBy->getKey());
        if ($reason !== null) {
            throw new VoucherNoLongerApplies($reason, $base);
        }
        $discount = $this->discount($program, $base->rental);

        return new RentalQuote($base->rental, $discount, $base->rental - $discount, [
            'parts' => $base->snapshot['parts'],
            'voucher' => [
                'program_id' => (int) $program->id,
                'name' => $program->name,
                'type' => $program->type,
                'code' => $code?->code,
                'discount_kind' => $program->discount_kind,
                'percent_bp' => $program->percent_bp,
                'amount' => $program->amount,
                'max_discount' => $program->max_discount,
                'min_rental' => $program->min_rental,
                'starts_at' => $program->starts_at->toIso8601String(),
                'ends_at' => $program->ends_at->toIso8601String(),
                'discount' => $discount,
            ],
        ]);
    }

    /**
     * The public vouchers that apply to $base on the court at $at, default first (D10): the largest discount, then
     * the earlier end, then the earlier creation.
     *
     * @return list<RentalQuote>
     */
    public function applicablePublic(Court $court, RentalQuote $base, CarbonInterface $at): array
    {
        $applicable = [];
        foreach ($this->programs->runningPublicForCourt((int) $court->id, $at) as $program) {
            try {
                $applicable[] = [$program, $this->check($program, null, $court, $base, $at)];
            } catch (VoucherNoLongerApplies) {
                // Not listed.
            }
        }
        usort($applicable, fn (array $a, array $b): int => $b[1]->discount <=> $a[1]->discount
            ?: $a[0]->ends_at <=> $b[0]->ends_at
            ?: $a[0]->created_at <=> $b[0]->created_at
            ?: $a[0]->id <=> $b[0]->id);

        return array_map(fn (array $pair): RentalQuote => $pair[1], $applicable);
    }

    private function failure(PromotionProgram $program, ?VoucherCode $code, Court $court, int $rental, CarbonInterface $at, ?int $heldById): ?string
    {
        if ($program->stopped_at !== null) {
            return 'stopped';
        }
        if ($at->lt($program->starts_at)) {
            return 'not_started';
        }
        if ($at->gte($program->ends_at)) {
            return 'expired';
        }
        if (! $this->programs->coversCourt($program, (int) $court->id)) {
            return 'court_not_covered';
        }
        if ($code !== null) {
            $active = $this->uses->activeForCode((int) $code->id);
            if ($active !== null && (int) $active->reservation_id !== $heldById) {
                return $active->status === VoucherUse::STATUS_USED ? 'used' : 'held_elsewhere';
            }
        } elseif ($this->uses->countActive((int) $program->id, $heldById) >= (int) $program->max_uses) {
            return 'exhausted';
        }

        return $program->min_rental !== null && $rental < $program->min_rental ? 'minimum_not_met' : null;
    }

    /** BR-66 in integers (D27): min(⌊T × percent_bp / 10000⌋, max_discount, T), or min(amount, T); never a float. */
    private function discount(PromotionProgram $program, int $rental): int
    {
        $discount = $program->discount_kind === PromotionProgram::KIND_PERCENT
            ? min(intdiv($rental * (int) $program->percent_bp, 10000), $program->max_discount ?? PHP_INT_MAX)
            : (int) $program->amount;

        return min($discount, $rental);
    }
}
