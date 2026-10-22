// Mirrors Court::SPORT_TYPES on the backend.
export const SPORT_TYPES = [
  'badminton',
  'football_5v5',
  'football_7v7',
  'tennis',
  'pickleball',
  'basketball',
  'volleyball',
  'table_tennis',
] as const

export type SportType = (typeof SPORT_TYPES)[number]

/** Status shared by venues and courts; a suspension is a separate record (active_suspension). */
export const OPERATING_STATUSES = ['inactive', 'active', 'paused', 'closed'] as const

export type OperatingStatus = (typeof OPERATING_STATUSES)[number]

export type VenueSuspension = {
  id: number
  reason: string
  suspended_at: string
}

export type OperationalStop = {
  id: number
  venue_id: number
  court_id: number | null
  kind: 'maintenance' | 'business' | 'closure'
  reason: string
  starts_at: string
  expected_end_at: string | null
  ended_at: string | null
}

export type Venue = {
  id: number
  organization_id?: number
  payment_model?: 'direct_owner_v1' | 'legacy_wallet_v1'
  status?: OperatingStatus
  active_suspension?: VenueSuspension | null
  name: string
  slug: string
  address: string | null
  city?: string | null
  district?: string | null
  ward?: string | null
  phone?: string | null
  email?: string | null
  description?: string | null
  images?: string[]
  google_maps_url: string | null
  latitude?: number | null
  longitude?: number | null
  is_active: boolean
  share_url?: string
  courts_count?: number
  courts?: Array<Pick<Court, 'id' | 'venue_id' | 'sport_type'>>
  image_url?: string
  amenities?: string[]
  timezone?: string
}

export type AvailabilitySlot = {
  startsAt: string
  endsAt: string
  price: number
  available: boolean
}

export type Court = {
  id: number
  /** The court's display name (BR-27); courts are always addressed by id. */
  code: string
  venue_id?: number
  venue: Venue
  sport_type: SportType
  slot_duration: number
  description?: string
  /** Uploaded or built-in images only, at most 10. */
  images?: string[]
  is_active?: boolean
  status?: OperatingStatus
  /** Lowest hourly price a booking can pay now; null when the court has no price. */
  from_price?: number | null
  /** Open stops (ended_at null), eager-loaded on the admin court list. */
  operational_stops?: OperationalStop[]
  reviews_count?: number
  reviews_avg_rating?: number | null
  amenities?: string[]
  surface_type?:
    'wood' | 'synthetic' | 'acrylic' | 'concrete' | 'grass' | 'clay' | 'hard' | 'other' | null
  capacity?: number | null
  timezone?: string
}

export type CourtReview = {
  id: number
  court_id: number
  user_id: number
  rating: number
  body?: string | null
  created_at?: string
  user?: {
    id: number
    name: string
  }
}
