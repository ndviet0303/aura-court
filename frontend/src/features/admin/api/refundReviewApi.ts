import { apiV2 } from '@/core/api/client'

/**
 * Serialized owner refund-obligation as returned by
 * `GET /api/v2/refund-requests` and the review action endpoints
 * (see `OwnerRefundService::serialize`). This is the new
 * `owner_refund_obligations` shape — NOT the legacy `refunds` table.
 */
export type RefundObligationStatus =
  | 'requested'
  | 'approved'
  | 'owner_reported'
  | 'acknowledged'
  | 'rejected'
  | 'recipient_disputed'

export interface RefundObligationEvidence {
  id: number
  event_type: string
  actor_id: number | null
  event_fingerprint?: string | null
  created_at: string | null
}

export interface RefundObligationRecipient {
  bank_code?: string | null
  account_name?: string | null
  account_number?: string | null
  [key: string]: unknown
}

export interface RefundObligation {
  id: number
  reference_code: string | null
  reservation_code: string | null
  status: RefundObligationStatus | string
  gross_amount: number
  fee_amount: number
  refund_amount: number
  currency: string
  slot_ids: number[]
  reason: string | null
  recipient: RefundObligationRecipient | null
  due_at: string | null
  requested_at: string | null
  approved_at: string | null
  owner_reported_at: string | null
  recipient_acknowledged_at: string | null
  evidence: RefundObligationEvidence[]
}

type ObligationEnvelope = { data: RefundObligation }
type ObligationListEnvelope = { data: RefundObligation[] }

/**
 * Owner refund-obligation review actions. Sanctum cookie auth (no extra
 * headers); v2 mutations auto-attach an Idempotency-Key via the client.
 */
export const refundReviewApi = {
  listRefundInbox: (organizationId: number, status?: string) =>
    apiV2.get<ObligationListEnvelope>('/refund-requests', {
      params: { organization_id: organizationId, ...(status ? { status } : {}) },
    }),
  approveRefund: (id: number) =>
    apiV2.post<ObligationEnvelope>(`/refund-requests/${id}/approve`),
  rejectRefund: (id: number, reason: string) =>
    apiV2.post<ObligationEnvelope>(`/refund-requests/${id}/reject`, { reason }),
  reportRefundExecution: (id: number, body: { transfer_reference: string; evidence: string }) =>
    apiV2.post<ObligationEnvelope>(`/refund-requests/${id}/report-execution`, body),
  acknowledgeRefund: (id: number, body: { accepted: boolean; note?: string }) =>
    apiV2.post<ObligationEnvelope>(`/refund-requests/${id}/acknowledge`, body),
}
