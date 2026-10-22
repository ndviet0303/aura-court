/** Module 06 D3: no attendance row is confirmed; check-in, check-out and no-show move it on. */
export type OperationalStatus = 'confirmed' | 'in_use' | 'completed' | 'no_show'
/** FR-104: a booking in use within 10 minutes of its booked end, or past it. */
export type UsageFlag = 'ending_soon' | 'overdue'
export type AttendanceCommand = 'check-in' | 'check-out' | 'no-show'

export interface OperationalReservation {
  id: number
  code: string
  operational_status: OperationalStatus
  usage_flag: UsageFlag | null
  venue: { id: number; name: string }
  court: { id: number; code: string; name: string }
  starts_at: string
  ends_at: string
  customer_name: string | null
  customer_phone: string | null
  total_amount: number
  currency: string
  attendance: {
    checked_in_at: string | null
    checked_in_by: string | null
    checked_out_at: string | null
    checked_out_by: string | null
    no_show_at: string | null
    no_show_by: string | null
  } | null
}

export interface OperationsCourt {
  id: number
  code: string
  name: string
  status: string
}

export interface OperationsDay {
  date: string
  venue: { id: number; name: string }
  courts: OperationsCourt[]
  reservations: OperationalReservation[]
}

/** BR-122: the payment result an operator may see, without receiver details. */
export interface AttendancePayment {
  status: string
  amount: number
  currency: string
  paid_at: string | null
}

export interface AttendanceSnapshot {
  status: OperationalStatus
  checked_in_at: string | null
  checked_out_at: string | null
  no_show_at: string | null
}

export interface AttendanceAdjustment {
  id: number
  reason: string
  before: AttendanceSnapshot
  after: AttendanceSnapshot
  adjusted_by: string | null
  created_at: string
}

/** FR-108: the corrected status and times, as venue-clock instants. */
export interface AdjustmentForm {
  code: string
  status: OperationalStatus
  checked_in_at?: string
  checked_out_at?: string
  reason: string
}

export interface AttendanceResult {
  reservation: OperationalReservation
  /** A replayed check-in or check-out that was already recorded. */
  duplicate?: boolean
  message: string
}
