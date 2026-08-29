export interface ResaleListing {
  id: number
  courtName: string
  venueName: string
  dateStr: string
  timeSlot: string
  timePeriod: 'morning' | 'afternoon' | 'evening'
  originalPrice: number
  resalePrice: number
  badge?: 'urgent' | 'deal' | 'hot'
  sellerName: string
  sellerAvatar?: string
  cutoffAt?: string
  isFeatured?: boolean
}

export interface UserBookingSlot {
  id: number
  entitlementId: number
  courtName: string
  venueName: string
  dateStr: string
  timeSlot: string
  originalPrice: number
  eligible: boolean
  ineligibleReason?: string
  dateBadge: {
    dayOfWeek: string
    dayNum: string
  }
}

export interface TransactionLog {
  id: number
  listingId?: number
  courtName: string
  venueName: string
  timeSlot: string
  dateStr: string
  status: 'active' | 'completed' | 'cancelled'
  resalePrice: number
  netReceived?: number
  updatedAt: string
}
