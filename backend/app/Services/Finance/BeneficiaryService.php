<?php

namespace App\Services\Finance;

use App\Contracts\Payment\BeneficiaryValidationRequest;
use App\Models\Beneficiary;
use App\Models\Organization;
use App\Services\Payment\PaymentProviderManager;

class BeneficiaryService
{
    public function __construct(private readonly PaymentProviderManager $providers) {}

    public function verifyAndCreate(Organization $organization, array $data): Beneficiary
    {
        $providerName = (string) ($data['provider'] ?? config('services.payments.default_provider', 'payos'));
        $response = $this->providers->provider($providerName)->verifyBeneficiary(new BeneficiaryValidationRequest(
            bankCode: $data['bank_code'],
            accountNumber: $data['account_number'],
            accountHolder: $data['account_holder'] ?? null,
            taxId: $data['tax_id'] ?? $organization->tax_code,
            metadata: ['organization_id' => $organization->id],
        ));
        abort_unless($response->verified, 422, 'Beneficiary bank account could not be verified.');

        return Beneficiary::updateOrCreate([
            'organization_id' => $organization->id,
            'provider' => $providerName,
            'bank_code' => $data['bank_code'],
            'account_number' => $data['account_number'],
        ], [
            'provider_beneficiary_id' => $response->providerBeneficiaryId,
            'account_holder' => $response->accountHolder ?: ($data['account_holder'] ?? ''),
            'tax_id' => $data['tax_id'] ?? $organization->tax_code,
            'status' => 'verified',
            'verified_at' => now(),
            'verification_payload' => $response->toArray(),
        ]);
    }
}
