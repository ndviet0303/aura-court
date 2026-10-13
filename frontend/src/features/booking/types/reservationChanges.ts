// Types for the policy-aware cancellation / partial-cancel (refund) and
// reschedule customer flows. Shapes mirror the backend contract exposed by
// OwnerRefundController / RescheduleController and OwnerRefundService::serialize.

// Minimal reservation shape the customer cancel/reschedule modals need,
// addressed by reservation code.
export type ChangeableBookingSlot = {
  id?: number
  starts_at: string
  ends_at?: string
  unit_price: number
}

export type ChangeableBooking = {
  code: string
  status?: string
  slots: ChangeableBookingSlot[]
}

export type RefundFeeType = 'none' | 'fixed' | 'percentage'

export type RefundCalculation = {
  policy_version: number | null
  gross_amount: number
  fee_type: RefundFeeType | string
  fee_value: number
  fee_amount: number
  refund_amount: number
  currency: string
  due_at: string
}

export type RefundQuote = {
  reservation_code: string
  slot_ids: number[]
  calculation: RefundCalculation
  points_to_restore: number
}

export type RefundRecipient = {
  bank_code: string
  account_name: string
  account_number: string
}

// Lifecycle: requested -> approved -> owner_reported -> acknowledged
// (or rejected / recipient_disputed). Only `acknowledged` is a verified,
// completed refund — an owner_reported transfer or a dispute is NOT.
export type RefundObligationStatus =
  'requested' | 'approved' | 'owner_reported' | 'acknowledged' | 'rejected' | 'recipient_disputed'

export type RefundEvidenceEvent = {
  id: number
  event_type: string
  actor_id: number | null
  event_fingerprint: string | null
  created_at: string | null
}

export type RefundObligation = {
  id: number
  reference_code: string
  reservation_code: string | null
  booking_id?: number | null
  payer_id: number
  status: RefundObligationStatus | string
  gross_amount: number
  fee_amount: number
  refund_amount: number
  currency: string
  slot_ids: number[]
  legacy_slot_ids?: number[] | null
  reason: string | null
  recipient: RefundRecipient | null
  execution_reference: string | null
  due_at: string | null
  requested_at: string | null
  approved_at: string | null
  owner_reported_at: string | null
  recipient_acknowledged_at: string | null
  evidence: RefundEvidenceEvent[]
}

export type CreateRefundRequestPayload = {
  slot_ids: number[]
  reason: string
  idempotency_key: string
  recipient?: RefundRecipient
}

export type ReschedulePreviewPayload = {
  court_id: number
  slots: string[]
}

export type ReschedulePreviewSlot = {
  starts_at: string
  ends_at: string
  unit_price: number
}

export type ReschedulePreview = {
  reservation_code: string
  new_court_id: number
  slots: ReschedulePreviewSlot[]
  policy_version: number | null
  deadline_at: string
  original_paid_amount: number
  new_total_amount: number
  change_fee_amount: number
  extra_payment_amount: number
  refund_amount: number
  currency: string
}

export type RescheduleReplacement = {
  code: string
  status: string
  court_id: number
  total_amount: number
  hold_expires_at: string | null
  slots: ReschedulePreviewSlot[]
}

export type ReschedulePaymentAttempt = {
  id: number
  provider: string
  reference: string
  transfer_content: string
  amount: number
  currency: string
  status: string
  expires_at: string | null
  paid_at: string | null
  receiver: { bank_code: string; account_name: string; account_number: string }
  qr_url: string
}

export type RescheduleResult = {
  reservation_code: string
  status: string
  replacement: RescheduleReplacement
  change: unknown
  payment_attempt: ReschedulePaymentAttempt | null
  refund: RefundObligation | null
}

export type CreateReschedulePayload = {
  court_id: number
  slots: string[]
  idempotency_key: string
  recipient?: RefundRecipient
}
