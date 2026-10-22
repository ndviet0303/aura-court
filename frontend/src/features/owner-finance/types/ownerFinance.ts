/** Calendar periods of the Vietnam calendar, or a free date range (D12). */
export type FinancePeriodKind = 'day' | 'month' | 'quarter' | 'year' | 'range'

/** The screen's shared filters; 0 means every venue or court, and both dates are inclusive Vietnam dates. */
export type OwnerFinanceFilters = {
  venueId: number
  courtId: number
  kind: FinancePeriodKind
  from: string
  to: string
}

export type OwnerFinanceQuery = {
  venue_id?: number
  court_id?: number
  from: string
  to: string
  page?: number
}

/** A booking as both finance endpoints return it; payable is the recorded Pᵢ. */
export type OwnerFinanceBooking = {
  id: number
  code: string
  status: string
  venue: { id: number | null; name: string | null }
  court: { id: number; code: string | null }
  starts_at: string | null
  ends_at: string | null
  payable: number
}

export type OwnerFinanceReceiptStatus = 'pending' | 'allocated' | 'unmatched' | 'exception' | 'late'

/** One transfer received; booking and delta are null when no booking is identified by its code. */
export type OwnerFinanceTransaction = {
  id: number
  transaction_at: string
  amount: number
  currency: string
  payment_code: string | null
  status: OwnerFinanceReceiptStatus
  exception_code: string | null
  purpose: string | null
  venue: { id: number; name: string | null }
  booking: OwnerFinanceBooking | null
  delta: number | null
}

export type OwnerFinanceTransactionPage = {
  data: OwnerFinanceTransaction[]
  meta: { current_page: number; last_page: number; total: number }
}

export type FinanceMetricKey = 'received' | 'completed' | 'unfinished' | 'no_show' | 'incident'

/** A booking behind a metric: `at` put it in the period; `amount` is the receipt amount for R, else Pᵢ. */
export type OwnerFinanceMetricRow = OwnerFinanceBooking & { at: string; amount: number }

export type OwnerFinanceMetric = {
  amount: number
  count: number
  bookings: OwnerFinanceMetricRow[]
}

/** Five separate metrics; the API sends no total. */
export type OwnerFinanceReport = { metrics: Record<FinanceMetricKey, OwnerFinanceMetric> }
