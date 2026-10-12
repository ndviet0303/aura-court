import { apiV2 } from '@/core/api/client'
import type {
  DisputeCase,
  DisputeStats,
  DisputeFilterParams,
  ArbitrateDisputePayload,
} from '../types/dispute'

function transformDisputeCase(raw: any): DisputeCase {
  return {
    id: raw.id,
    disputeCode: raw.dispute_code || raw.disputeCode || `DSP-${raw.id}`,
    organization_id: raw.organization_id,
    venueId: raw.venue_id || raw.venueId,
    bookingId: raw.booking_id || raw.bookingId,
    reservationId: raw.reservation_id ?? undefined,
    reservationCode: raw.reservation_code ?? undefined,
    courtName: raw.court_name || raw.courtName || '',
    disputeType: raw.dispute_type || raw.disputeType || 'other',
    severity: raw.severity || 'medium',
    title: raw.title || '',
    summary: raw.summary || '',
    status: raw.status || 'pending',
    slaDeadline: raw.sla_deadline || raw.slaDeadline,
    amountDisputed: Number(raw.amount_disputed ?? raw.amountDisputed ?? 0),
    currency: raw.currency || 'VND',
    complainant: {
      id: raw.complainant?.id,
      name: raw.complainant?.name || raw.complainant_name || 'Khách hàng',
      tier: raw.complainant?.tier || raw.complainant_tier || 'Hạng Bạc',
      reputationScore: Number(
        raw.complainant?.reputation_score ?? raw.complainant?.reputationScore ?? 90,
      ),
      phone: raw.complainant?.phone || raw.complainant_phone || '',
      avatar: raw.complainant?.avatar || '',
    },
    respondent: {
      id: raw.respondent?.id,
      name: raw.respondent?.name || raw.respondent_name || 'Bị đơn',
      tier: raw.respondent?.tier || raw.respondent_tier || 'Hạng Đồng',
      reputationScore: Number(
        raw.respondent?.reputation_score ?? raw.respondent?.reputationScore ?? 85,
      ),
      phone: raw.respondent?.phone || raw.respondent_phone || '',
      avatar: raw.respondent?.avatar || '',
    },
    facility: {
      name: raw.facility?.name || raw.facility_name || 'Cơ sở thể thao',
      operatorName:
        raw.facility?.operator_name ||
        raw.facility?.operatorName ||
        raw.facility_operator_name ||
        '',
    },
    timelineEvents: (raw.timeline_events || raw.timelineEvents || []).map((ev: any) => ({
      time: ev.time,
      title: ev.title,
      description: ev.description,
      type: ev.type || 'primary',
    })),
    chatMessages: (raw.chat_messages || raw.chatMessages || []).map((msg: any) => ({
      sender: msg.sender,
      role: msg.role,
      avatar: msg.avatar,
      message: msg.message,
      time: msg.time,
    })),
    courtMapContext: (raw.court_map_context || raw.courtMapContext || []).map((cm: any) => ({
      name: cm.name,
      status: cm.status,
      label: cm.label,
    })),
    aiAnalysis:
      raw.ai_analysis || raw.aiAnalysis
        ? {
            faultRateFacility: Number(
              raw.ai_analysis?.fault_rate_facility ?? raw.aiAnalysis?.faultRateFacility ?? 0,
            ),
            faultRateUser: Number(
              raw.ai_analysis?.fault_rate_user ?? raw.aiAnalysis?.faultRateUser ?? 0,
            ),
            rootCause: raw.ai_analysis?.root_cause || raw.aiAnalysis?.rootCause || '',
            recommendationSummary:
              raw.ai_analysis?.recommendation_summary ||
              raw.aiAnalysis?.recommendationSummary ||
              '',
          }
        : undefined,
    resolutionDecision: raw.resolution_decision || raw.resolutionDecision,
    refundMethod: raw.refund_method || raw.refundMethod,
    refundAmount: Number(raw.refund_amount ?? raw.refundAmount ?? 0),
    compensationType: raw.compensation_type || raw.compensationType,
    compensationValue: raw.compensation_value || raw.compensationValue,
    penaltyNotes: raw.penalty_notes || raw.penaltyNotes,
    penaltyPoints: Number(raw.penalty_points ?? raw.penaltyPoints ?? 0),
    arbitratedBy: raw.arbitrated_by || raw.arbitratedBy,
    arbitratorName: raw.arbitrator_name || raw.arbitratorName,
    arbitratedAt: raw.arbitrated_at || raw.arbitratedAt,
    arbitratorNotes: raw.arbitrator_notes || raw.arbitratorNotes,
    digitalSignatureHash: raw.digital_signature_hash || raw.digitalSignatureHash,
    created_at: raw.created_at,
    updatedAt: raw.updated_at || raw.updatedAt,
  }
}

function transformDisputeStats(raw: any): DisputeStats {
  return {
    pending_count: Number(raw?.pending_count ?? raw?.pendingCount ?? 0),
    investigatingCount: Number(raw?.investigating_count ?? raw?.investigatingCount ?? 0),
    awaitingReplyCount: Number(raw?.awaiting_reply_count ?? raw?.awaitingReplyCount ?? 0),
    resolvedCount: Number(raw?.resolved_count ?? raw?.resolvedCount ?? 0),
    criticalSlaCount: Number(raw?.critical_sla_count ?? raw?.criticalSlaCount ?? 0),
    avgResolutionTimeMinutes: Number(
      raw?.avg_resolution_time_minutes ?? raw?.avgResolutionTimeMinutes ?? 18.5,
    ),
    consensusRate: Number(raw?.consensus_rate ?? raw?.consensusRate ?? 98.2),
  }
}

export const disputeApi = {
  getDisputes: async (params?: DisputeFilterParams): Promise<DisputeCase[]> => {
    const query = new URLSearchParams()
    if (params?.status && params.status !== 'all') query.set('status', params.status)
    if (params?.severity && params.severity !== 'all') query.set('severity', params.severity)
    if (params?.disputeType && params.disputeType !== 'all')
      query.set('dispute_type', params.disputeType)
    if (params?.search) query.set('search', params.search)
    if (params?.perPage) query.set('per_page', String(params.perPage))

    const qs = query.toString()
    const res = await apiV2.get<{ data: any[] }>(`/admin/disputes${qs ? `?${qs}` : ''}`)
    const list = res?.data || []
    return list.map(transformDisputeCase)
  },

  getStats: async (): Promise<DisputeStats> => {
    const res = await apiV2.get<{ stats: any }>('/admin/disputes/stats')
    return transformDisputeStats(res?.stats)
  },

  getDisputeDetail: async (id: number): Promise<DisputeCase | null> => {
    const res = await apiV2.get<{ data: any }>(`/admin/disputes/${id}`)
    return res?.data ? transformDisputeCase(res.data) : null
  },

  arbitrateDispute: async (
    id: number,
    payload: ArbitrateDisputePayload,
  ): Promise<{ message: string; data: DisputeCase }> => {
    const body = {
      decision: payload.decision,
      refund_method: payload.refundMethod,
      refund_amount: payload.refundAmount,
      compensation_type: payload.compensationType,
      compensation_value: payload.compensationValue,
      penalty_notes: payload.penaltyNotes,
      penalty_points: payload.penaltyPoints,
      notes: payload.notes,
      digital_signature_hash: payload.digitalSignatureHash,
    }
    const res = await apiV2.post<{ message: string; data: any }>(
      `/admin/disputes/${id}/arbitrate`,
      body,
    )
    return {
      message: res.message,
      data: transformDisputeCase(res.data),
    }
  },
}
