// Module 08.2 booking transfer (FR-139..FR-147). Money is integer VND; times are ISO 8601 with offset.

export type TransferRefusal = { code: string; message: string }

/** One refused booking of a 422 `{message, code, items}` answer. */
export type TransferItemRefusal = TransferRefusal & { reservation_id: number }

export type TransferVenue = { id: number; name: string; address: string | null }
export type TransferCourt = { id: number; name: string; image_url: string | null }
/** Never carries the email, phone or booking code of the other customer. */
export type TransferUser = { id: number; name: string; avatar_url: string | null }

/** UC-9.1: an upcoming confirmed booking of mine, with why it cannot be listed (FR-139). */
export type TransferCandidate = {
  reservation_id: number
  code: string
  venue: TransferVenue
  court: TransferCourt
  starts_at: string
  ends_at: string
  paid_amount: number
  max_price: number
  /** Set on the bookings of one recurring submission (BR-179); null otherwise. */
  recurring_group: string | null
  eligible: boolean
  refusal: TransferRefusal | null
}

export type ListingItemState = 'available' | 'held' | 'transferred' | 'withdrawn' | 'expired'

export type ListingItem = {
  reservation_id: number
  venue: TransferVenue
  court: TransferCourt
  starts_at: string
  ends_at: string
  paid_amount: number
  price: number
  state: ListingItemState
}

/** Who contacted the seller about the listing, seen by the seller only. */
export type ListingContact = {
  user: TransferUser
  conversation_id: number
  reservation_ids: number[]
  contacted_at: string
}

export type TransferListing = {
  id: number
  note: string | null
  created_at: string
  seller: TransferUser
  is_mine: boolean
  items: ListingItem[]
  contacts?: ListingContact[]
}

export type PricedItem = { reservation_id: number; price: number }

export type TransferStatus = 'pending' | 'completed' | 'cancelled'

export type BookingTransferItem = {
  reservation_id: number
  venue: TransferVenue
  court: TransferCourt
  starts_at: string
  ends_at: string
  price: number
  /** Re-checked when read, so the seller sees why a confirmation would fail. */
  refusal: TransferRefusal | null
}

export type BookingTransfer = {
  id: number
  status: TransferStatus
  listing_id: number | null
  seller: TransferUser
  buyer: TransferUser
  is_seller: boolean
  items: BookingTransferItem[]
  total: number
  created_at: string
  confirmed_at: string | null
  cancelled_at: string | null
}

export type CreateTransferPayload = {
  listing_id?: number
  buyer_id: number
  items: PricedItem[]
}

export type TransferPage<T> = {
  data: T[]
  meta: { current_page: number; last_page: number; total: number }
}
