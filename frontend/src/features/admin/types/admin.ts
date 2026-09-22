import type { Court, Venue, SportZone, PriceSlot, CourtBlock } from '@/features/court/types/court'
import type { Booking, Refund, Coupon } from '@/features/booking/types/booking'
import type { User } from '@/core/types/common'

export type CourtForm = {
  name: string
  venue_id: number
  sport_zone_id?: number | null
  description?: string | null
  image_url?: string | null
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
  google_maps_url?: string
  is_active?: boolean
}

export type OrganizationMember = {
  id: number
  user_id: number
  organization_id: number
  role: string
  user: User
}
