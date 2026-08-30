<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HostKycResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kyc_code' => $this->kyc_code,
            'organization_id' => $this->organization_id,
            'business_type' => $this->business_type,
            'business_name' => $this->business_name,
            'legal_name' => $this->legal_name,
            'tax_code' => $this->tax_code,
            'tax_status' => $this->tax_status,
            'representative_name' => $this->representative_name,
            'id_card_number' => $this->id_card_number,
            'id_card_front_url' => $this->id_card_front_url,
            'id_card_back_url' => $this->id_card_back_url,
            'id_card_portrait_url' => $this->id_card_portrait_url,
            'business_license_url' => $this->business_license_url,
            'bank_name' => $this->bank_name,
            'bank_code' => $this->bank_code,
            'bank_account_number' => $this->bank_account_number,
            'bank_account_holder' => $this->bank_account_holder,
            'napas_match_status' => $this->napas_match_status,
            'court_scale' => $this->court_scale,
            'lighting_spec' => $this->lighting_spec,
            'lease_contract_status' => $this->lease_contract_status,
            'facility_images' => $this->facility_images ?: [],
            'ai_ocr_score' => $this->ai_ocr_score,
            'face_match_score' => $this->face_match_score,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'reviewer_notes' => $this->reviewer_notes,
            'checklist_state' => $this->checklist_state ?: [],
            'reviewed_by' => $this->reviewed_by,
            'reviewer_name' => $this->reviewer?->name,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'submitted_at' => $this->submitted_at?->toISOString() ?: $this->created_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
