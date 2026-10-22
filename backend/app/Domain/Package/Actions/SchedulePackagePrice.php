<?php

namespace App\Domain\Package\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\PackagePriceRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Domain\Package\DTOs\SchedulePackagePriceData;
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

    public function execute(SchedulePackagePriceData $data): PackagePrice
    {
        $now = now()->utc();
        // D3: an effective time that is not in the future takes effect when the price is set.
        $effectiveAt = $data->effectiveAt === null || $data->effectiveAt->lte($now) ? $now : $data->effectiveAt;

        return DB::transaction(function () use ($data, $now, $effectiveAt) {
            $this->prices->lockTerm($data->term);
            $this->prices->replaceScheduled($data->term, $now);
            $price = $this->prices->create([
                'term_days' => $data->term,
                'amount' => $data->amount,
                'effective_at' => $effectiveAt,
                'created_by' => $data->createdBy,
            ]);

            DB::afterCommit(fn () => $this->notifyOwners($price));

            return $price;
        });
    }

    /** D3: every active owner hears of the term, the new price and its effective time; the key makes a rerun a no-op. */
    private function notifyOwners(PackagePrice $price): void
    {
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
                // The outbox row is already pending, so scheduled recovery resends it; keep notifying the other owners.
                Log::warning("Package price notice failed immediately for price {$price->id}, user {$userId}: {$e->getMessage()}", [
                    'package_price_id' => $price->id,
                    'user_id' => $userId,
                    'exception' => $e,
                ]);
            }
        }
    }
}
