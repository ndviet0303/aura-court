import type { Court } from '@/features/court/types/court'

export type V2ReservationStatus = 'draft' | 'held' | 'confirmed' | 'cancelled' | 'expired'

export type V2PriceQuoteSlotSnapshot = {
  starts_at: string
  ends_at?: string
  unit_price: number
  court_id?: number
  rule_name?: string
  base_price?: number
  rate_multiplier?: number
}

export type V2PriceQuote = {
  id: number
  quote_token: string
  total_amount: number
  subtotal?: number
  discount_amount?: number
  expires_at: string
  slot_snapshot: V2PriceQuoteSlotSnapshot[]
}

export type V2ReservationSlot = {
  id?: number
  reservation_id?: number
  court_id?: number
  starts_at: string
  ends_at?: string
  unit_price: number
  status?: string
}

export type V2ReservationEntitlement = {
  id: number
  code: string
  status: string
  qr_payload?: string
  expires_at?: string
}

export type V2Reservation = {
  id: number
  code: string
  status: V2ReservationStatus | string
  total_amount: number
  hold_expires_at?: string
  created_at?: string
  user_id?: number
  organization_id?: number
  court: Court
  quote?: V2PriceQuote | null
  slots: V2ReservationSlot[]
  entitlements?: V2ReservationEntitlement[]
  loyalty_discount_amount?: number
  loyalty_points_redeemed?: number
  loyalty_snapshot?: any
  net_payable?: number
}

export type V2PriceQuotePayload = {
  court_id: number
  slots: string[]
}

export type V2HoldReservationPayload = {
  quote_token?: string
  quote_id?: number
  court_id?: number
  slots?: string[]
  loyalty_points?: number
  loyalty_policy_version?: number
  idempotency_key?: string
}
