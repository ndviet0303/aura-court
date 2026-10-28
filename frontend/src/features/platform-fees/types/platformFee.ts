import type { FinancePeriodKind } from '@/features/owner-finance/types/ownerFinance'
import type {
  PackagePaymentRequestStatus,
  PackageStatus,
  VenuePackage,
} from '@/features/platform-subscriptions/types'

/** The four package terms in days (D3); every term grants the same functions. */
export type PackageTermDays = 30 | 90 | 180 | 360

export const PACKAGE_TERMS: readonly PackageTermDays[] = [30, 90, 180, 360]

/** A package price in whole VND, effective from a UTC instant. */
export type PackagePrice = {
  id: number
  term_days: PackageTermDays
  amount: number
  effective_at: string
}

/** One term as platform admins see it: its applicable price and its next scheduled price. */
export type PackageTermPrice = {
  term_days: PackageTermDays
  days: number
  current_price: PackagePrice | null
  next_price: PackagePrice | null
}

export type SchedulePackagePricePayload = {
  term_days: number
  amount: number | null
  /** UTC ISO instant; left out, the price takes effect now. */
  effective_at?: string
  /** UC-5.2: the term's first price; refused with 409 PACKAGE_PRICE_EXISTS once it has one. */
  initial: boolean
}

export type PlatformFeeOwner = { id: number; name: string | null; email: string | null }

export type PlatformFeeReceiptOutcome =
  'applied' | 'late' | 'amount_mismatch' | 'already_paid' | 'term_conflict' | 'account_mismatch'

/** A stored transfer linked to a request; its amount may differ from the request's. */
export type PlatformFeeReceipt = {
  id: number
  provider_transaction_id: string
  amount: number
  transferred_at: string
  received_at: string
  outcome: PlatformFeeReceiptOutcome
}

/** A package payment request as platform admins see it; the detail adds its receipts. */
export type PlatformFeeRequest = {
  id: number
  request_code: string
  payment_code: string
  venue: { id: number; name: string | null; status: string | null }
  organization: { id: number; name: string | null }
  owner: PlatformFeeOwner | null
  kind: 'purchase' | 'renewal'
  term_days: PackageTermDays
  amount: number
  status: PackagePaymentRequestStatus
  created_at: string
  expires_at: string
  paid_at: string | null
  period_starts_at: string | null
  period_ends_at: string | null
  venue_package_id: number | null
  receipts?: PlatformFeeReceipt[]
}

export type PlatformFeePage<T> = {
  data: T[]
  meta: { current_page: number; last_page: number; total: number }
}

/** 0 or '' means no filter; the period counts only when both inclusive Vietnam dates are set. */
export type PlatformFeeRequestFilters = {
  status: PackagePaymentRequestStatus | ''
  venueId: number
  term: number
  from: string
  to: string
}

export type PlatformFeeRequestQuery = {
  status?: PackagePaymentRequestStatus
  venue_id?: number
  term?: number
  from?: string
  to?: string
  page?: number
}

/** The n-day group of non-terminated packages ending within n days (BR-116). */
export type ExpiringWithin = 1 | 3 | 7

export type PlatformFeeVenue = {
  venue_id: number
  venue_name: string | null
  venue_status: string | null
  organization: { id: number; name: string | null }
  owner: PlatformFeeOwner | null
  package_status: PackageStatus
  package: VenuePackage | null
  /** Detail only, newest first. */
  packages?: VenuePackage[]
  requests?: PlatformFeeRequest[]
}

export type PlatformFeeVenueFilters = {
  status: PackageStatus | ''
  expiringWithin: ExpiringWithin | 0
  search: string
}

export type PlatformFeeVenueQuery = {
  status?: PackageStatus
  expiring_within?: ExpiringWithin
  search?: string
  page?: number
  per_page?: number
}

/** expiring_counts counts distinct venues of every group and ignores the list filters. */
export type PlatformFeeVenuePage = PlatformFeePage<PlatformFeeVenue> & {
  expiring_counts: Record<ExpiringWithin, number>
}

export type PlatformFeeAmounts = { amount: number; purchases: number; renewals: number }

/** Fees collected on paid requests by payment time; by_term always lists the four terms. */
export type PlatformFeeReport = {
  period: { from: string; to: string }
  totals: PlatformFeeAmounts
  by_term: (PlatformFeeAmounts & { term_days: PackageTermDays })[]
  by_venue: (PlatformFeeAmounts & {
    venue_id: number
    venue_name: string | null
    term_days: PackageTermDays
  })[]
}

/** Both dates are inclusive Vietnam dates; 0 means every venue or term. */
export type PlatformFeeReportFilters = {
  venueId: number
  term: number
  kind: FinancePeriodKind
  from: string
  to: string
}

export type PlatformFeeReportQuery = {
  from: string
  to: string
  venue_id?: number
  term?: number
}
