import type { Author, Comment, PageMeta, ReportTargetType } from './community'

export type ReportStatus = 'pending' | 'resolved'
export type ModerationAction = 'hidden' | 'restored' | 'kept'
export type ModerationCommand = 'hide' | 'restore' | 'keep'

type Actor = { id: number; name: string }

export type ContentReport = {
  id: number
  type: ReportTargetType
  target_id: number
  reason: string
  status: ReportStatus
  action_taken: ModerationAction | null
  reporter: Actor
  reviewer: Actor | null
  reviewed_at: string | null
  created_at: string
  /** Null once the author deleted the content. */
  target: { author: Author; excerpt: string; is_hidden: boolean } | null
}

export type ContentReportPage = { data: ContentReport[]; meta: PageMeta }

export type ContentReportDetail = {
  report: ContentReport
  target: {
    type: ReportTargetType
    id: number
    author: Author
    content: string
    images: string[]
    rating?: number
    is_hidden: boolean
    created_at: string
  } | null
  context: {
    post?: { id: number; content: string; author: Author }
    comments?: Comment[]
    venue?: { id: number; name: string }
    reservation_code?: string
  }
  pending_reports: number
  history: { action: string; reason: string; actor: Actor; created_at: string }[]
}

export type ReportFilters = { status: ReportStatus | 'all'; type: ReportTargetType | '' }

export const MODERATION_NOTE_MAX = 1000
