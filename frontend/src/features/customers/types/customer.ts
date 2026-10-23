import type { OperationalReservation } from '@/features/operations/types/operations'

/** BR-141: tier by completed bookings in the quarter (15 / 35 / 60). */
export type CustomerTier = 'bronze' | 'silver' | 'gold' | 'diamond'

/** FR-110: one account's figures at one venue for the chosen quarter. */
export interface VenueCustomer {
  id: number
  name: string
  phone: string | null
  email: string | null
  avatar_url: string | null
  completed: number
  no_show: number
  used_minutes: number
  completed_amount: number
  last_used_at: string | null
  tier: CustomerTier
  /** BR-142: percent, null when there is no completed or missed booking ("Chưa có dữ liệu"). */
  usage_rate: number | null
}

export interface VenueCustomerPage {
  quarter: string
  data: VenueCustomer[]
  meta: { current_page: number; last_page: number; total: number }
}

export interface VenueCustomerProfile {
  quarter: string
  customer: VenueCustomer
  history: OperationalReservation[]
}
