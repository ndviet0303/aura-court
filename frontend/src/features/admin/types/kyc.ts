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
  representative_phone?: string
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
  version?: number
  submitted_version?: number
  submission_snapshot?: Record<string, unknown> | null
  documents?: Array<{
    id: number
    document_type: string
    original_name: string
    mime_type: string
    file_size_bytes: number
    version: number
    download_url?: string
  }>
  review_history?: Array<{
    id: number
    decision: string
    reason?: string | null
    feedback_fields?: Record<string, string> | null
    reviewed_by: number
    reviewer_name?: string
    reviewed_at: string
  }>
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
  business_type?: KycBusinessType | 'all'
  search?: string
  date?: string
  perPage?: number
  page?: number
}

export interface ReviewKycPayload {
  decision: 'approve' | 'reject' | 'request_supplement'
  rejection_reason?: string
  reviewer_notes?: string
  checklist_state?: Record<string, boolean>
  expected_version?: number
  feedback_fields?: Record<string, string>
}

export interface KycAuditLog {
  id: string | number
  kyc_id: number
  action: string
  reviewer_name: string
  decision: KycStatus
  notes?: string
  rejection_reason?: string
  created_at: string
}

export interface DocumentInspectPayload {
  title: string
  type: 'cccd' | 'gpkd' | 'facility' | 'other'
  imageUrl: string
  docMeta?: Record<string, string | number>
}
