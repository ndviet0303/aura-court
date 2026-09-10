<?php

namespace App\Domain\Partner\Rules;

use App\Models\HostKyc;

class PartnerCompletenessPolicy
{
    public const REQUIRED_FIELDS = [
        'business_type',
        'business_name',
        'legal_name',
        'representative_name',
        'id_card_number',
        'bank_name',
        'bank_code',
        'bank_account_number',
        'bank_account_holder',
        'contact_name',
        'contact_phone',
        'contact_email',
        'address_line',
        'city',
        'sports',
    ];

    /**
     * @return string[]
     */
    public static function getRequiredDocuments(string $businessType, bool $isRepresentative = true): array
    {
        $docs = [
            'representative_id_front',
            'representative_id_back',
            'bank_account_confirmation',
            'venue_operation_rights',
        ];

        if ($businessType === 'company') {
            $docs[] = 'business_license';
        } elseif ($businessType === 'household') {
            $docs[] = 'business_registration_certificate';
        }

        if (! $isRepresentative) {
            $docs[] = 'power_of_attorney';
        }

        return $docs;
    }

    /**
     * @return array{
     *     is_complete: bool,
     *     missing_fields: string[],
     *     missing_documents: string[],
     *     required_documents: string[]
     * }
     */
    public static function evaluate(HostKyc $kyc): array
    {
        $missingFields = [];
        foreach (self::REQUIRED_FIELDS as $field) {
            $val = $kyc->{$field};
            if ($val === null || $val === '' || ($field === 'sports' && empty($val))) {
                $missingFields[] = $field;
            }
        }

        if (in_array($kyc->business_type, ['company', 'household'], true) && empty($kyc->tax_code)) {
            $missingFields[] = 'tax_code';
        }

        // Compare contact name with representative name to check if power of attorney is required
        $applicantName = mb_strtolower(trim((string) ($kyc->contact_name ?: ($kyc->user?->name ?? ''))));
        $repName = mb_strtolower(trim((string) ($kyc->representative_name ?: '')));
        $isRep = empty($applicantName) || empty($repName) || $applicantName === $repName;

        $requiredDocs = self::getRequiredDocuments((string) $kyc->business_type, $isRep);
        $uploadedDocs = $kyc->documents()
            ->whereIn('status', ['uploaded', 'verified'])
            ->pluck('document_type')
            ->unique()
            ->all();

        $missingDocs = array_values(array_diff($requiredDocs, $uploadedDocs));

        return [
            'is_complete' => empty($missingFields) && empty($missingDocs),
            'missing_fields' => $missingFields,
            'missing_documents' => $missingDocs,
            'required_documents' => $requiredDocs,
        ];
    }
}
