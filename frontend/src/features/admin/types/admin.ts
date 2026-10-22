import type { Court, SportType } from '@/features/court/types/court'
import type { User } from '@/core/types/common'

export type CourtForm = {
  code: string
  venue_id: number
  sport_type: SportType
  description?: string | null
  images?: string[]
  surface_type?: Court['surface_type']
  capacity?: number | null
}

/** SaveVenueRequest body; null clears an optional field, an omitted one keeps its saved value. */
export type VenueForm = {
  name: string
  address: string
  phone: string
  email: string
  city?: string | null
  district?: string | null
  ward?: string | null
  description?: string | null
  amenities?: string[]
  images?: string[]
  google_maps_url?: string | null
  latitude?: number | null
  longitude?: number | null
}

export type VenueBookingPolicySettings = {
  booking: {
    max_days_ahead: number
    min_duration_minutes: number
    max_duration_minutes: number
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
  }
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
