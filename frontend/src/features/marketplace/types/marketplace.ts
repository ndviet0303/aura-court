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
  badge?: 'urgent' | 'deal' | 'hot'
  sellerName: string
  sellerAvatar?: string
  sellerEmail?: string
  sellerId?: number
  cutoffAt?: string
  isFeatured?: boolean
  status: 'listed' | 'checkout' | 'sold' | 'withdrawn'
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
  status: 'listed' | 'checkout' | 'sold' | 'withdrawn'
  cutoffAt?: string
  createdAt?: string
}

export interface PurchaseListingPayload {
  buyer_payment_reference: string
  seller_refund_accepted?: boolean
}

export interface CreateListingPayload {
  asking_price: number
}
