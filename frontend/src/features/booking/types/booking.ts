import type { Court, AvailabilitySlot } from '@/features/court/types/court'
import type { User } from '@/core/types/common'

export type { AvailabilitySlot }

export type BookingStatus =
  'pending_payment' | 'confirmed' | 'cancelled' | 'expired' | 'refunded' | 'refund_pending'

export type PromotionCampaign = {
  id: number
  organization_id: number
  venue_id?: number | null
  court_id?: number | null
  name: string
  description?: string | null
  status: 'draft' | 'active' | 'suspended' | 'retired' | string
  eligibility_group?: 'all_customers' | 'first_time_customers' | 'returning_customers' | string
  starts_at?: string | null
  ends_at?: string | null
  usage_limit?: number | null
  usage_count?: number
  min_spend?: number
  can_combine_with_membership?: boolean
  version: number
}

export type Coupon = {
  id: number
  code: string
  organization_id?: number | null
  venue_id?: number | null
  court_id?: number | null
  promotion_campaign_id?: number | null
  campaign?: PromotionCampaign | null
  status?: 'draft' | 'active' | 'suspended' | 'retired' | string | null
  eligibility_group?: 'all_customers' | 'first_time_customers' | 'returning_customers' | string
  version?: number
  discount_type: 'fixed' | 'percent'
  discount_value: number
  max_discount?: number | null
  min_spend?: number | null
  usage_count?: number | null
  usage_limit?: number | null
  is_active?: boolean
  starts_at?: string | null
  ends_at?: string | null
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
  payment_model?: 'direct_owner_v1' | 'legacy_wallet_v1' | string
  total_amount: number
  discount_amount?: number
  membership_discount_amount?: number
  loyalty_discount_amount?: number
  loyalty_points_redeemed?: number
  loyalty_snapshot?: any
  net_payable?: number
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
  type?: 'booking' | 'tournament' | 'direct_owner' | string
  title?: string
  tournament?: any
  athleteDetails?: {
    name?: string
    phone?: string
    partnerName?: string
    notes?: string
  }
  receipt?: any
  participant?: any
}

export type Refund = {
  id: number
  amount: number
  status: string
  note?: string
  booking: Booking & { user?: User }
}

export type CardDetails = {
  cardNumber?: string
  cardExpiry?: string
  cardCvc?: string
  cardName?: string
}
