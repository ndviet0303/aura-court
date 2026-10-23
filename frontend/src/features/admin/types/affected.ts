/** One booking an operational change touches (design D7, AffectedReservationQuery::payload). */
export type AffectedBooking = {
  reservation_id: number
  code: string
  status: string
  in_use: boolean
  court_id: number
  court_code: string | null
  starts_at: string
  ends_at: string
  customer_name?: string | null
  customer_phone?: string | null
}

export type AffectedList = {
  bookings: AffectedBooking[]
}

export const CASE_STATUSES = [
  'pending',
  'contacting',
  'unreachable',
  'moved',
  'time_changed',
  'cancelled',
  'kept',
] as const
export type AffectedCaseStatus = (typeof CASE_STATUSES)[number]
export const OPEN_CASE_STATUSES: AffectedCaseStatus[] = ['pending', 'contacting', 'unreachable']

export const CONTACT_CHANNELS = ['phone', 'zalo', 'chat'] as const
export type ContactChannel = (typeof CONTACT_CHANNELS)[number]

export const CASE_SOURCES = [
  'operational_stop',
  'venue_suspension',
  'venue_schedule',
  'schedule_exception',
  'court_schedule',
] as const
export type AffectedSourceType = (typeof CASE_SOURCES)[number]

/** AffectedReservationCaseResource. */
export type AffectedCase = {
  id: number
  status: AffectedCaseStatus
  venue: { id: number; name: string | null }
  reservation: {
    id: number
    code: string
    status: string
    payment_status: string
    customer_name: string | null
    customer_phone: string | null
    court_id: number
    court_code: string | null
    starts_at: string
    ends_at: string
  }
  source: { type: AffectedSourceType; id: number; starts_at: string | null; ends_at: string | null }
  channel: ContactChannel | null
  note: string | null
  refund_note: string | null
  handled_by: { id: number; name: string } | null
  handled_at: string | null
  created_at: string
}

/** '' means not filtered; without a status the server lists open cases. */
export type AffectedCaseFilters = {
  venue_id: number | ''
  court_id: number | ''
  source_type: AffectedSourceType | ''
  status: AffectedCaseStatus | ''
}

/** Moved, time_changed and cancelled come only from the operator commands. */
export type CaseOutcomeForm = {
  status: 'contacting' | 'unreachable' | 'kept'
  channel: ContactChannel
  note: string
}

export type OperatorCommand = 'move-court' | 'change-time' | 'operator-cancel'

export type OperatorCommandForm = {
  customer_agreed: true
  channel: ContactChannel
  note?: string
  court_id?: number
  starts_at?: string
  ends_at?: string
  refund_note?: string
}

export type CaseAction = 'outcome' | OperatorCommand

export type AffectedCasePage = {
  data: AffectedCase[]
  meta: { current_page: number; last_page: number; total: number }
}
