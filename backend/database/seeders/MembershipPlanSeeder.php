<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use App\Models\Organization;
use App\Models\TenantMembershipPlan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MembershipPlanSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);

        $org = Organization::where('slug', 'aura-badminton-club')->first() ?? Organization::first();
        if (! $org) {
            return;
        }

        $membershipPlans = [
            [
                'name' => 'Gói Thành Viên Bạc (Silver Pass)',
                'description' => 'Giảm 10% cho tất cả các lượt đặt sân trong khung giờ thường.',
                'discount_percent' => 10,
                'price' => 299000,
            ],
            [
                'name' => 'Gói Thành Viên Vàng VIP (Gold All-Access)',
                'description' => 'Giảm 20% đặt sân toàn hệ thống, tặng 2 nước Pocari mỗi lượt chơi và miễn phí gửi xe.',
                'discount_percent' => 20,
                'price' => 799000,
            ],
            [
                'name' => 'Gói Kim Cương Doanh Nghiệp (Diamond Club)',
                'description' => 'Giảm 30% giá sân, ưu tiên giữ lịch cố định theo tháng và sử dụng phòng tắm VIP Sauna.',
                'discount_percent' => 30,
                'price' => 1999000,
            ],
        ];

        foreach ($membershipPlans as $plan) {
            MembershipPlan::updateOrCreate(
                ['name' => $plan['name']],
                [
                    'description' => $plan['description'],
                    'discount_percent' => $plan['discount_percent'],
                    'is_active' => true,
                ]
            );

            TenantMembershipPlan::updateOrCreate(
                ['organization_id' => $org->id, 'name' => $plan['name']],
                [
                    'version' => 1,
                    'price' => $plan['price'],
                    'benefits' => ['discount' => $plan['discount_percent'], 'description' => $plan['description']],
                    'status' => 'published',
                    'published_at' => $now,
                ]
            );
        }
    }
}
