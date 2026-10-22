import type { ApiError } from '@/core/types/api'
import type { PackageTermDays, PackageTermPrice } from '@/features/platform-fees/types/platformFee'

/** A venue's package status, derived by the server at the time of reading (D1). */
export type PackageStatus = 'none' | 'active' | 'expired' | 'terminated'

/** A pending request past its deadline already reads as expired (D7). */
export type PackagePaymentRequestStatus = 'pending' | 'paid' | 'expired'

export type VenuePackage = {
  id: number
  venue_id: number
  term_days: PackageTermDays
  starts_at: string
  ends_at: string
  status: PackageStatus
  terminated_at: string | null
}

/** AuraCourt's receiving account for package fees (D5). */
export type PackageReceiver = {
  bank_code: string
  account_number: string | null
  account_name: string
}

/** A package payment request; its term, amount and deadline never change (D4). */
export type PackagePaymentRequest = {
  request_code: string
  payment_code: string
  venue_id: number
  kind: 'purchase' | 'renewal'
  term_days: PackageTermDays
  days: number
  amount: number
  status: PackagePaymentRequestStatus
  expires_at: string
  paid_at: string | null
  period_starts_at: string | null
  period_ends_at: string | null
  receiver: PackageReceiver
  /** VietQR image of the amount and payment code; null while AuraCourt's account is not configured. */
  qr_url: string | null
}

/** One venue on the owner's package screen. */
export type VenuePackageOverview = {
  venue_id: number
  venue_name: string
  venue_status: 'inactive' | 'active' | 'paused' | 'closed'
  package_status: PackageStatus
  package: VenuePackage | null
  pending_request: PackagePaymentRequest | null
  /** Newest first, the live pending request included. */
  requests: PackagePaymentRequest[]
}

export type VenuePackagesOverview = {
  venues: VenuePackageOverview[]
  terms: PackageTermPrice[]
}

/** A 409 PACKAGE_REQUEST_PENDING carries the venue's live pending request in `data`. */
export type PackageRequestError = ApiError & { data?: PackagePaymentRequest }
