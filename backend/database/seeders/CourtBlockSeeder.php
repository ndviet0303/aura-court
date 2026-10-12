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
        $today = Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'))->startOfDay();
        $nextMonday = $today->copy()->startOfWeek()->addWeek();
        $saturday = $today->copy()->next(Carbon::SATURDAY);

        // [court slug, local start, hours, reason, created days ago]
        $blocks = [
            ['san-2-tieu-chuan-cau-giay', $nextMonday->copy()->addDay()->setTime(6, 0), 4, 'Bảo trì định kỳ: thay thảm và căng lại lưới sân 2', 5],
            ['san-tennis-1-dat-nen-tay-ho', $nextMonday->copy()->addDays(2)->setTime(13, 0), 4, 'Bảo dưỡng mặt sân đất nện: lu nền và kẻ lại vạch', 4],
            ['san-bong-ro-in-house-nba-landmark', $saturday->copy()->setTime(18, 0), 4, 'Sự kiện riêng: giao lưu bóng rổ doanh nghiệp, thuê trọn sân', 6],
            ['san-bong-da-mini-pmh', $saturday->copy()->addDay()->setTime(7, 0), 4, 'Sự kiện riêng: giải bóng đá mini cư dân Phú Mỹ Hưng', 9],
            ['san-bong-ban-2-stiga-master-quan-ngua', $today->copy()->subDays(3)->setTime(14, 0), 3, 'Thay mặt bàn và lưới bàn bóng bàn số 2', 10],
        ];

        foreach ($blocks as [$slug, $startsAt, $hours, $reason, $createdDaysAgo]) {
            $court = Court::where('slug', $slug)->first();
            if (! $court) {
                continue;
            }

            // Keyed by court and reason so a re-seed never moves a block under bookings seeded around it.
            CourtBlock::firstOrCreate(['court_id' => $court->id, 'reason' => $reason], [
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHours($hours),
                'created_at' => $today->copy()->subDays($createdDaysAgo)->setTime(9, 30),
                'updated_at' => $today->copy()->subDays($createdDaysAgo)->setTime(9, 30),
            ]);
        }
    }
}
