import type { Court, Venue, SportZone, PriceSlot, CourtBlock } from '@/features/court/types/court'
import type { Booking, Refund, Coupon } from '@/features/booking/types/booking'
import type { User } from '@/core/types/common'

export type CourtForm = {
  name: string
  venue_id: number
  sport_zone_id?: number | null
  description?: string | null
  image_url?: string | null
  surface_type?: Court['surface_type']
  capacity?: number | null
  is_active: boolean
}

export type PriceForm = {
  weekday: number
  starts_at: string
  ends_at: string
  price: number
}

export type BlockForm = {
  starts_at: string
  ends_at: string
  reason: string
}

export type VenueForm = {
  name: string
  address: string
  city?: string
  district?: string
  ward?: string
  phone?: string
  email?: string
  description?: string
  images?: string[]
  google_maps_url?: string
  is_active?: boolean
}

export type VenueBookingPolicySettings = {
  booking: {
    hold_ttl_minutes: number
    max_days_ahead: number
    min_duration_minutes: number
    max_duration_minutes: number
    require_continuity: boolean
  }
  cancellation: {
    deadline_hours: number
    fee_type: 'none' | 'fixed' | 'percentage'
    fee_value: number
    refund_due_hours: number
  }
  change: {
    deadline_hours: number
    fee_type: 'none' | 'fixed' | 'percentage'
    fee_value: number
  }
  arrival: {
    early_minutes: number
    late_grace_minutes: number
    no_show_after_minutes: number
  }
  recurring: {
    payment_policy: 'full_series' | 'per_period'
    conflict_policy: 'all_or_nothing' | 'partial'
    overdue_release_hours: number
  }
  transfer: {
    cutoff_hours: number
    max_consideration_percent: number
    free_transfer_allowed: boolean
    seller_reimbursement_required: boolean
  }
  ancillary_credit_policy: 'retain' | 'refund' | 'prorate'
}

export type VenueBookingPolicyVersion = {
  id: number
  venue_id: number
  version: number
  settings: VenueBookingPolicySettings
  effective_at: string
}

export type OrganizationMember = {
  id: number
  user_id: number
  organization_id: number
  role: string
  user: User
}
