<?php

namespace App\Domain\Partner\Actions;

use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Domain\Partner\DTOs\SavePartnerApplicationData;
use App\Models\HostKyc;
use Illuminate\Support\Facades\DB;

class SavePartnerApplication
{
    public function __construct(
        private readonly HostKycRepositoryInterface $kycRepo,
    ) {}

    public function execute(SavePartnerApplicationData $data): HostKyc
    {
        return DB::transaction(function () use ($data) {
            $kyc = $this->kycRepo->findByIdWithLock($data->kycId);

            if (! $kyc || $kyc->user_id !== $data->userId) {
                abort(403, 'Bạn không có quyền chỉnh sửa hồ sơ này.');
            }

            if (! $kyc->isDraft() && ! $kyc->isRevisionRequired()) {
                abort(409, 'Không thể chỉnh sửa hồ sơ đang trong quá trình xét duyệt hoặc đã có kết quả.');
            }

            // Optimistic concurrency check
            if ($data->expectedVersion !== null && $kyc->version !== $data->expectedVersion) {
                abort(409, 'Dữ liệu hồ sơ đã được cập nhật từ một phiên làm việc khác. Vui lòng tải lại trang.');
            }

            $payload = $data->toArray();

            // Duplicate business identity check (flags for manual platform investigation)
            if (! empty($data->taxCode) && $data->taxCode !== $kyc->tax_code) {
                $conflict = $this->kycRepo->findByTaxCode($data->taxCode);
                if ($conflict && $conflict->id !== $kyc->id) {
                    $payload['investigation_status'] = 'flagged_duplicate';
                } else {
                    $payload['investigation_status'] = 'clean';
                }
            }

            $payload['version'] = $kyc->version + 1;

            return $this->kycRepo->update($kyc, $payload);
        });
    }
}
