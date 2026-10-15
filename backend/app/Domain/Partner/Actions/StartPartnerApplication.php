<?php

namespace App\Domain\Partner\Actions;

use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Domain\Partner\DTOs\StartPartnerApplicationData;
use App\Models\HostKyc;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartPartnerApplication
{
    public function __construct(
        private readonly OrganizationLockRepositoryInterface $locks,
        private readonly HostKycRepositoryInterface $kycRepo,
    ) {}

    public function execute(StartPartnerApplicationData $data): HostKyc
    {
        return DB::transaction(function () use ($data) {
            $user = $this->locks->lockUserForUpdate($data->userId);
            if (! $user) {
                throw ValidationException::withMessages(['user' => 'Người dùng không tồn tại.']);
            }

            // BR-04: partner registration needs a verified email.
            if (! $user->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'user' => 'Vui lòng xác thực email trước khi đăng ký đối tác.',
                ]);
            }

            // Check account restrictions
            $userStatus = (string) ($user->status ?? 'active');
            if (in_array($userStatus, ['suspended', 'banned', 'locked'], true)) {
                abort(403, 'Tài khoản của bạn đang bị tạm khóa hoặc giới hạn. Vui lòng liên hệ bộ phận hỗ trợ.');
            }

            // Check if user is already an active organization owner
            $isOwner = $user->organizations()
                ->wherePivot('role', 'organization_owner')
                ->where('organizations.status', 'active')
                ->exists();

            if ($isOwner) {
                throw ValidationException::withMessages([
                    'user' => 'Tài khoản đã là chủ sở hữu câu lạc bộ. Vui lòng chuyển sang bảng điều khiển quản lý.',
                ]);
            }

            // Resume existing in-flight/draft application if one is active
            $existing = $this->kycRepo->findCurrentByUserIdWithLock($user->id);
            if ($existing) {
                return $existing;
            }

            // Check policy if user had a previous rejected application
            $parentId = $data->parentKycId;
            if (! $parentId) {
                $lastApp = $this->kycRepo->findLatestByUserId($user->id);
                if ($lastApp && $lastApp->isRejected()) {
                    $parentId = $lastApp->id;
                }
            }

            // BR-14: a rejected application stays on record; a new one only links back to the user's own.
            if ($parentId) {
                $parentKyc = $this->kycRepo->findById($parentId);
                if (! $parentKyc || $parentKyc->user_id !== $user->id || ! $parentKyc->isRejected()) {
                    $parentId = null;
                }
            }

            // Generate unique KYC application code
            $code = 'KYC-'.date('Ymd').'-'.strtoupper(Str::random(6));

            return $this->kycRepo->create([
                'kyc_code' => $code,
                'user_id' => $user->id,
                'current_user_id' => $user->id,
                'parent_kyc_id' => $parentId,
                'business_type' => 'individual',
                'business_name' => '',
                'legal_name' => $user->name,
                'tax_code' => '',
                'representative_name' => $user->name,
                'id_card_number' => '',
                'bank_name' => '',
                'bank_code' => '',
                'bank_account_number' => '',
                'bank_account_holder' => '',
                'contact_name' => $user->name,
                'contact_phone' => $user->phone ?? '',
                'contact_email' => $user->email,
                'status' => 'draft',
                'version' => 1,
            ]);
        });
    }
}
