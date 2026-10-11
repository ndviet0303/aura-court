export type PartnerApplicationStatus =
  'draft' | 'pending' | 'revision_required' | 'approved' | 'rejected'

export type BusinessType = 'individual' | 'household' | 'company'

export type PartnerDocumentType =
  | 'representative_id_front'
  | 'representative_id_back'
  | 'business_license'
  | 'business_registration_certificate'
  | 'venue_operation_rights'
  | 'bank_account_confirmation'
  | 'power_of_attorney'
  | 'brand_document'
  | 'invoice_sample'

export interface PartnerDocument {
  id: number
  document_type: PartnerDocumentType | string
  file_name: string
  mime_type: string
  file_size: number
  version: number
  status: string
  read_url: string
  created_at: string
  application_version?: number
}

export interface PartnerConsent {
  id: number
  consent_type: string
  terms_version: string
  agreed_at: string
}

export interface PartnerReviewHistory {
  id: number
  decision: 'approve' | 'revision_required' | 'reject' | string
  reason?: string | null
  feedback_fields?: Record<string, string> | null
  reapplication_allowed: boolean
  application_version: number
  created_at: string
}

export interface PartnerApplication {
  id: number
  kyc_code: string
  status: PartnerApplicationStatus
  version: number
  is_current: boolean
  business_type: BusinessType | null
  business_name: string | null
  legal_name: string | null
  tax_code: string | null
  tax_status: string | null
  representative_name: string | null
  id_card_number: string | null
  id_card_number_masked: string | null
  bank_name: string | null
  bank_code: string | null
  bank_account_number: string | null
  bank_account_number_masked: string | null
  bank_account_holder: string | null
  contact_name: string | null
  contact_phone: string | null
  contact_email: string | null
  address_line: string | null
  ward: string | null
  district: string | null
  city: string | null
  postal_code: string | null
  sports: string[]
  court_scale: number
  lighting_spec: string | null
  investigation_status: string | null
  rejection_reason: string | null
  submitted_at: string | null
  reviewed_at: string | null
  documents: PartnerDocument[]
  consents: PartnerConsent[]
  review_histories: PartnerReviewHistory[]
  created_at: string
  updated_at: string
}

export interface SaveDraftPayload {
  business_type?: BusinessType
  business_name?: string
  legal_name?: string
  tax_code?: string
  representative_name?: string
  id_card_number?: string
  bank_name?: string
  bank_code?: string
  bank_account_number?: string
  bank_account_holder?: string
  contact_name?: string
  contact_phone?: string
  contact_email?: string
  address_line?: string
  ward?: string
  district?: string
  city?: string
  postal_code?: string
  sports?: string[]
  court_scale?: number
  lighting_spec?: string
  expected_version: number
}

export type PartnerConsentType =
  | 'cooperation_terms'
  | 'platform_fee_policy'
  | 'settlement_refund_terms'
  | 'data_processing_agreement'
  | 'service_responsibility'

export interface SubmitApplicationPayload {
  expected_version: number
  consents: Array<{
    consent_type: PartnerConsentType
    terms_version: string
  }>
}
