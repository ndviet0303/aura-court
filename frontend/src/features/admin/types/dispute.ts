export type DisputeStatus =
  'pending' | 'investigating' | 'awaiting_response' | 'resolved' | 'dismissed'

export type DisputeSeverity = 'critical' | 'high' | 'medium' | 'low'

export type DisputeType = 'double_booking' | 'match_stake' | 'equipment_damage' | 'other'

export interface DisputeParty {
  id?: number
  name: string
  tier?: string
  reputationScore?: number
  phone?: string
  avatar?: string
}

export interface DisputeTimelineEvent {
  time: string
  title: string
  description: string
  type: 'primary' | 'warning' | 'error' | 'success' | 'info'
}

export interface DisputeChatMessage {
  sender: string
  role: 'complainant' | 'respondent' | 'facility' | 'system'
  avatar?: string
  message: string
  time: string
}

export interface CourtMapNode {
  name: string
  status: 'available' | 'occupied' | 'conflict' | 'maintenance'
  label: string
}

export interface AiAnalysis {
  faultRateFacility: number
  faultRateUser: number
  rootCause: string
  recommendationSummary: string
}

export interface DisputeCase {
  id: number
  disputeCode: string
  organization_id?: number
  venueId?: number
  bookingId?: number
  reservationId?: number
  reservationCode?: string
  courtName: string
  disputeType: DisputeType
  severity: DisputeSeverity
  title: string
  summary: string
  status: DisputeStatus
  slaDeadline?: string
  amountDisputed: number
  currency: string
  complainant: DisputeParty
  respondent: DisputeParty
  facility: {
    name: string
    operatorName?: string
  }
  timelineEvents: DisputeTimelineEvent[]
  chatMessages: DisputeChatMessage[]
  courtMapContext: CourtMapNode[]
  aiAnalysis?: AiAnalysis
  resolutionDecision?: string
  refundMethod?: string
  refundAmount?: number
  compensationType?: string
  compensationValue?: string
  penaltyNotes?: string
  penaltyPoints?: number
  arbitratedBy?: number
  arbitratorName?: string
  arbitratedAt?: string
  arbitratorNotes?: string
  digitalSignatureHash?: string
  created_at?: string
  updatedAt?: string
}

export interface DisputeStats {
  pending_count: number
  investigatingCount: number
  awaitingReplyCount: number
  resolvedCount: number
  criticalSlaCount: number
}

export interface DisputeFilterParams {
  [key: string]: string | number | boolean | undefined | null
  status?: DisputeStatus | 'all'
  severity?: DisputeSeverity | 'all'
  disputeType?: string
  search?: string
  perPage?: number
  page?: number
}

export interface ArbitrateDisputePayload {
  decision: 'resolve' | 'dismiss' | 'investigate_further'
  compensationType?: 'voucher_20' | 'voucher_50' | 'reputation_points' | 'none'
  compensationValue?: string
  penaltyNotes?: string
  penaltyPoints?: number
  notes?: string
  digitalSignatureHash?: string
}
