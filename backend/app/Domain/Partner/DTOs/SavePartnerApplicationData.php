<?php

namespace App\Domain\Partner\DTOs;

use App\Http\Requests\Partner\SavePartnerApplicationRequest;

readonly class SavePartnerApplicationData
{
    public function __construct(
        public int $userId,
        public int $kycId,
        public ?string $businessType = null,
        public ?string $businessName = null,
        public ?string $legalName = null,
        public ?string $taxCode = null,
        public ?string $taxStatus = null,
        public ?string $representativeName = null,
        public ?string $idCardNumber = null,
        public ?string $bankName = null,
        public ?string $bankCode = null,
        public ?string $bankAccountNumber = null,
        public ?string $bankAccountHolder = null,
        public ?string $contactName = null,
        public ?string $contactPhone = null,
        public ?string $contactEmail = null,
        public ?string $addressLine = null,
        public ?string $ward = null,
        public ?string $district = null,
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?array $sports = null,
        public ?int $courtScale = null,
        public ?string $lightingSpec = null,
        public ?int $expectedVersion = null,
    ) {}

    public static function from(SavePartnerApplicationRequest $request, int $kycId): self
    {
        return new self(
            userId: (int) $request->user()->id,
            kycId: $kycId,
            businessType: $request->input('business_type'),
            businessName: $request->input('business_name'),
            legalName: $request->input('legal_name'),
            taxCode: $request->input('tax_code'),
            taxStatus: $request->input('tax_status'),
            representativeName: $request->input('representative_name'),
            idCardNumber: $request->input('id_card_number'),
            bankName: $request->input('bank_name'),
            bankCode: $request->input('bank_code'),
            bankAccountNumber: $request->input('bank_account_number'),
            bankAccountHolder: $request->input('bank_account_holder'),
            contactName: $request->input('contact_name'),
            contactPhone: $request->input('contact_phone'),
            contactEmail: $request->input('contact_email'),
            addressLine: $request->input('address_line'),
            ward: $request->input('ward'),
            district: $request->input('district'),
            city: $request->input('city'),
            postalCode: $request->input('postal_code'),
            sports: $request->input('sports'),
            courtScale: $request->filled('court_scale') ? (int) $request->input('court_scale') : null,
            lightingSpec: $request->input('lighting_spec'),
            expectedVersion: $request->filled('expected_version') ? (int) $request->input('expected_version') : null,
        );
    }

    public function toArray(): array
    {
        $payload = [
            'business_type' => $this->businessType,
            'business_name' => $this->businessName,
            'legal_name' => $this->legalName,
            'tax_code' => $this->taxCode,
            'tax_status' => $this->taxStatus,
            'representative_name' => $this->representativeName,
            'id_card_number' => $this->idCardNumber,
            'bank_name' => $this->bankName,
            'bank_code' => $this->bankCode,
            'bank_account_number' => $this->bankAccountNumber,
            'bank_account_holder' => $this->bankAccountHolder,
            'contact_name' => $this->contactName,
            'contact_phone' => $this->contactPhone,
            'contact_email' => $this->contactEmail,
            'address_line' => $this->addressLine,
            'ward' => $this->ward,
            'district' => $this->district,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'sports' => $this->sports,
            'court_scale' => $this->courtScale,
            'lighting_spec' => $this->lightingSpec,
        ];

        return array_filter($payload, fn ($v) => $v !== null);
    }
}
