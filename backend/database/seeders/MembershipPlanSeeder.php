<?php

namespace Database\Seeders;

use App\Models\LoyaltyRule;
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

        $orgs = Organization::where('status', 'active')->get();
        if ($orgs->isEmpty()) {
            return;
        }

        foreach ($orgs as $org) {
            $membershipPlans = [
                [
                    'name' => 'Silver Standard',
                    'code' => 'SILVER',
                    'description' => 'Dành cho người chơi phong trào tập luyện định kỳ trong tuần.',
                    'discount_percent' => 10,
                    'point_multiplier' => 1.2,
                    'advance_booking_days' => 5,
                    'free_weekend_hours' => 0,
                    'cancellation_fee_waived' => false,
                    'is_popular' => false,
                    'price' => 299000,
                    'billing_cycle' => 'monthly',
                    'perks' => [
                        'Giảm 10% cho tất cả khung giờ tiêu chuẩn',
                        'Tích lũy x1.2 điểm thưởng khi đặt sân',
                        'Đặt sân trước tối đa 5 ngày',
                    ],
                ],
                [
                    'name' => 'Gold Elite All-Access',
                    'code' => 'GOLD',
                    'description' => 'Gói hội viên phổ biến nhất, tối ưu đặc quyền giờ vàng và tích điểm.',
                    'discount_percent' => 20,
                    'point_multiplier' => 1.8,
                    'advance_booking_days' => 10,
                    'free_weekend_hours' => 2,
                    'cancellation_fee_waived' => true,
                    'is_popular' => true,
                    'price' => 699000,
                    'billing_cycle' => 'monthly',
                    'perks' => [
                        'Giảm 20% đặt sân toàn hệ thống',
                        'Tích lũy x1.8 điểm thưởng & quà tặng tháng',
                        'Tặng 2 giờ chơi sân cuối tuần mỗi tháng',
                        'Miễn phí hủy sân trước 24h không mất cọc',
                    ],
                ],
                [
                    'name' => 'Diamond VIP Club',
                    'code' => 'DIAMOND',
                    'description' => 'Đẳng cấp doanh nghiệp & VĐV chuyên nghiệp với dịch vụ thượng hạng.',
                    'discount_percent' => 30,
                    'point_multiplier' => 2.5,
                    'advance_booking_days' => 30,
                    'free_weekend_hours' => 6,
                    'cancellation_fee_waived' => true,
                    'is_popular' => false,
                    'price' => 1799000,
                    'billing_cycle' => 'monthly',
                    'perks' => [
                        'Giảm 30% giá sân & giữ lịch cố định',
                        'Tích lũy x2.5 điểm thưởng mọi dịch vụ',
                        'Tặng 6 giờ chơi VIP cuối tuần',
                        'Sử dụng phòng tắm Sauna & Locker VIP',
                    ],
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
                        'benefits' => [
                            'code' => $plan['code'],
                            'description' => $plan['description'],
                            'discount_percent' => $plan['discount_percent'],
                            'point_multiplier' => $plan['point_multiplier'],
                            'advance_booking_days' => $plan['advance_booking_days'],
                            'free_weekend_hours' => $plan['free_weekend_hours'],
                            'cancellation_fee_waived' => $plan['cancellation_fee_waived'],
                            'is_popular' => $plan['is_popular'],
                            'billing_cycle' => $plan['billing_cycle'],
                            'perks' => $plan['perks'],
                        ],
                        'status' => 'published',
                        'published_at' => $now,
                    ]
                );
            }

            // Seed Loyalty Conversion Config
            LoyaltyRule::updateOrCreate(
                ['organization_id' => $org->id, 'name' => 'Default Loyalty Config'],
                [
                    'version' => 1,
                    'earn_points_per_100_vnd' => 1,
                    'redeem_points_per_100_vnd' => 100,
                    'status' => 'published',
                    'metadata' => [
                        'spend_amount_vnd' => 1000,
                        'points_earned' => 1,
                        'points_redeemed' => 100,
                        'reward_value_vnd' => 5000,
                    ],
                    'published_at' => $now,
                ]
            );

            // Seed Multiplier Rules
            $multiplierRules = [
                [
                    'name' => 'Khung giờ thấp điểm (Off-peak Happy Hours)',
                    'multiplier' => 2.0,
                    'tag' => 'Off-peak',
                    'schedule' => 'T2 - T6, 08:00 - 15:00',
                    'description' => 'Áp dụng nhân đôi điểm thưởng cho tất cả lượt đặt sân trong khung giờ thấp điểm.',
                    'is_active' => true,
                ],
                [
                    'name' => 'Tuần lễ Sinh nhật Hội viên',
                    'multiplier' => 3.0,
                    'tag' => 'Birthday',
                    'schedule' => 'Trong tháng sinh nhật của khách hàng',
                    'description' => 'Tặng x3 điểm cho mọi chi tiêu dịch vụ trong tháng sinh nhật của hội viên VIP.',
                    'is_active' => true,
                ],
                [
                    'name' => 'Giải đấu & Sự kiện CLB',
                    'multiplier' => 1.5,
                    'tag' => 'Tournament',
                    'schedule' => 'Thứ 7 & Chủ nhật tuần đầu tháng',
                    'description' => 'Hệ số x1.5 cho vận động viên tham gia thi đấu giải nội bộ.',
                    'is_active' => false,
                ],
            ];

            foreach ($multiplierRules as $rule) {
                LoyaltyRule::updateOrCreate(
                    ['organization_id' => $org->id, 'name' => $rule['name']],
                    [
                        'version' => 1,
                        'earn_points_per_100_vnd' => (int) round($rule['multiplier']),
                        'redeem_points_per_100_vnd' => 100,
                        'status' => $rule['is_active'] ? 'published' : 'draft',
                        'metadata' => [
                            'is_multiplier_rule' => true,
                            'multiplier' => $rule['multiplier'],
                            'tag' => $rule['tag'],
                            'schedule' => $rule['schedule'],
                            'description' => $rule['description'],
                            'is_active' => $rule['is_active'],
                        ],
                        'published_at' => $now,
                    ]
                );
            }
        }
    }
}

