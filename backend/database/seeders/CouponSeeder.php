<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Court;
use App\Models\Organization;
use App\Models\PromotionCampaign;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Promotion campaigns with their coupon codes, shaped like PromotionController creates them:
 * every coupon belongs to a campaign of its organization, and status/dates drive DiscountService.
 */
class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'));
        $today = $now->copy()->startOfDay();

        $flagship = Organization::where('slug', 'aura-badminton-club')->first();
        $partner = Organization::where('slug', 'hoang-gia-sports')->first();
        $owner = User::where('email', 'owner@demo.auracourt.vn')->first();
        $partnerOwner = User::where('email', 'owner.hoanggia@demo.auracourt.vn')->first();
        $venue = fn (string $slug): ?int => Venue::where('slug', $slug)->value('id');
        $court = fn (string $slug): ?int => Court::where('slug', $slug)->value('id');

        $campaigns = [
            [
                'org' => $flagship,
                'author' => $owner,
                'name' => 'Mùa giải Aura - Ưu đãi toàn hệ thống',
                'description' => 'Giảm 10% tiền sân cho mọi bộ môn tại 5 cơ sở Aura trong suốt mùa giải.',
                'status' => 'active',
                'starts_at' => $today->copy()->subDays(20),
                'ends_at' => $today->copy()->addDays(45)->endOfDay(),
                'usage_limit' => 500,
                'min_spend' => 100000,
                'coupons' => [
                    ['code' => 'AURA2026', 'type' => 'percent', 'value' => 10, 'max' => 200000, 'min_spend' => 100000, 'limit' => 500, 'used' => 24],
                ],
            ],
            [
                'org' => $flagship,
                'author' => $owner,
                'name' => 'Aura VIP - Đội nhóm sân lớn',
                'description' => 'Ưu đãi cho đội nhóm đặt sân bóng đá, bóng rổ, bóng chuyền từ 600.000₫. Không cộng dồn ưu đãi hội viên.',
                'status' => 'active',
                'starts_at' => $today->copy()->subDays(10),
                'ends_at' => $today->copy()->addDays(50)->endOfDay(),
                'usage_limit' => 100,
                'min_spend' => 600000,
                'combine' => false,
                'coupons' => [
                    ['code' => 'AURAVIP', 'type' => 'percent', 'value' => 15, 'max' => 300000, 'min_spend' => 600000, 'limit' => 100, 'used' => 9],
                ],
            ],
            [
                'org' => $flagship,
                'author' => $owner,
                'name' => 'Chào bạn mới',
                'description' => 'Tặng 50.000₫ cho lượt đặt sân đầu tiên tại hệ thống Aura.',
                'status' => 'active',
                'group' => Coupon::GROUP_FIRST_TIME,
                'starts_at' => $today->copy()->subDays(60),
                'ends_at' => $today->copy()->addDays(90)->endOfDay(),
                'usage_limit' => 300,
                'min_spend' => 100000,
                'coupons' => [
                    ['code' => 'CHAOBANMOI', 'type' => 'fixed', 'value' => 50000, 'max' => null, 'min_spend' => 100000, 'limit' => 300, 'used' => 41],
                ],
            ],
            [
                'org' => $flagship,
                'author' => $owner,
                'name' => 'Pickleball Hoàng Hôn Hồ Tây',
                'description' => 'Giảm 20% khi đặt Sân Pickleball 3 - Sunset View Hồ Tây.',
                'status' => 'active',
                'venue' => 'aura-complex-tay-ho',
                'court' => 'san-pickleball-3-sunset-tay-ho',
                'starts_at' => $today->copy()->subDays(14),
                'ends_at' => $today->copy()->addDays(30)->endOfDay(),
                'usage_limit' => 200,
                'min_spend' => 150000,
                'coupons' => [
                    ['code' => 'SUNSET20', 'type' => 'percent', 'value' => 20, 'max' => 100000, 'min_spend' => 150000, 'limit' => 200, 'used' => 18],
                ],
            ],
            [
                'org' => $flagship,
                'author' => $owner,
                'name' => 'Đêm bóng đá Quần Ngựa',
                'description' => 'Giảm 100.000₫ cho trận bóng đá từ 500.000₫ tại Aura Arena Quần Ngựa. Đã hết lượt trong đợt này.',
                'status' => 'active',
                'venue' => 'aura-arena-quan-ngua',
                'starts_at' => $today->copy()->subDays(25),
                'ends_at' => $today->copy()->addDays(20)->endOfDay(),
                'usage_limit' => 150,
                'min_spend' => 500000,
                'coupons' => [
                    ['code' => 'FOOTBALLNIGHT', 'type' => 'fixed', 'value' => 100000, 'max' => null, 'min_spend' => 500000, 'limit' => 150, 'used' => 150],
                ],
            ],
            [
                'org' => $flagship,
                'author' => $owner,
                'name' => 'Tennis Landmark 72 - Tháng mở màn',
                'description' => 'Giảm 15% Sân Tennis 3 - Mặt Cứng US Open tại Landmark 72, áp dụng từ tuần sau.',
                'status' => 'active',
                'venue' => 'aura-landmark-72',
                'court' => 'san-tennis-3-mat-cung-landmark',
                'starts_at' => $today->copy()->addDays(7),
                'ends_at' => $today->copy()->addDays(37)->endOfDay(),
                'usage_limit' => 100,
                'min_spend' => 300000,
                'activated_at' => $now->copy()->subDays(2),
                'coupons' => [
                    ['code' => 'TENNIS72', 'type' => 'percent', 'value' => 15, 'max' => 150000, 'min_spend' => 300000, 'limit' => 100, 'used' => 0],
                ],
            ],
            [
                'org' => $flagship,
                'author' => $owner,
                'name' => 'Khai trương Aura Sài Gòn',
                'description' => 'Giảm 25% mừng khai trương Aura Sports Center Sài Gòn. Chương trình đã kết thúc.',
                'status' => 'retired',
                'venue' => 'aura-central-saigon',
                'starts_at' => $today->copy()->subDays(75),
                'ends_at' => $today->copy()->subDays(15)->endOfDay(),
                'usage_limit' => 300,
                'min_spend' => 100000,
                'coupons' => [
                    ['code' => 'SAIGONOPEN', 'type' => 'percent', 'value' => 25, 'max' => 150000, 'min_spend' => 100000, 'limit' => 300, 'used' => 212],
                ],
            ],
            [
                'org' => $flagship,
                'author' => $owner,
                'name' => 'Lễ hội thể thao cộng đồng',
                'description' => 'Bản nháp: giảm 30.000₫ cho người chơi tham gia ngày hội thể thao cộng đồng.',
                'status' => 'draft',
                'starts_at' => $today->copy()->addDays(30),
                'ends_at' => $today->copy()->addDays(32)->endOfDay(),
                'usage_limit' => 200,
                'min_spend' => 80000,
                'coupons' => [
                    ['code' => 'SPORTFEST', 'type' => 'fixed', 'value' => 30000, 'max' => null, 'min_spend' => 80000, 'limit' => 200, 'used' => 0],
                ],
            ],
            [
                'org' => $partner,
                'author' => $partnerOwner,
                'name' => 'Hoàng Gia Pickleball Club',
                'description' => 'Giảm 50.000₫ cho lượt đặt sân từ 200.000₫ tại Hoàng Gia Sports Phú Mỹ Hưng.',
                'status' => 'active',
                'starts_at' => $today->copy()->subDays(30),
                'ends_at' => $today->copy()->addDays(30)->endOfDay(),
                'usage_limit' => 200,
                'min_spend' => 200000,
                'coupons' => [
                    ['code' => 'HOANGGIA50', 'type' => 'fixed', 'value' => 50000, 'max' => null, 'min_spend' => 200000, 'limit' => 200, 'used' => 27],
                ],
            ],
            [
                'org' => $partner,
                'author' => $partnerOwner,
                'name' => 'Tennis sáng sớm Phú Mỹ Hưng',
                'description' => 'Giảm 10% sân tennis khung giờ sáng. Đã hết hạn, chờ chủ sân lưu trữ.',
                'status' => 'active',
                'starts_at' => $today->copy()->subDays(40),
                'ends_at' => $today->copy()->subDays(5)->endOfDay(),
                'usage_limit' => 100,
                'min_spend' => 200000,
                'coupons' => [
                    ['code' => 'PMHTENNIS', 'type' => 'percent', 'value' => 10, 'max' => 50000, 'min_spend' => 200000, 'limit' => 100, 'used' => 33],
                ],
            ],
        ];

        foreach ($campaigns as $spec) {
            if (! $spec['org'] || ! $spec['author']) {
                continue;
            }

            $status = $spec['status'];
            // Scheduled campaigns were drafted days ago, never in the future.
            $createdAt = $spec['starts_at']->copy()->subDays(3)->setTime(9, 30)->min($today->copy()->subDays(3)->setTime(9, 30));
            $activatedAt = $status === 'draft' ? null : ($spec['activated_at'] ?? $spec['starts_at']->copy()->subDay()->setTime(10, 0));
            $retiredAt = $status === 'retired' ? $spec['ends_at']->copy()->addDay()->setTime(9, 0) : null;
            // Each transition bumps the version: created (1), activated (2), retired (3).
            $version = 1 + ($activatedAt ? 1 : 0) + ($retiredAt ? 1 : 0);
            $scope = [
                'venue_id' => isset($spec['venue']) ? $venue($spec['venue']) : null,
                'court_id' => isset($spec['court']) ? $court($spec['court']) : null,
            ];
            $shared = [
                'eligibility_group' => $spec['group'] ?? Coupon::GROUP_ALL,
                'can_combine_with_membership' => $spec['combine'] ?? true,
                'version' => $version,
                'created_by' => $spec['author']->id,
                'updated_by' => $spec['author']->id,
                'activated_at' => $activatedAt,
                'suspended_at' => null,
                'retired_at' => $retiredAt,
                'created_at' => $createdAt,
                'updated_at' => $retiredAt ?? $activatedAt ?? $createdAt,
            ];

            $campaign = PromotionCampaign::firstOrNew(['organization_id' => $spec['org']->id, 'name' => $spec['name']]);
            $campaign->forceFill($scope + $shared + [
                'description' => $spec['description'],
                'status' => $status,
                'starts_at' => $spec['starts_at'],
                'ends_at' => $spec['ends_at'],
                'usage_limit' => $spec['usage_limit'],
                'usage_count' => array_sum(array_column($spec['coupons'], 'used')),
                'min_spend' => $spec['min_spend'],
            ])->save();

            foreach ($spec['coupons'] as $c) {
                Coupon::firstOrNew(['code' => $c['code']])->forceFill($scope + $shared + [
                    'organization_id' => $spec['org']->id,
                    'promotion_campaign_id' => $campaign->id,
                    'discount_type' => $c['type'],
                    'discount_value' => $c['value'],
                    'max_discount' => $c['max'],
                    'min_spend' => $c['min_spend'],
                    'usage_limit' => $c['limit'],
                    'usage_count' => $c['used'],
                    'starts_at' => $spec['starts_at'],
                    'ends_at' => $spec['ends_at'],
                    'status' => $status,
                    'is_active' => $status === Coupon::STATUS_ACTIVE,
                    'metadata' => null,
                ])->save();
            }
        }
    }
}
