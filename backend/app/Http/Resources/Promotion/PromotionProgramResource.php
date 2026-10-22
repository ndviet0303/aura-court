<?php

namespace App\Http\Resources\Promotion;

use App\Models\Court;
use App\Models\PromotionProgram;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A program with its status at now, its courts by id and its usage counted from voucher uses (design D8, D11).
 * The percentage comes as percent_bp (12.5% = 1250, D27) and as percent for display; instants are ISO-8601 UTC.
 * Remaining is the total uses (public) or the code count (private) less the uses held and used.
 *
 * @mixin PromotionProgram
 */
class PromotionProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $held = (int) $this->getAttribute('held_uses_count');
        $used = (int) $this->getAttribute('used_uses_count');

        return [
            'id' => (int) $this->id,
            'venue_id' => (int) $this->venue_id,
            'name' => (string) $this->name,
            'type' => (string) $this->type,
            'discount_kind' => (string) $this->discount_kind,
            'percent_bp' => $this->percent_bp,
            'percent' => $this->percent_bp === null ? null : $this->percent_bp / 100,
            'amount' => $this->amount,
            'max_discount' => $this->max_discount,
            'min_rental' => $this->min_rental,
            'starts_at' => self::utc($this->starts_at),
            'ends_at' => self::utc($this->ends_at),
            'stopped_at' => $this->stopped_at === null ? null : self::utc($this->stopped_at),
            'status' => $this->statusAt(),
            'max_uses' => $this->max_uses,
            'code_count' => $this->code_count,
            // Module 2 D9: a court's code is its display name and its id its address; name and slug are no longer kept.
            'courts' => $this->whenLoaded('courts', fn (): array => $this->courts->map(fn (Court $court): array => [
                'id' => (int) $court->id,
                'name' => (string) $court->code,
            ])->all()),
            'usage' => [
                'held' => $held,
                'used' => $used,
                'remaining' => max(0, (int) ($this->max_uses ?? $this->code_count) - $held - $used),
            ],
            'created_at' => self::utc($this->created_at),
            'updated_at' => self::utc($this->updated_at),
        ];
    }

    private static function utc(DateTimeInterface $at): string
    {
        return CarbonImmutable::instance($at)->utc()->toIso8601String();
    }
}
