<?php

namespace Database\Seeders;

use App\Contracts\Repositories\PromotionProgramRepositoryInterface;
use App\Models\Court;
use App\Models\PromotionProgram;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * Demo vouchers of the flagship venue (design D8): a public program for every court and a private one with its codes
 * for the first two, both running from a week ago for two months. A program already seeded by name is left alone.
 */
class PromotionProgramSeeder extends Seeder
{
    public function run(): void
    {
        $venue = Venue::where('slug', 'aura-court')->first();
        $courtIds = $venue ? Court::where('venue_id', $venue->id)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all() : [];
        if ($courtIds === []) {
            return;
        }

        $programs = app(PromotionProgramRepositoryInterface::class);
        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $window = ['starts_at' => $today->copy()->subDays(7), 'ends_at' => $today->copy()->addDays(60)];
        foreach ([
            [
                'name' => 'Chào mùa giải - giảm 20%',
                'type' => PromotionProgram::TYPE_PUBLIC,
                'discount_kind' => PromotionProgram::KIND_PERCENT,
                'percent_bp' => 2000,
                'max_discount' => 50000,
                'min_rental' => 100000,
                'max_uses' => 100,
                'court_ids' => $courtIds,
            ],
            [
                'name' => 'Tri ân khách quen - giảm 30.000 ₫',
                'type' => PromotionProgram::TYPE_PRIVATE,
                'discount_kind' => PromotionProgram::KIND_FIXED,
                'amount' => 30000,
                'code_count' => 20,
                'court_ids' => array_slice($courtIds, 0, 2),
            ],
        ] as $def) {
            if (PromotionProgram::where('venue_id', $venue->id)->where('name', $def['name'])->exists()) {
                continue;
            }
            $program = $programs->create(['venue_id' => $venue->id, ...Arr::except($def, 'court_ids'), ...$window], $def['court_ids']);
            if (isset($def['code_count'])) {
                $programs->createCodes($program, $def['code_count']);
            }
        }
    }
}
