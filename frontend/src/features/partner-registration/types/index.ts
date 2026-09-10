export type PartnerApplicationStatus =
  | 'draft'
  | 'pending_review'
  | 'supplementation_requested'
  | 'approved'
  | 'rejected'

export type BusinessType = 'individual' | 'household' | 'enterprise'

export type PartnerDocumentType =
  | 'identity_front'
  | 'identity_back'
  | 'business_license'
  | 'tax_certificate'
  | 'premises_proof'
  | 'supplementary'

export interface PartnerDocument {
  id: number
  application_id: number
  document_type: PartnerDocumentType | string
  file_path: string
  original_name: string
  mime_type: string
  file_size_bytes: number
  version: number
  download_url?: string
  created_at: string
}

export interface PartnerConsent {
  id: number
  application_id: number
  consent_type: string
  terms_version: string
  accepted_at: string
  ip_address?: string | null
}

export interface PartnerReviewHistory {
  id: number
  application_id: number
  decision: 'approve' | 'supplement' | 'reject' | string
  reason?: string | null
  feedback_fields?: Record<string, string> | null
  reviewed_by: number
  reviewed_at: string
}

export interface PartnerApplicationMetadata {
  consent_terms?: boolean
  consent_privacy?: boolean
  terms_version?: string
  terms_accepted_at?: string
  [key: string]: unknown
}

export interface PartnerApplication {
  id: number
  user_id: number
  organization_id?: number | null
  status: PartnerApplicationStatus
  version: number
  business_type: BusinessType | null
  business_name: string | null
  tax_code: string | null
  contact_name: string | null
  contact_phone: string | null
  contact_email: string | null
  metadata: PartnerApplicationMetadata | null
  draft_payload: Record<string, unknown> | null
  documents: PartnerDocument[]
  consents: PartnerConsent[]
  review_history: PartnerReviewHistory[]
  created_at: string
  updated_at: string
}

export interface StartApplicationPayload {
  consent_terms: boolean
  consent_privacy: boolean
  terms_version?: string
}

export interface SaveDraftPayload {
  business_type?: BusinessType
  business_name?: string
  tax_code?: string
  contact_name?: string
  contact_phone?: string
  contact_email?: string
  draft_payload?: Record<string, unknown>
}

export interface SubmitApplicationPayload {
  contact_name: string
  contact_phone: string
  contact_email: string
  consent_partner: boolean
  consent_data: boolean
  terms_version: string
}

export interface SupplementApplicationPayload {
  notes?: string
  document_ids?: number[]
}
