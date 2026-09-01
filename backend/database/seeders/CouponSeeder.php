<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);

        $coupons = [
            [
                'code' => 'AURA2026',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'max_discount' => 200000,
                'min_spend' => 100000,
                'is_active' => true,
            ],
            [
                'code' => 'AURAVIP',
                'discount_type' => 'percent',
                'discount_value' => 15,
                'max_discount' => 500000,
                'min_spend' => 200000,
                'is_active' => true,
            ],
            [
                'code' => 'CHAOBANMOI',
                'discount_type' => 'fixed',
                'discount_value' => 50000,
                'max_discount' => null,
                'min_spend' => 100000,
                'is_active' => true,
            ],
            [
                'code' => 'PICKLEBALL2026',
                'discount_type' => 'percent',
                'discount_value' => 20,
                'max_discount' => 150000,
                'min_spend' => 120000,
                'is_active' => true,
            ],
            [
                'code' => 'FOOTBALLNIGHT',
                'discount_type' => 'fixed',
                'discount_value' => 100000,
                'max_discount' => null,
                'min_spend' => 500000,
                'is_active' => true,
            ],
            [
                'code' => 'SPORTFEST',
                'discount_type' => 'fixed',
                'discount_value' => 30000,
                'max_discount' => null,
                'min_spend' => 80000,
                'is_active' => true,
            ],
            [
                'code' => 'WEEKENDPLAY',
                'discount_type' => 'percent',
                'discount_value' => 12,
                'max_discount' => 300000,
                'min_spend' => 150000,
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $c) {
            Coupon::updateOrCreate(
                ['code' => $c['code']],
                [
                    'discount_type' => $c['discount_type'],
                    'discount_value' => $c['discount_value'],
                    'max_discount' => $c['max_discount'],
                    'min_spend' => $c['min_spend'],
                    'is_active' => $c['is_active'],
                    'starts_at' => $now->copy()->subDays(30),
                    'ends_at' => $now->copy()->addMonths(12),
                ]
            );
        }
    }
}
