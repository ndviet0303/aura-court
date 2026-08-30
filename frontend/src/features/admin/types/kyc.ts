export type KycStatus = 'pending' | 'needs_supplement' | 'flagged' | 'approved' | 'rejected'

export type KycBusinessType = 'enterprise' | 'business_household' | 'individual'

export type NapasMatchStatus = 'matched' | 'mismatched' | 'pending'

export interface HostKyc {
  id: number
  kycCode: string
  organizationId?: number
  businessType: KycBusinessType
  businessName: string
  legalName: string
  taxCode: string
  taxStatus: string
  representativeName: string
  idCardNumber: string
  idCardFrontUrl: string
  idCardBackUrl?: string
  idCardPortraitUrl?: string
  businessLicenseUrl: string
  bankName: string
  bankCode: string
  bankAccountNumber: string
  bankAccountHolder: string
  napasMatchStatus: NapasMatchStatus
  courtScale: number
  lightingSpec?: string
  leaseContractStatus: string
  facilityImages: string[]
  aiOcrScore: number
  faceMatchScore: number
  status: KycStatus
  rejectionReason?: string
  reviewerNotes?: string
  checklistState?: {
    repIdentityMatched?: boolean
    businessLicenseValid?: boolean
    bankAccountMatched?: boolean
    brandNameDiscrepancyAccepted?: boolean
    facilityInspected?: boolean
  }
  reviewedBy?: number
  reviewerName?: string
  reviewedAt?: string
  submittedAt: string
  createdAt?: string
}

export interface KycStats {
  pendingCount: number
  aiConfidenceRate: number
  avgProcessingTimeMinutes: number
  flaggedCount: number
  approvedCount?: number
  needsSupplementCount?: number
}

export interface KycFilterParams {
  status?: KycStatus | 'all'
  search?: string
  perPage?: number
  page?: number
}

export interface ReviewKycPayload {
  decision: 'approve' | 'reject' | 'request_supplement'
  rejectionReason?: string
  reviewerNotes?: string
  checklistState?: Record<string, boolean>
}
