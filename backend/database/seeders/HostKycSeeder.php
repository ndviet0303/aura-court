<?php

namespace Database\Seeders;

use App\Domain\Partner\Actions\SubmitPartnerApplication;
use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\PartnerConsent;
use App\Models\PartnerReviewHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HostKycSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'));
        $reviewer = User::where('email', 'admin@auracourt.vn')->firstOrFail();
        $users = User::whereIn('email', [
            'owner@demo.auracourt.vn', 'owner.hoanggia@demo.auracourt.vn', 'partner.draft@demo.auracourt.vn',
            'partner.pending@demo.auracourt.vn', 'partner.revision@demo.auracourt.vn', 'partner.flagged@demo.auracourt.vn',
        ])->get()->keyBy('email');
        $orgs = Organization::whereIn('slug', ['aura-badminton-club', 'hoang-gia-sports'])->get()->keyBy('slug');

        $applications = [
            // Approved applications behind the two partner organizations.
            [
                'kyc_code' => 'KYC-2026-0815',
                'org' => 'aura-badminton-club',
                'email' => 'owner@demo.auracourt.vn',
                'business_type' => 'enterprise',
                'business_name' => 'Aura Sports Marketplace Club',
                'legal_name' => 'CÔNG TY TNHH AURA SPORTS',
                'tax_code' => '0109876543',
                'representative_name' => 'TRAN QUOC BAO',
                'id_card_number' => '001085012345',
                'bank_name' => 'MBBank - Ngân hàng TMCP Quân Đội',
                'bank_code' => 'MBBANK',
                'bank_account_number' => '0451 000 123 456',
                'bank_account_holder' => 'CONG TY TNHH AURA SPORTS',
                'court_scale' => 27,
                'address_line' => 'Số 18 Phố Duy Tân',
                'ward' => 'Dịch Vọng Hậu',
                'district' => 'Cầu Giấy',
                'city' => 'Hà Nội',
                'sports' => ['badminton', 'pickleball', 'table_tennis', 'football_5v5', 'football_7v7', 'tennis', 'basketball', 'volleyball'],
                'facility_images' => ['/courts/badminton-0.jpg', '/courts/football-wide.jpg', '/courts/tennis-1.jpg'],
                'status' => 'approved',
                'submitted_at' => $now->copy()->subMonths(2),
                'reviewed_at' => $now->copy()->subMonths(2)->addDays(2),
            ],
            [
                'kyc_code' => 'KYC-2026-0847',
                'org' => 'hoang-gia-sports',
                'email' => 'owner.hoanggia@demo.auracourt.vn',
                'business_type' => 'enterprise',
                'business_name' => 'Hoàng Gia Sports',
                'legal_name' => 'CÔNG TY TNHH THỂ THAO HOÀNG GIA',
                'tax_code' => '0318765432',
                'representative_name' => 'LAM GIA HUY',
                'id_card_number' => '079088004567',
                'bank_name' => 'Techcombank - Ngân hàng TMCP Kỹ Thương',
                'bank_code' => 'TCB',
                'bank_account_number' => '1903 6688 9900',
                'bank_account_holder' => 'CONG TY TNHH THE THAO HOANG GIA',
                'court_scale' => 4,
                'address_line' => 'Số 15 Đường Nguyễn Lương Bằng',
                'ward' => 'Tân Phú',
                'district' => 'Quận 7',
                'city' => 'TP. Hồ Chí Minh',
                'sports' => ['pickleball', 'tennis', 'football_5v5'],
                'facility_images' => ['/courts/pickleball-1.jpg', '/courts/tennis-2.jpg'],
                'status' => 'approved',
                'submitted_at' => $now->copy()->subMonths(1)->subDays(10),
                'reviewed_at' => $now->copy()->subMonths(1)->subDays(8),
            ],
            // Applicants at each review stage.
            [
                'kyc_code' => 'KYC-2026-0901',
                'email' => 'partner.draft@demo.auracourt.vn',
                'business_type' => 'individual',
                'business_name' => 'Sân Pickleball Thảo Điền',
                'legal_name' => 'Bùi Thanh Tâm',
                'tax_code' => '',
                'representative_name' => 'Bùi Thanh Tâm',
                'id_card_number' => '',
                'bank_name' => '',
                'bank_code' => '',
                'bank_account_number' => '',
                'bank_account_holder' => '',
                'city' => 'TP. Hồ Chí Minh',
                'sports' => ['pickleball'],
                'status' => 'draft',
            ],
            [
                'kyc_code' => 'KYC-2026-0892',
                'email' => 'partner.pending@demo.auracourt.vn',
                'business_type' => 'enterprise',
                'business_name' => 'Smash City Arena',
                'legal_name' => 'CÔNG TY TNHH THỂ THAO SMASH CITY',
                'tax_code' => '0316589921',
                'tax_status' => 'Đang hoạt động',
                'representative_name' => 'NGUYEN VAN AN',
                'id_card_number' => '079092008892',
                'bank_name' => 'MBBank - Ngân hàng TMCP Quân Đội',
                'bank_code' => 'MBBANK',
                'bank_account_number' => '0909 888 999',
                'bank_account_holder' => 'CONG TY TNHH THE THAO SMASH CITY',
                'napas_match_status' => 'matched',
                'court_scale' => 12,
                'lighting_spec' => 'Hệ thống đèn LED chống chói 1000W',
                'lease_contract_status' => 'Hợp đồng 5 năm (đến 31/12/2030)',
                'address_line' => 'Số 210 Đường Cộng Hòa',
                'ward' => 'Phường 13',
                'district' => 'Tân Bình',
                'city' => 'TP. Hồ Chí Minh',
                'sports' => ['badminton', 'pickleball'],
                'facility_images' => ['/courts/badminton-night.jpg', '/courts/pickleball-outdoor.jpg'],
                'ai_ocr_score' => 99.0,
                'face_match_score' => 99.4,
                'status' => 'pending',
                'checklist_state' => [
                    'rep_identity_matched' => true,
                    'business_license_valid' => true,
                    'bank_account_matched' => true,
                    'brand_name_discrepancy_accepted' => false,
                    'facility_inspected' => true,
                ],
                'submitted_at' => $now->copy()->subHours(2),
            ],
            [
                'kyc_code' => 'KYC-2026-0891',
                'email' => 'partner.revision@demo.auracourt.vn',
                'business_type' => 'business_household',
                'business_name' => 'Tân Bình Sport',
                'legal_name' => 'HỘ KINH DOANH TÂN BÌNH SPORT',
                'tax_code' => '8392104921',
                'tax_status' => 'Đang hoạt động',
                'representative_name' => 'TRAN THI BICH',
                'id_card_number' => '079194002341',
                'bank_name' => 'Techcombank - Ngân hàng TMCP Kỹ Thương',
                'bank_code' => 'TCB',
                'bank_account_number' => '1903 666 888',
                'bank_account_holder' => 'TRAN THI BICH',
                'napas_match_status' => 'matched',
                'court_scale' => 6,
                'lease_contract_status' => 'Chính chủ sở hữu mặt bằng',
                'address_line' => 'Số 45 Đường Trường Chinh',
                'ward' => 'Phường 12',
                'district' => 'Tân Bình',
                'city' => 'TP. Hồ Chí Minh',
                'sports' => ['football_5v5', 'badminton'],
                'facility_images' => ['/courts/football-3.jpg'],
                'ai_ocr_score' => 98.2,
                'face_match_score' => 99.0,
                'status' => 'revision_required',
                'rejection_reason' => 'Ảnh mặt sau CCCD bị mờ và thiếu ảnh khu vực sân bóng đá.',
                'reviewer_notes' => 'Ảnh mặt sau CCCD bị mờ và thiếu ảnh khu vực sân bóng đá. Vui lòng bổ sung để tiếp tục xét duyệt.',
                'submitted_at' => $now->copy()->subDays(3),
                'reviewed_at' => $now->copy()->subDays(2),
            ],
            [
                'kyc_code' => 'KYC-2026-0889',
                'email' => 'partner.flagged@demo.auracourt.vn',
                'business_type' => 'enterprise',
                'business_name' => 'Elite Sports Club Q7',
                'legal_name' => 'CÔNG TY CP THỂ THAO ELITE VIỆT NAM',
                'tax_code' => '0317892110',
                'tax_status' => 'Đang hoạt động',
                'representative_name' => 'LE HOANG PHUC',
                'id_card_number' => '079088001122',
                'bank_name' => 'Vietcombank - Chi nhánh Nam Sài Gòn',
                'bank_code' => 'VCB',
                'bank_account_number' => '0071 000 888 999',
                'bank_account_holder' => 'CONG TY CP THE THAO ELITE VIET NAM',
                'napas_match_status' => 'matched',
                'court_scale' => 16,
                'lease_contract_status' => 'Hợp đồng 10 năm',
                'address_line' => 'Số 15 Đường Nguyễn Lương Bằng',
                'ward' => 'Tân Phú',
                'district' => 'Quận 7',
                'city' => 'TP. Hồ Chí Minh',
                'sports' => ['tennis', 'pickleball'],
                'facility_images' => [],
                'ai_ocr_score' => 94.5,
                'face_match_score' => 96.8,
                'status' => 'flagged',
                'investigation_status' => 'flagged_duplicate',
                'rejection_reason' => 'Cảnh báo: địa chỉ khai báo trùng với cơ sở Hoàng Gia Sports Phú Mỹ Hưng đã hoạt động trên hệ thống.',
                'submitted_at' => $now->copy()->subDay(),
            ],
        ];

        // Reviewer decisions shown on the applicant's history timeline; a review bumps the application version.
        $reviews = [
            'KYC-2026-0815' => ['decision' => 'approve', 'reason' => 'Hồ sơ đạt yêu cầu phê duyệt đối tác.'],
            'KYC-2026-0847' => ['decision' => 'approve', 'reason' => 'Hồ sơ đạt yêu cầu phê duyệt đối tác.'],
            'KYC-2026-0891' => [
                'decision' => 'revision_required',
                'reason' => 'Ảnh mặt sau CCCD bị mờ và thiếu ảnh khu vực sân bóng đá.',
                'feedback_fields' => [
                    'id_card_back' => 'Ảnh mặt sau CCCD bị mờ, vui lòng chụp lại rõ nét.',
                    'facility_images' => 'Bổ sung ảnh khu vực sân bóng đá 5 người.',
                ],
                'reapplication_allowed' => true,
            ],
        ];

        foreach ($applications as $application) {
            $user = $users[$application['email']];
            $org = isset($application['org']) ? $orgs[$application['org']] : null;
            $reviewed = isset($application['reviewed_at']);

            $kyc = HostKyc::updateOrCreate(
                ['kyc_code' => $application['kyc_code']],
                array_merge(array_diff_key($application, array_flip(['org', 'email'])), [
                    'organization_id' => $org?->id,
                    'user_id' => $user->id,
                    'current_user_id' => $user->id,
                    'contact_name' => $user->name,
                    'contact_phone' => $user->phone ?? '',
                    'contact_email' => $user->email,
                    'reviewed_by' => $reviewed ? $reviewer->id : null,
                    'version' => isset($reviews[$application['kyc_code']]) ? 2 : 1,
                ])
            );

            if (isset($application['submitted_at'])) {
                foreach (SubmitPartnerApplication::REQUIRED_CONSENT_TYPES as $type) {
                    PartnerConsent::updateOrCreate(
                        ['host_kyc_id' => $kyc->id, 'consent_type' => $type],
                        ['user_id' => $user->id, 'terms_version' => 'v1.0', 'agreed_at' => $application['submitted_at']]
                    );
                }
            }

            if ($review = $reviews[$application['kyc_code']] ?? null) {
                PartnerReviewHistory::updateOrCreate(
                    ['host_kyc_id' => $kyc->id, 'application_version' => 1],
                    $review + [
                        'reviewer_id' => $reviewer->id,
                        'reviewer_notes' => $application['reviewer_notes'] ?? null,
                        'created_at' => $application['reviewed_at'],
                        'updated_at' => $application['reviewed_at'],
                    ]
                );
            }

            $org?->forceFill(['source_kyc_id' => $kyc->id])->save();
        }
    }
}
