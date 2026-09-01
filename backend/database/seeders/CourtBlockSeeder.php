<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\CourtBlock;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CourtBlockSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);
        $courts = Court::all();

        if ($courts->isEmpty()) {
            return;
        }

        $blockStart = $now->copy()->startOfDay()->addDays(14)->setTime(8, 0);
        foreach ([1, 5, 8, 12, 16] as $idx) {
            if (isset($courts[$idx])) {
                $court = $courts[$idx];
                CourtBlock::updateOrCreate(
                    ['court_id' => $court->id, 'starts_at' => $blockStart->copy()->utc()->format('Y-m-d H:i:s')],
                    [
                        'ends_at' => $blockStart->copy()->addHours(3)->utc()->format('Y-m-d H:i:s'),
                        'reason' => $idx % 2 === 0 ? 'Bảo trì và vệ sinh định kỳ sàn sân' : 'Tổ chức giải đấu nội bộ CLB',
                    ]
                );
            }
        }
    }
}
