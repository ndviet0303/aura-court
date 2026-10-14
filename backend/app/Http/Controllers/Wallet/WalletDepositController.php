<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PaymentIntent;
use App\Services\Infrastructure\TenantContext;
use App\Services\Payment\WalletAvailabilityService;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WalletDepositController extends Controller
{
    public function __construct(
        private readonly WalletPaymentService $walletPayments,
        private readonly TenantContext $tenantContext,
        private readonly WalletAvailabilityService $walletAvailability,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PaymentIntent::query()
            ->where('user_id', $user->id)
            ->whereJsonContains('metadata->type', 'wallet_deposit');

        $orgId = $request->query('organization_id') ?? $this->tenantContext->getOrganizationId();
        if ($orgId) {
            $query->where('organization_id', (int) $orgId);
        }

        $deposits = $query->latest('id')->take(50)->get()->map(function (PaymentIntent $intent) {
            return [
                'id' => $intent->id,
                'reference' => $intent->reference,
                'organization_id' => $intent->organization_id,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'status' => $intent->status,
                'provider' => $intent->provider,
                'transfer_info' => $intent->metadata['transfer_info'] ?? null,
                'succeeded_at' => $intent->succeeded_at?->toIso8601String(),
                'failed_at' => $intent->failed_at?->toIso8601String(),
                'created_at' => $intent->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'deposits' => $deposits,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:1000',
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'provider' => 'nullable|string|max:40',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        $orgId = $validated['organization_id']
            ?? $this->tenantContext->getOrganizationId()
            ?? $request->header('X-Organization-ID');

        if (! $orgId) {
            $org = Organization::where('status', 'active')->whereHas('venues')->first()
                ?: Organization::where('status', 'active')->first()
                ?: Organization::first();
            $orgId = $org?->id;
        }

        abort_unless($orgId, 422, 'Không tìm thấy thông tin tổ chức cho ví.');

        $provider = strtolower((string) ($validated['provider'] ?? 'sepay'));
        $idempotencyKey = $validated['idempotency_key'] ?? null;

        if ($idempotencyKey) {
            $existing = PaymentIntent::where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return response()->json([
                    'id' => $existing->id,
                    'reference' => $existing->reference,
                    'organization_id' => $existing->organization_id,
                    'amount' => $existing->amount,
                    'currency' => $existing->currency,
                    'status' => $existing->status,
                    'provider' => $existing->provider,
                    'transfer_info' => $existing->metadata['transfer_info'] ?? null,
                    'created_at' => $existing->created_at->toIso8601String(),
                ]);
            }
        }

        $this->walletAvailability->assertAvailable((int) $orgId);

        $wallet = $this->walletPayments->getOrCreateWallet($user->id, (int) $orgId);

        $reference = 'DEP-'.strtoupper(Str::random(8)).'-'.$user->id;
        $idempotencyKey ??= $reference;

        $amount = (int) $validated['amount'];

        $intent = PaymentIntent::create([
            'organization_id' => (int) $orgId,
            'user_id' => $user->id,
            'reference' => $reference,
            'provider' => $provider,
            'idempotency_key' => $idempotencyKey,
            'amount' => $amount,
            'currency' => 'VND',
            'status' => 'pending',
            'metadata' => [
                'type' => 'wallet_deposit',
                'wallet_account_id' => $wallet->id,
            ],
            'expires_at' => now()->addHours(24),
        ]);

        // SePay detects payment codes as prefix + digits, so the memo is ORD{intent id}.
        $bank = 'TPBank';
        $accountNumber = '83403032005';
        $accountName = 'AURA COURT';
        $code = 'ORD'.$intent->id;

        $qrUrl = "https://img.vietqr.io/image/{$bank}-{$accountNumber}-compact2.png?amount={$amount}&addInfo="
            .urlencode($code).'&accountName='.urlencode($accountName);

        $transferInfo = [
            'bank' => $bank,
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'code' => $code,
            'amount' => $amount,
            'qr_url' => $qrUrl,
        ];

        $intent->update(['metadata' => [
            ...$intent->metadata,
            'transfer_code' => $code,
            'memo' => $code,
            'transfer_info' => $transferInfo,
        ]]);

        return response()->json([
            'id' => $intent->id,
            'reference' => $intent->reference,
            'organization_id' => $intent->organization_id,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'status' => $intent->status,
            'provider' => $intent->provider,
            'transfer_info' => $transferInfo,
            'created_at' => $intent->created_at->toIso8601String(),
        ], 201);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $user = $request->user();

        $intent = PaymentIntent::query()
            ->where('user_id', $user->id)
            ->where(function ($q) use ($reference) {
                $q->where('reference', $reference);
                if (is_numeric($reference)) {
                    $q->orWhere('id', (int) $reference);
                }
            })
            ->firstOrFail();

        $wallet = $this->walletPayments->getOrCreateWallet($intent->user_id, $intent->organization_id);

        return response()->json([
            'id' => $intent->id,
            'reference' => $intent->reference,
            'organization_id' => $intent->organization_id,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'status' => $intent->status,
            'provider' => $intent->provider,
            'transfer_info' => $intent->metadata['transfer_info'] ?? null,
            'wallet_balance' => $wallet->balance,
            'succeeded_at' => $intent->succeeded_at?->toIso8601String(),
            'failed_at' => $intent->failed_at?->toIso8601String(),
            'created_at' => $intent->created_at->toIso8601String(),
        ]);
    }
}
