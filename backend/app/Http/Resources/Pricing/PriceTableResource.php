<?php

namespace App\Http\Resources\Pricing;

use App\Models\CourtPriceTableLink;
use App\Models\PriceTable;
use App\Models\PriceTableInterval;
use App\Models\PriceTableVersion;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A price table with its links in effect now or later, and on the detail its versions newest first, the order
 * loadDetail() gives and the version states rely on. Interval times are the venue's local H:i, an end of day
 * reading 23:59 (design D15); instants are ISO-8601 UTC.
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
            'versions' => $this->whenLoaded('versions', fn (): array => $this->versionRows()),
            'links' => $this->links->map(fn (CourtPriceTableLink $link): array => [
                'court_id' => (int) $link->court_id,
                'court_name' => (string) $link->court->name,
                'starts_at' => $link->starts_at->toIso8601String(),
                'ends_at' => $link->ends_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * The versions as loaded, newest first, each with its state at now under design D6: 'active' prices play now,
     * 'scheduled' will price play from its effective time because no version published after it takes effect
     * at or before then, 'superseded' will price nothing from now on.
     *
     * @return list<array<string, mixed>>
     */
    private function versionRows(): array
    {
        $now = CarbonImmutable::now();
        $active = $this->versions->first(fn (PriceTableVersion $version): bool => $version->effective_at <= $now);
        // The earliest effective time among the versions published after the one at hand.
        $laterEffectiveAt = null;
        $rows = [];
        foreach ($this->versions as $version) {
            $state = match (true) {
                $version->is($active) => 'active',
                $version->effective_at > $now && ($laterEffectiveAt === null || $version->effective_at < $laterEffectiveAt) => 'scheduled',
                default => 'superseded',
            };
            $laterEffectiveAt = $laterEffectiveAt === null ? $version->effective_at : min($laterEffectiveAt, $version->effective_at);
            $rows[] = [
                'id' => (int) $version->id,
                'published_at' => $version->published_at->toIso8601String(),
                'effective_at' => $version->effective_at->toIso8601String(),
                'state' => $state,
                'intervals' => $version->intervals->map(fn (PriceTableInterval $interval): array => [
                    'weekdays' => $interval->weekdays,
                    'dates' => $interval->dates,
                    'start' => self::clock($interval->start_minute),
                    'end' => self::clock($interval->end_minute),
                    'price_per_hour' => $interval->price_per_hour,
                ])->all(),
            ];
        }

        return $rows;
    }

    private static function clock(int $minute): string
    {
        return $minute === 1440 ? '23:59' : sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
