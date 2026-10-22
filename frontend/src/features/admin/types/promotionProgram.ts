export type PromotionProgramType = 'public' | 'private'
export type PromotionDiscountKind = 'percent' | 'fixed'
/** Computed by the server at request time. */
export type PromotionProgramStatus = 'upcoming' | 'running' | 'ended' | 'stopped'
/** Read from the code's voucher use: none, held for a booking pending payment, or used. */
export type VoucherCodeState = 'available' | 'held' | 'used'

export type PromotionProgramCourt = { id: number; name: string; slug: string }

export type PromotionProgram = {
  id: number
  venue_id: number
  name: string
  type: PromotionProgramType
  discount_kind: PromotionDiscountKind
  /** Hundredths of a percent: 12.5% = 1250. */
  percent_bp: number | null
  percent: number | null
  amount: number | null
  /** Percent programs only. */
  max_discount: number | null
  min_rental: number | null
  starts_at: string
  ends_at: string
  stopped_at: string | null
  status: PromotionProgramStatus
  /** Public programs only. */
  max_uses: number | null
  /** Private programs only. */
  code_count: number | null
  /** By id. */
  courts: PromotionProgramCourt[]
  usage: { held: number; used: number; remaining: number }
  created_at: string
  updated_at: string
}

/** Terms an owner can still change after issuing; fields of the other discount or voucher kind are left out. */
export type PromotionProgramTerms = {
  name: string
  percent?: number
  amount?: number
  max_discount?: number | null
  min_rental: number | null
  starts_at: string
  ends_at: string
  court_ids: number[]
  max_uses?: number
}

export type CreatePromotionProgramPayload = PromotionProgramTerms & {
  type: PromotionProgramType
  discount_kind: PromotionDiscountKind
  code_count?: number
}

export type VoucherCode = {
  id: number
  code: string
  state: VoucherCodeState
  /** The booking page of `court` with the code filled in; null when the program has no court. */
  deep_link_url: string | null
}

export type PromotionProgramCodes = {
  program_id: number
  /** The program's court with the smallest id, which the deep links open. */
  court: PromotionProgramCourt | null
  /** By id; empty for a public program. */
  codes: VoucherCode[]
}
