<?php

namespace App\Domain\Package\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\PackagePriceRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Domain\Package\DTOs\ScheduledPackagePrice;
use App\Domain\Package\DTOs\SchedulePackagePriceData;
use App\Exceptions\PackageRuleException;
use App\Models\PackagePrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SchedulePackagePrice
{
    public function __construct(
        private readonly PackagePriceRepositoryInterface $prices,
        private readonly UserRepositoryInterface $users,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(SchedulePackagePriceData $data): ScheduledPackagePrice
    {
        $now = now()->utc();
        // D3: an effective time that is not in the future takes effect when the price is set.
        $effectiveAt = $data->effectiveAt === null || $data->effectiveAt->lte($now) ? $now : $data->effectiveAt;

        $notificationFailed = false;
        $price = DB::transaction(function () use ($data, $now, $effectiveAt, &$notificationFailed) {
            $this->prices->lockTerm($data->term);
            // UC-5.2 E-1: a term priced meanwhile keeps its price; changing it is UC-5.3.
            if ($data->initial && ($this->prices->findApplicable($data->term, $now) ?? $this->prices->findNextScheduled($data->term, $now)) !== null) {
                throw new PackageRuleException(409, 'PACKAGE_PRICE_EXISTS', 'Kỳ hạn này đã có giá. Vui lòng tải lại và dùng chức năng cập nhật giá.');
            }
            $this->prices->replaceScheduled($data->term, $now);
            $price = $this->prices->create([
                'term_days' => $data->term,
                'amount' => $data->amount,
                'effective_at' => $effectiveAt,
                'created_by' => $data->createdBy,
            ]);

            DB::afterCommit(function () use ($price, &$notificationFailed) {
                $notificationFailed = ! $this->notifyOwners($price);
            });

            return $price;
        });

        return new ScheduledPackagePrice($price, $notificationFailed);
    }

    /** D3: every active owner hears of the term, the new price and its effective time; the key makes a rerun a no-op. False when a notice failed. */
    private function notifyOwners(PackagePrice $price): bool
    {
        $notified = true;
        $days = $price->term_days->value;
        $amount = number_format($price->amount, 0, ',', '.').'₫';
        $effectiveAt = $price->effective_at->copy()->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y');

        foreach ($this->users->activeOwnerIds() as $userId) {
            try {
                $this->notif->recordOutboxNotification(
                    key: "package.price.{$price->id}.{$userId}",
                    userId: $userId,
                    eventType: 'package.price.set',
                    payload: [
                        'title' => "Giá gói {$days} ngày thay đổi",
                        'body' => "Giá gói {$days} ngày là {$amount}, áp dụng từ {$effectiveAt}.",
                        'action_url' => '/partner/subscription',
                        'term_days' => $days,
                        'amount' => $price->amount,
                        'effective_at' => $price->effective_at->toISOString(),
                        'aggregate_type' => PackagePrice::class,
                        'aggregate_id' => $price->id,
                    ],
                    channels: ['database', 'mail'],
                );
            } catch (\Throwable $e) {
                // A pending outbox row is resent by recovery; UC-5.3 E-3 still tells the admin. Keep notifying the other owners.
                $notified = false;
                Log::warning("Package price notice failed immediately for price {$price->id}, user {$userId}: {$e->getMessage()}", [
                    'package_price_id' => $price->id,
                    'user_id' => $userId,
                    'exception' => $e,
                ]);
            }
        }

        return $notified;
    }
}
