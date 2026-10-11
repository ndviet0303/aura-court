<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Events\CourtStatusUpdated;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['court_id', 'starts_at', 'ends_at', 'reason'])]
class CourtBlock extends Model
{
    protected static function booted(): void
    {
        static::saved(function (CourtBlock $block): void {
            $court = $block->court ?? Court::find($block->court_id);
            if ($court && $court->venue_id) {
                CourtStatusUpdated::dispatch(
                    venueId: (int) $court->venue_id,
                    courtId: (int) $court->id,
                    status: 'maintenance',
                    metadata: [
                        'court_block_id' => $block->id,
                        'reason' => $block->reason,
                        'starts_at' => $block->starts_at?->toIso8601String(),
                        'ends_at' => $block->ends_at?->toIso8601String(),
                    ]
                );
            }
        });

        static::deleted(function (CourtBlock $block): void {
            $court = $block->court ?? Court::find($block->court_id);
            if ($court && $court->venue_id) {
                CourtStatusUpdated::dispatch(
                    venueId: (int) $court->venue_id,
                    courtId: (int) $court->id,
                    status: 'available',
                    metadata: [
                        'court_block_id' => $block->id,
                        'event' => 'deleted',
                    ]
                );
            }
        });
    }

    protected function casts(): array
    {
        return ['starts_at' => UtcDateTime::class, 'ends_at' => UtcDateTime::class];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
