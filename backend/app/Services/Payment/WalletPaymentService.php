<?php

namespace App\Services\Payment;

use App\Models\Organization;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\Finance\FinancialPostingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class WalletPaymentService
{
    public function __construct(
        private readonly FinancialPostingService $financials,
    ) {}

    public function getOrCreateWallet(int $userId, ?int $organizationId = null): WalletAccount
    {
        if (! $organizationId) {
            $organizationId = Cache::remember('default_org_id', 3600, function () {
                $organization = Organization::where('status', 'active')->whereHas('venues')->first()
                    ?: Organization::where('status', 'active')->first()
                    ?: Organization::first();
                return $organization?->id;
            });
        }

        abort_unless($organizationId, 500, 'Không tìm thấy tổ chức hoạt động.');

        return WalletAccount::firstOrCreate(
            ['organization_id' => $organizationId, 'user_id' => $userId, 'currency' => 'VND'],
            ['balance' => 0, 'status' => 'active']
        );
    }

    public function debit(WalletAccount $wallet, int $amount, string $sourceType, int $sourceId, ?string $reference = null): WalletTransaction
    {
        abort_unless($amount > 0, 422, 'Số tiền thanh toán phải lớn hơn 0.');

        $wallet = WalletAccount::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

        if ($wallet->balance < $amount) {
            $shortfall = $amount - $wallet->balance;
            throw ValidationException::withMessages([
                'wallet' => ['Số dư ví không đủ. Vui lòng nạp thêm tiền vào ví để hoàn tất đặt sân.'],
                'balance' => [$wallet->balance],
                'shortfall' => [$shortfall],
            ]);
        }

        $wallet->decrement('balance', $amount);

        return WalletTransaction::create([
            'organization_id' => $wallet->organization_id,
            'wallet_account_id' => $wallet->id,
            'type' => 'debit',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reference' => $reference,
        ]);
    }

    public function credit(WalletAccount $wallet, int $amount, string $sourceType, int $sourceId, ?string $reference = null): WalletTransaction
    {
        abort_unless($amount > 0, 422, 'Số tiền hoàn phải lớn hơn 0.');

        $wallet = WalletAccount::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
        $wallet->increment('balance', $amount);

        return WalletTransaction::create([
            'organization_id' => $wallet->organization_id,
            'wallet_account_id' => $wallet->id,
            'type' => 'credit',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reference' => $reference,
        ]);
    }
}
