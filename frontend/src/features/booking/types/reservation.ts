import type { Court } from '@/features/court/types/court'

export type ReservationStatus = 'draft' | 'held' | 'confirmed' | 'cancelled' | 'expired'

export type PriceQuoteSlotSnapshot = {
  starts_at: string
  ends_at?: string
  unit_price: number
  court_id?: number
  rule_name?: string
  base_price?: number
  rate_multiplier?: number
}

export type PriceQuote = {
  id: number
  quote_token: string
  total_amount: number
  subtotal?: number
  discount_amount?: number
  // Null when the quote carries no membership or coupon benefit.
  benefit_snapshot?: {
    membership_id?: number
    plan_id?: number
    plan_version?: number
    membership_discount_amount?: number
    benefit_code?: string
    coupon_id?: number
    coupon_code?: string
    coupon_discount_amount?: number
  } | null
  expires_at: string
  slot_snapshot: PriceQuoteSlotSnapshot[]
}

export type ReservationSlot = {
  id?: number
  reservation_id?: number
  court_id?: number
  starts_at: string
  ends_at?: string
  unit_price: number
  status?: string
}

export type ReservationEntitlement = {
  id: number
  code: string
  status: string
  qr_payload?: string
  expires_at?: string
}

export type LegacyMapping = {
  id: number
  reservation_id: number
  legacy_code: string
  legacy_status: string
}

export type ReservationReview = {
  id: number
  reservation_id: number
  rating: number
  body?: string | null
}

// Contact recorded when the booking was created; later profile edits do not change it.
export type ReservationBooker = { name: string; phone: string; email: string }

// Latest payment request of the booking and the latest transfer received for it (detail API only).
export type ReservationPaymentResult = {
  status: 'pending' | 'paid' | 'expired' | 'cancelled' | string
  amount: number
  currency: string
  expires_at: string | null
  paid_at: string | null
  receipt: {
    status: 'allocated' | 'late' | 'exception' | 'unmatched' | string
    amount: number
    currency: string
    transferred_at: string | null
  } | null
}

// Check-in and check-out recorded on site (detail API only).
export type ReservationAttendance = {
  status: string
  checked_in_at: string | null
  checked_out_at: string | null
}

// The newest affected-booking case from a venue or court change (detail API only).
export type ReservationAffectedCase = {
  status: 'pending' | 'contacting' | 'unreachable' | 'moved' | 'time_changed' | 'cancelled' | 'kept'
  source_type: string
  source_starts_at: string | null
  source_ends_at: string | null
  handled_at: string | null
}

export type Reservation = {
  id: number
  code: string
  status: ReservationStatus | string
  total_amount: number
  // The single payment deadline of a held booking; its payment attempt expires at the same instant.
  hold_expires_at?: string
  created_at?: string
  user_id?: number
  organization_id?: number
  court: Court
  quote?: PriceQuote | null
  slots: ReservationSlot[]
  entitlements?: ReservationEntitlement[]
  loyalty_discount_amount?: number
  loyalty_points_redeemed?: number
  loyalty_snapshot?: unknown
  net_payable?: number
  payment_model?: 'direct_owner_v1' | 'legacy_wallet_v1' | string | null
  legacy_mapping?: LegacyMapping[]
  review?: ReservationReview | null
  metadata?: {
    operational_status?: 'in_use' | 'completed' | 'no_show'
    // Why a held booking was released, e.g. customer_payment_cancel or payment_deadline.
    release_reason?: string
  } | null
  booker?: ReservationBooker | null
  payment?: ReservationPaymentResult | null
  attendance?: ReservationAttendance | null
  latest_affected_case?: ReservationAffectedCase | null
  // The voucher the booking holds or used (module 3 D17); null once its use was released.
  voucher?: ReservationVoucher | null
}

export type ReservationVoucher = { name: string | null; code: string | null; discount: number }

// A voucher priced for the selected interval (GET /venues/{venue}/vouchers, module 3 D10). id is the
// program sent as voucher_program_id; code is set for a private code only.
export type VoucherQuote = {
  id: number
  name: string
  type: 'public' | 'private'
  code: string | null
  ends_at: string
  rental: number
  discount: number
  total: number
}

// A 422 VOUCHER_INVALID: nothing was created; rental and total are the amounts without the voucher.
export type VoucherInvalid = { reason: string; message: string; rental: number; total: number }

// One interval on the 30-minute grid (design D14); a list of slot starts is refused with 422.
export type PriceQuotePayload = {
  court_id: number
  starts_at: string
  ends_at: string
}

export type HoldReservationPayload = {
  // The amount payable the customer was shown; a higher recalculated amount is refused with PRICE_CHANGED.
  expected_total: number
  quote_token?: string
  quote_id?: number
  court_id?: number
  starts_at?: string
  ends_at?: string
  // A public program or a private code, never both (module 3 D17).
  voucher_program_id?: number
  voucher_code?: string
}

// New amounts of a 409 PRICE_CHANGED: nothing was created and the customer must confirm them first.
export type PriceChange = { rental: number; discount: number; total: number }
