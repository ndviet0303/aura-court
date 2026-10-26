/** Venue credit: 1 credit = 1 ₫, never expires, scoped to one organization (venue-credit spec). */
export type VenueCreditEntryType =
  'issue' | 'reserve' | 'release' | 'capture' | 'spend' | 'return' | 'cash_out'

export interface VenueCreditEntry {
  id: number
  type: VenueCreditEntryType
  amount: number
  balance_delta: number
  reserved_delta: number
  balance_after: number
  reserved_after: number
  source_type: string
  reference: string | null
  /** Only on the owner's list. */
  customer?: { id: number; name: string | null }
  created_at: string | null
}

/** The customer's credit at one organization with its latest entries. */
export interface VenueCreditAccount {
  organization: { id: number; name: string | null }
  /** Spendable now; `reserved` is held for a booking waiting for its QR payment. */
  balance: number
  reserved: number
  entries: VenueCreditEntry[]
}

export interface VenueCreditCashOutCode {
  code: string
  amount: number
  expires_at: string
}
