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

export type Reservation = {
  id: number
  code: string
  status: ReservationStatus | string
  total_amount: number
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
  metadata?: { operational_status?: 'in_use' | 'completed' | 'no_show' } | null
}

export type PriceQuotePayload = {
  court_id: number
  slots: string[]
  coupon_code?: string
}

export type HoldReservationPayload = {
  quote_token?: string
  quote_id?: number
  court_id?: number
  slots?: string[]
  coupon_code?: string
  loyalty_points?: number
  loyalty_policy_version?: number
}
