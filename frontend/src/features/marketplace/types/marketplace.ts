export type ResaleListingStatus = 'listed' | 'checkout' | 'sold' | 'withdrawn'
export type ResaleBadge = 'urgent' | 'deal' | 'hot'

/** The seller's own bank account. Only the seller and the buyer holding the lock ever receive it. */
export type ResaleReceiver = {
  bank_code: string
  account_number: string
  account_name: string
}

/** Listing as the API returns it. It never carries receiver data. */
export type ApiResaleListing = {
  id: number
  organization_id: number
  reservation_entitlement_id: number
  seller_id: number
  original_price: number
  asking_price: number
  fee_bps: number
  status: ResaleListingStatus
  effective_status: ResaleListingStatus
  cutoff_at: string | null
  sold_at: string | null
  created_at: string | null
  metadata: {
    badge?: ResaleBadge
    notes?: string
    locked_by?: number
    locked_until?: string
    payment_reported_at?: string
  } | null
  entitlement?: {
    starts_at: string
    ends_at: string
    amount: number
    court?: { code: string; venue?: { name: string; address?: string | null } | null } | null
  } | null
  seller?: { id: number; name: string; avatar_url?: string | null } | null
}

export type ResaleLock = {
  locked_until: string
  payment_reported_at: string | null
  reference: string
  held_by_me: boolean
}

export type ResalePayment = {
  amount: number
  currency: 'VND'
  reference: string
  transfer_content: string
  qr_url: string
}

export type ResaleState = {
  listing: ApiResaleListing
  role: 'seller' | 'buyer'
  status: ResaleListingStatus
  lock: ResaleLock | null
  receiver: ResaleReceiver | null
  payment: ResalePayment | null
  transfer: {
    id: number
    status: string
    buyer_amount: number
    marketplace_fee: number
    seller_refund: number
    settlement_mode: string
  } | null
  last_release: {
    by: 'buyer' | 'seller' | 'expiry'
    at: string
    payment_reported: boolean
    reason: string | null
  } | null
}

export interface ResaleListing {
  id: number
  courtName: string
  venueName: string
  venueAddress?: string
  dateStr: string
  timeSlot: string
  timePeriod: 'morning' | 'afternoon' | 'evening'
  originalPrice: number
  resalePrice: number
  badge?: ResaleBadge
  sellerName: string
  sellerAvatar?: string
  sellerId?: number
  cutoffAt?: string
  isFeatured?: boolean
  status: ResaleListingStatus
  /** The public list only shows a listing in checkout to the buyer holding its lock. */
  heldByMe: boolean
  notes?: string
}

export interface UserBookingSlot {
  id: number
  reservationId: number
  courtName: string
  venueName: string
  dateStr: string
  timeSlot: string
  originalPrice: number
  startsAt: string
  eligible: boolean
  ineligibleReason?: string
}

export interface MyResaleListing {
  id: number
  entitlementId: number
  courtName: string
  venueName: string
  dateStr: string
  timeSlot: string
  originalPrice: number
  askingPrice: number
  status: ResaleListingStatus
  lockedUntil?: string
  paymentReportedAt?: string
  cutoffAt?: string
  createdAt?: string
}

export interface CreateListingPayload {
  asking_price: number
  seller_receiver?: ResaleReceiver
}

/** The parts of `GET /reservations` the sell tab reads. */
export type ResaleSourceReservation = {
  id: number
  status: string
  court?: { code: string; venue?: { name: string } | null } | null
  entitlements?: {
    id: number
    status: string
    starts_at: string
    ends_at: string
    amount: number | null
    court?: { code: string; venue?: { name: string } | null } | null
  }[]
}
