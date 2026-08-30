export type KycStatus = 'pending' | 'needs_supplement' | 'flagged' | 'approved' | 'rejected'

export type KycBusinessType = 'enterprise' | 'business_household' | 'individual'

export type NapasMatchStatus = 'matched' | 'mismatched' | 'pending'

export interface HostKyc {
  id: number
  kyc_code: string
  organization_id?: number
  business_type: KycBusinessType
  business_name: string
  legal_name: string
  tax_code: string
  tax_status: string
  representative_name: string
  id_card_number: string
  id_card_front_url: string
  id_card_back_url?: string
  id_card_portrait_url?: string
  business_license_url: string
  bank_name: string
  bank_code: string
  bank_account_number: string
  bank_account_holder: string
  napas_match_status: NapasMatchStatus
  court_scale: number
  lighting_spec?: string
  lease_contract_status: string
  facility_images: string[]
  ai_ocr_score: number
  face_match_score: number
  status: KycStatus
  rejection_reason?: string
  reviewer_notes?: string
  checklist_state?: {
    rep_identity_matched?: boolean
    business_license_valid?: boolean
    bank_account_matched?: boolean
    brand_name_discrepancy_accepted?: boolean
    facility_inspected?: boolean
  }
  reviewed_by?: number
  reviewer_name?: string
  reviewed_at?: string
  submitted_at: string
  created_at?: string
}

export interface KycStats {
  pending_count: number
  ai_confidence_rate: number
  avg_processing_time_minutes: number
  flagged_count: number
  approved_count?: number
  needs_supplement_count?: number
}

export interface KycFilterParams {
  status?: KycStatus | 'all'
  search?: string
  perPage?: number
  page?: number
}

export interface ReviewKycPayload {
  decision: 'approve' | 'reject' | 'request_supplement'
  rejection_reason?: string
  reviewer_notes?: string
  checklist_state?: Record<string, boolean>
}
