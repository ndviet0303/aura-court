<?php

namespace App\Http\Resources\Pricing;

use App\Models\CourtPriceTableLink;
use App\Models\PriceTable;
use App\Models\PriceTableInterval;
use App\Models\PriceTableVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A price table with its links in effect now or later, and on the detail its versions (newest first). Interval
 * times are the venue's local H:i, an end of day reading 23:59 (design D15); instants are ISO-8601 UTC.
 *
 * @mixin PriceTable
 */
class PriceTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'venue_id' => (int) $this->venue_id,
            'name' => (string) $this->name,
            'kind' => (string) $this->kind,
            'status' => (string) $this->status,
            'versions' => $this->whenLoaded('versions', fn (): array => $this->versions->map(fn (PriceTableVersion $version): array => [
                'id' => (int) $version->id,
                'published_at' => $version->published_at->toIso8601String(),
                'effective_at' => $version->effective_at->toIso8601String(),
                'intervals' => $version->intervals->map(fn (PriceTableInterval $interval): array => [
                    'weekdays' => $interval->weekdays,
                    'dates' => $interval->dates,
                    'start' => self::clock($interval->start_minute),
                    'end' => self::clock($interval->end_minute),
                    'price_per_hour' => $interval->price_per_hour,
                ])->all(),
            ])->all()),
            'links' => $this->links->map(fn (CourtPriceTableLink $link): array => [
                'court_id' => (int) $link->court_id,
                // D9: the court's code is its display name; the key stays for module 3's API.
                'court_name' => (string) $link->court->code,
                'starts_at' => $link->starts_at->toIso8601String(),
                'ends_at' => $link->ends_at?->toIso8601String(),
            ])->all(),
        ];
    }

    private static function clock(int $minute): string
    {
        return $minute === 1440 ? '23:59' : sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
