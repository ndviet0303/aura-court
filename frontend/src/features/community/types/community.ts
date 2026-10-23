import type { SportType } from '@/features/court/types/court'

export type Author = { id: number; name: string; avatar_url: string | null }

export type PageMeta = { current_page: number; last_page: number; total: number }

export type PostType = 'share' | 'matchmaking'

/** BR-146: a matchmaking post only describes a game; nobody joins, pays or holds a court through it. */
export type Post = {
  id: number
  type: PostType
  content: string
  images: string[]
  sport: SportType | null
  venue: { id: number; name: string; slug: string } | null
  address: string | null
  court_code: string | null
  play_at: string | null
  author: Author
  comments_count: number
  is_mine: boolean
  created_at: string
  updated_at: string
}

export type Comment = {
  id: number
  post_id: number
  content: string
  author: Author
  is_mine: boolean
  created_at: string
  updated_at: string
}

/** The matchmaking fields are ignored by the API for a share post. */
export type PostPayload = {
  type: PostType
  content: string
  images: string[]
  sport?: SportType | null
  venue_id?: number | null
  address?: string | null
  court_code?: string | null
  play_at?: string | null
}

export type ReportTargetType = 'post' | 'comment' | 'review'
export type ReportTarget = { type: ReportTargetType; id: number }

export const POST_MAX = 5000
export const COMMENT_MAX = 2000
export const REPORT_REASON_MAX = 500
export const POST_IMAGES_MAX = 10
