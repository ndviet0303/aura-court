import type { Author, PageMeta } from './community'

export type ReviewReply = { body: string; replied_at: string }

/** FR-115..FR-117: a review belongs to the venue; the court is only the booked one. */
export type Review = {
  id: number
  rating: number
  body: string | null
  images: string[]
  author: Author
  venue_id: number
  court: { id: number; code: string } | null
  reservation_code: string
  reply: ReviewReply | null
  is_mine: boolean
  editable_until: string
  created_at: string
  updated_at: string
}

export type ReviewPage = {
  data: Review[]
  meta: PageMeta
  summary: { count: number; average: number | null }
}

export type ReviewPayload = { rating: number; body: string | null; images: string[] }

export const REVIEW_BODY_MAX = 2000
export const REVIEW_IMAGES_MAX = 5
