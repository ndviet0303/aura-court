<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\MarketplaceListing;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $org = Organization::whereHas('venues')->first() ?? Organization::first();
        if (! $org) {
            return;
        }

        $sellerUsers = User::where('email', 'like', 'khach%')->get();
        if ($sellerUsers->isEmpty()) {
            $sellerUsers = User::limit(5)->get();
        }

        $courts = Court::with('venue')->get();
        if ($courts->isEmpty()) {
            return;
        }

        $now = Carbon::now($timezone);

        // Clean up previous marketplace listings & entitlements to prevent duplicate unique key constraint violations
        MarketplaceListing::query()->delete();
        ReservationEntitlement::whereHas('reservation', fn ($q) => $q->where('code', 'like', 'RES-P2P-%')->orWhere('code', 'like', 'RES-MY-%'))->delete();
        ReservationSlot::whereHas('reservation', fn ($q) => $q->where('code', 'like', 'RES-P2P-%')->orWhere('code', 'like', 'RES-MY-%'))->delete();
        Reservation::where('code', 'like', 'RES-P2P-%')->orWhere('code', 'like', 'RES-MY-%')->delete();

        // Sample slots schedule covering morning (06-12), afternoon (12-17), and evening (17-22)
        $sampleData = [
            [
                'days_ahead' => 1,
                'start_hour' => 18,
                'end_hour' => 20,
                'original_price' => 200000,
                'asking_price' => 150000,
                'badge' => 'hot',
                'court_keyword' => 'san-1',
                'notes' => 'Bận việc gia đình đột xuất pass lại giá tốt cho anh em giao lưu.',
            ],
            [
                'days_ahead' => 1,
                'start_hour' => 14,
                'end_hour' => 16,
                'original_price' => 220000,
                'asking_price' => 160000,
                'badge' => 'deal',
                'court_keyword' => 'san-2',
                'notes' => 'Slot buổi chiều mát mẻ, nhượng lại cho nhóm bạn đánh đơn/đôi.',
            ],
            [
                'days_ahead' => 2,
                'start_hour' => 8,
                'end_hour' => 10,
                'original_price' => 260000,
                'asking_price' => 190000,
                'badge' => 'deal',
                'court_keyword' => 'pickleball',
                'notes' => 'Slot sáng cuối tuần đẹp, nhượng lại do trùng lịch thi đấu.',
            ],
            [
                'days_ahead' => 2,
                'start_hour' => 19,
                'end_hour' => 21,
                'original_price' => 360000,
                'asking_price' => 280000,
                'badge' => 'hot',
                'court_keyword' => 'san-5',
                'notes' => 'Sân VIP giờ vàng tối, ưu tiên anh em chốt sớm.',
            ],
            [
                'days_ahead' => 3,
                'start_hour' => 15,
                'end_hour' => 17,
                'original_price' => 240000,
                'asking_price' => 180000,
                'badge' => 'deal',
                'court_keyword' => 'san-3',
                'notes' => 'Slot chiều tiêu chuẩn, tặng kèm 1 ống cầu mới.',
            ],
            [
                'days_ahead' => 3,
                'start_hour' => 17,
                'end_hour' => 18,
                'original_price' => 180000,
                'asking_price' => 130000,
                'badge' => 'urgent',
                'court_keyword' => 'san-4',
                'notes' => 'Pass gấp giá siêu rẻ cho anh em vào sân luôn.',
            ],
            [
                'days_ahead' => 4,
                'start_hour' => 7,
                'end_hour' => 9,
                'original_price' => 300000,
                'asking_price' => 200000,
                'badge' => 'deal',
                'court_keyword' => 'san-3',
                'notes' => 'Slot thể dục sáng sớm, không gian thoáng đãng sạch sẽ.',
            ],
            [
                'days_ahead' => 5,
                'start_hour' => 20,
                'end_hour' => 22,
                'original_price' => 280000,
                'asking_price' => 210000,
                'badge' => 'hot',
                'court_keyword' => 'san-8',
                'notes' => 'Slot tối muộn sân Replay công nghệ cao tại Quần Ngựa.',
            ],
            [
                'days_ahead' => 6,
                'start_hour' => 16,
                'end_hour' => 18,
                'original_price' => 320000,
                'asking_price' => 240000,
                'badge' => 'deal',
                'court_keyword' => 'tennis',
                'notes' => 'Sân Tennis mặt cứng Tây Hồ view thoáng mát hoàng hôn.',
            ],
        ];

        foreach ($sampleData as $idx => $sample) {
            $seller = $sellerUsers->get($idx % $sellerUsers->count());
            
            // Match court by keyword if possible, otherwise by modulo
            $court = $courts->first(fn ($c) => str_contains($c->slug, $sample['court_keyword']))
                ?? $courts->get($idx % $courts->count());

            $startsAt = $now->copy()->addDays($sample['days_ahead'])->setHour($sample['start_hour'])->setMinute(0)->setSecond(0);
            $endsAt = $now->copy()->addDays($sample['days_ahead'])->setHour($sample['end_hour'])->setMinute(0)->setSecond(0);

            $res = Reservation::create([
                'organization_id' => $org->id,
                'user_id' => $seller->id,
                'court_id' => $court->id,
                'code' => 'RES-P2P-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                'status' => 'confirmed',
                'currency' => 'VND',
                'total_amount' => $sample['original_price'],
                'confirmed_at' => $now,
            ]);

            $slot = ReservationSlot::create([
                'organization_id' => $org->id,
                'reservation_id' => $res->id,
                'court_id' => $court->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'unit_price' => $sample['original_price'],
                'status' => 'confirmed',
            ]);

            $entitlement = ReservationEntitlement::create([
                'organization_id' => $org->id,
                'reservation_id' => $res->id,
                'reservation_slot_id' => $slot->id,
                'user_id' => $seller->id,
                'court_id' => $court->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'amount' => $sample['original_price'],
                'status' => 'active',
            ]);

            MarketplaceListing::create([
                'organization_id' => $org->id,
                'reservation_entitlement_id' => $entitlement->id,
                'seller_id' => $seller->id,
                'original_price' => $sample['original_price'],
                'asking_price' => $sample['asking_price'],
                'fee_bps' => 500,
                'status' => 'listed',
                'cutoff_at' => $startsAt->copy()->subHours(2),
                'metadata' => [
                    'badge' => $sample['badge'],
                    'notes' => $sample['notes'],
                ],
            ]);
        }

        // Seed 2 eligible entitlements for demo user (user@example.com) to test the Sell feature!
        $demoUser = User::where('email', 'user@example.com')->first();
        if ($demoUser) {
            $court = $courts->first();
            $startsAt1 = $now->copy()->addDays(2)->setHour(18)->setMinute(0)->setSecond(0);
            $endsAt1 = $now->copy()->addDays(2)->setHour(20)->setMinute(0)->setSecond(0);

            $res1 = Reservation::create([
                'organization_id' => $org->id,
                'user_id' => $demoUser->id,
                'court_id' => $court->id,
                'code' => 'RES-MY-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                'status' => 'confirmed',
                'currency' => 'VND',
                'total_amount' => 250000,
                'confirmed_at' => $now,
            ]);

            $slot1 = ReservationSlot::create([
                'organization_id' => $org->id,
                'reservation_id' => $res1->id,
                'court_id' => $court->id,
                'starts_at' => $startsAt1,
                'ends_at' => $endsAt1,
                'unit_price' => 250000,
                'status' => 'confirmed',
            ]);

            ReservationEntitlement::create([
                'organization_id' => $org->id,
                'reservation_id' => $res1->id,
                'reservation_slot_id' => $slot1->id,
                'user_id' => $demoUser->id,
                'court_id' => $court->id,
                'starts_at' => $startsAt1,
                'ends_at' => $endsAt1,
                'amount' => 250000,
                'status' => 'active',
            ]);
        }
    }
}
