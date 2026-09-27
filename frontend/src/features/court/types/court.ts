export type SportType =
  | 'badminton'
  | 'football_5v5'
  | 'football_7v7'
  | 'tennis'
  | 'pickleball'
  | 'basketball'
  | 'volleyball'
  | 'table_tennis'

export type SportZone = {
  id: number
  venue_id: number
  name: string
  sport_type: SportType
  slot_duration: number
  description?: string | null
  is_active: boolean
  courts_count?: number
  courts?: Court[]
  venue?: Venue
}

export type Venue = {
  id: number
  organization_id?: number
  payment_model?: 'direct_owner_v1' | 'legacy_wallet_v1'
  status?: string
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
  is_active: boolean
  share_url?: string
  courts_count?: number
  sport_zones?: SportZone[]
  image_url?: string
  amenities?: string[]
  timezone?: string
}

export type CourtBlock = {
  id: number
  court_id: number
  starts_at: string
  ends_at: string
  reason?: string
}

export type PriceSlot = {
  id?: number
  court_id?: number
  weekday: number
  starts_at: string
  ends_at: string
  price: number
  is_active?: boolean
}

export type AvailabilitySlot = {
  startsAt: string
  endsAt: string
  price: number
  available: boolean
}

export type Court = {
  id: number
  name: string
  slug?: string
  venue_id?: number
  sport_zone_id?: number | null
  venue: Venue
  sport_zone?: SportZone | null
  description?: string
  image_url?: string
  is_active?: boolean
  price_slots?: PriceSlot[]
  blocks?: CourtBlock[]
  reviews_count?: number
  reviews_avg_rating?: number | null
  amenities?: string[]
  surface_type?: 'wood' | 'synthetic' | 'acrylic' | 'concrete' | 'grass' | 'clay' | 'hard' | 'other' | null
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
