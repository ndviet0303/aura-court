import type { Court, AvailabilitySlot } from '@/features/court/types/court'
import type { User } from '@/core/types/common'

export type { AvailabilitySlot }

export type BookingStatus =
  | 'pending_payment'
  | 'confirmed'
  | 'cancelled'
  | 'expired'
  | 'refunded'
  | 'refund_pending'

export type Coupon = {
  id: number
  code: string
  discount_type: 'fixed' | 'percent'
  discount_value: number
  max_discount?: number | null
  min_spend?: number | null
}

export type BookingSlot = {
  id?: number
  starts_at: string
  ends_at: string
  unit_price: number
}

export type Review = {
  id: number
  booking_id: number
  rating: number
  body?: string | null
  user?: User
}

export type Booking = {
  id: number
  code: string
  status: BookingStatus | string
  total_amount: number
  discount_amount?: number
  membership_discount_amount?: number
  payment_expires_at?: string
  court: Court
  slots: BookingSlot[]
  coupon?: Coupon | null
  review?: Review | null
}

export type PaymentInfo = {
  code: string
  amount: number
  bank: string
  accountNumber: string
  qrUrl: string
  expiresAt: string
  type?: 'booking' | 'tournament' | string
  title?: string
  tournament?: any
  athleteDetails?: {
    name?: string
    phone?: string
    partnerName?: string
    notes?: string
  }
}

export type Refund = {
  id: number
  amount: number
  status: string
  note?: string
  booking: Booking & { user?: User }
}

export type CreateBookingPayload = {
  court_id: number | null
  slots: string[]
  coupon_code?: string
  quote_token?: string
}

export type CardDetails = {
  cardNumber?: string
  cardExpiry?: string
  cardCvc?: string
  cardName?: string
}
