import type { VoucherQuote } from './reservation'

// BR-162: how the customer picks the days of a recurring booking.
export type RecurringMode = 'weekdays' | 'monthDays' | 'dates'

// A day of month that a month lacks, such as 31 in November (BR-166); month is YYYY-MM.
export type SkippedDay = { month: string; day: number }

// Why the ordinary booking flow refused one occurrence: its message, and a code with amounts when it has one.
export type OccurrenceRefusal = { message: string; code?: string; total?: number }

export type RecurringReservation = {
  code: string
  status: string
  total_amount: number
  hold_expires_at: string | null
}

// One occurrence as POST /recurring-bookings/check or /recurring-bookings answered it (FR-137, FR-138).
export type RecurringOccurrenceResult = {
  starts_at: string
  ends_at: string
  ok: boolean
  refusal: OccurrenceRefusal | null
  rental: number | null
  vouchers: VoucherQuote[] | null
  code_voucher: VoucherQuote | null
  code_refusal: OccurrenceRefusal | null
  reservation: RecurringReservation | null
}

export type RecurringOccurrencePayload = {
  starts_at: string
  ends_at: string
  expected_total?: number
  voucher_program_id?: number
  voucher_code?: string
}

export type RecurringBookingPayload = {
  court_id: number
  occurrences: RecurringOccurrencePayload[]
}

// A planned occurrence: its own day and time (BR-168), whether the customer picked it for submitting (UC-8.7 A-1),
// the last check, the voucher chosen for it and, once submitted, the booking it became or why it was refused.
export type OccurrenceRow = {
  date: string
  start: string
  end: string
  // Its time was set on its own (UC-8.5) rather than by the common time.
  custom: boolean
  selected: boolean
  checking: boolean
  check: RecurringOccurrenceResult | null
  // null takes the first voucher that applies, as the booking form does; 0 is none.
  voucherId: number | null
  code: string
  booked: RecurringReservation | null
  refusal: OccurrenceRefusal | null
}
