<?php

namespace App\Domain\Package\Actions;

use App\Contracts\Repositories\VenuePackageRepositoryInterface;
use App\Domain\Package\DTOs\TerminateVenuePackageData;
use App\Domain\Package\Enums\PackageStatus;
use App\Exceptions\PackageRuleException;
use App\Models\VenuePackage;
use Illuminate\Support\Facades\DB;

/** D8: termination takes effect at once and leaves ends_at, requests, receipts, the ledger and bookings alone. */
class TerminateVenuePackage
{
    public function __construct(private readonly VenuePackageRepositoryInterface $packages) {}

    public function execute(TerminateVenuePackageData $data): VenuePackage
    {
        if (! $data->consequencesConfirmed) {
            throw new PackageRuleException(422, 'TERMINATION_NOT_CONFIRMED', 'Vui lòng xác nhận mất thời gian còn lại và không được hoàn tiền trước khi chấm dứt gói.');
        }

        return DB::transaction(function () use ($data): VenuePackage {
            $now = now()->utc();
            $venue = $this->packages->lockVenueForPackage($data->venueId);
            abort_if($venue === null || (int) $venue->organization_id !== $data->organizationId, 404, 'Cơ sở thể thao không tồn tại.');

            $package = $this->packages->findCurrentForVenue($venue->id);
            if ($package === null || $package->statusAt($now) !== PackageStatus::Active) {
                throw new PackageRuleException(409, 'PACKAGE_NOT_ACTIVE', 'Cơ sở không có gói đang hoạt động để chấm dứt.');
            }

            return $this->packages->update($package, ['terminated_at' => $now, 'terminated_by' => $data->terminatedBy]);
        });
    }
}
