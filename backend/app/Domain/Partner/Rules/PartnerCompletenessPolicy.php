<?php

namespace App\Domain\Partner\Rules;

use App\Models\HostKyc;

class PartnerCompletenessPolicy
{
    /** BR-12: business name, business type, representative, address, contact email and phone. */
    public const REQUIRED_FIELDS = [
        'business_type',
        'business_name',
        'representative_name',
        'contact_phone',
        'contact_email',
        'address_line',
        'city',
    ];

    /**
     * @return string[]
     */
    /** BR-12: the verification documents of the business type. */
    public static function getRequiredDocuments(string $businessType): array
    {
        $docs = ['representative_id_front', 'representative_id_back'];

        if ($businessType === 'company') {
            $docs[] = 'business_license';
        } elseif ($businessType === 'household') {
            $docs[] = 'business_registration_certificate';
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
            if ($val === null || trim((string) $val) === '') {
                $missingFields[] = $field;
            }
        }

        $requiredDocs = self::getRequiredDocuments((string) $kyc->business_type);
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
