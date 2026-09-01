import type { Court } from '@/features/court/types/court'

export type RecurringFrequency = 'weekly' | 'biweekly' | 'monthly'
export type RecurringStatus = 'draft' | 'confirmed' | 'active' | 'paused' | 'cancelled' | 'delinquent'
export type InvoiceStatus = 'pending' | 'paid' | 'overdue'
export type OccurrenceStatus = 'scheduled' | 'confirmed' | 'completed' | 'cancelled' | 'released'

export interface RecurringInvoice {
  id: number | string
  uuid?: string
  recurring_series_id: number
  organization_id?: number
  period_start: string
  period_end: string
  due_at: string
  amount: number
  status: InvoiceStatus
  payment_reference?: string | null
  paid_at?: string | null
  series?: RecurringSeries
}

export interface RecurringOccurrence {
  id: number
  recurring_series_id: number
  organization_id?: number
  reservation_id?: number
  sequence: number
  starts_at: string
  ends_at: string
  amount: number
  status: OccurrenceStatus
}

export interface RecurringSeries {
  id: number
  organization_id?: number
  user_id?: number
  court_id: number
  court?: Court
  frequency: RecurringFrequency
  starts_at: string
  ends_at: string
  occurrence_count?: number
  status: RecurringStatus
  total_amount: number
  next_invoice_due_at?: string | null
  invoices?: RecurringInvoice[]
  occurrences?: RecurringOccurrence[]
  metadata?: Record<string, unknown>
  created_at?: string
  updated_at?: string
}

export interface CreateRecurringSeriesPayload {
  court_id: number
  frequency: RecurringFrequency
  starts_at: string
  ends_at?: string
  until?: string
  occurrences?: number
  coupon_code?: string
  weekdays?: number[]
}

export interface WeekdayOption {
  id: number
  label: string
  name: string
  fullName: string
}
