<?php

namespace Database\Seeders;

use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HostKycSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);

        $org = Organization::where('slug', 'aura-badminton-club')->first() ?? Organization::first();
        $admin = User::where('role', 'admin')->first();

        if (! $org || ! $admin) {
            return;
        }

        $kycRecords = [
            [
                'kyc_code' => 'KYC-2026-0892',
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
                'facility_images' => ['/aura/court-1.jpg', '/aura/court-2.jpg'],
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
                'business_type' => 'business_household',
                'business_name' => 'Sân Cầu Lông Tân Bình Sport',
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
                'facility_images' => ['/aura/court-3.jpg'],
                'ai_ocr_score' => 98.2,
                'face_match_score' => 99.0,
                'status' => 'pending',
                'checklist_state' => [
                    'rep_identity_matched' => true,
                    'business_license_valid' => true,
                    'bank_account_matched' => true,
                    'brand_name_discrepancy_accepted' => true,
                    'facility_inspected' => true,
                ],
                'submitted_at' => $now->copy()->subHours(4),
            ],
            [
                'kyc_code' => 'KYC-2026-0889',
                'business_type' => 'enterprise',
                'business_name' => 'Elite Badminton Club Q7',
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
                'facility_images' => [],
                'ai_ocr_score' => 94.5,
                'face_match_score' => 96.8,
                'status' => 'flagged',
                'rejection_reason' => 'Cảnh báo: Trùng địa chỉ khai báo với một cơ sở đã đăng ký khác trên hệ thống.',
                'submitted_at' => $now->copy()->subDay(),
            ],
        ];

        foreach ($kycRecords as $kycData) {
            HostKyc::updateOrCreate(
                ['kyc_code' => $kycData['kyc_code']],
                array_merge($kycData, [
                    'organization_id' => $org->id,
                    'user_id' => $admin->id,
                ])
            );
        }
    }
}
