<?php

namespace App\Http\Resources\Partner;

use App\Contracts\Storage\PartnerDocumentStorageInterface;
use App\Models\HostKyc;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HostKyc
 */
class PartnerApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PartnerDocumentStorageInterface $storage */
        $storage = app(PartnerDocumentStorageInterface::class);

        return [
            'id' => $this->id,
            'kyc_code' => $this->kyc_code,
            'status' => $this->status,
            'version' => (int) $this->version,
            'is_current' => $this->isCurrent(),
            'business_type' => $this->business_type,
            'business_name' => $this->business_name,
            'legal_name' => $this->legal_name,
            'tax_code' => $this->tax_code,
            'tax_status' => $this->tax_status,
            'representative_name' => $this->representative_name,
            'id_card_number' => $this->id_card_number,
            'id_card_number_masked' => $this->id_card_number ? $storage->maskDocumentNumber($this->id_card_number) : null,
            'bank_name' => $this->bank_name,
            'bank_code' => $this->bank_code,
            'bank_account_number' => $this->bank_account_number,
            'bank_account_number_masked' => $this->bank_account_number ? $storage->maskBankAccount($this->bank_account_number) : null,
            'bank_account_holder' => $this->bank_account_holder,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'address_line' => $this->address_line,
            'ward' => $this->ward,
            'district' => $this->district,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'sports' => $this->sports ?? [],
            'court_scale' => (int) ($this->court_scale ?? 1),
            'lighting_spec' => $this->lighting_spec,
            'investigation_status' => $this->investigation_status,
            'rejection_reason' => $this->rejection_reason,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'documents' => $this->documents->map(function ($doc) use ($storage) {
                return [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type,
                    'file_name' => $doc->file_name,
                    'mime_type' => $doc->mime_type,
                    'file_size' => (int) $doc->file_size,
                    'version' => (int) $doc->version,
                    'status' => $doc->status,
                    'read_url' => $storage->temporaryReadUrl($doc->file_path),
                    'created_at' => $doc->created_at?->toIso8601String(),
                ];
            }),
            'consents' => $this->consents->map(function ($consent) {
                return [
                    'id' => $consent->id,
                    'consent_type' => $consent->consent_type,
                    'terms_version' => $consent->terms_version,
                    'agreed_at' => $consent->agreed_at?->toIso8601String(),
                ];
            }),
            'review_histories' => $this->reviewHistories->map(function ($history) {
                return [
                    'id' => $history->id,
                    'decision' => $history->decision,
                    'reason' => $history->reason,
                    'feedback_fields' => $history->feedback_fields,
                    'reapplication_allowed' => (bool) $history->reapplication_allowed,
                    'application_version' => (int) $history->application_version,
                    'created_at' => $history->created_at?->toIso8601String(),
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
