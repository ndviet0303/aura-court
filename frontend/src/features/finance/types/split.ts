import type { ReservationPaymentAttempt } from '@/features/booking/api/bookingApi'

export type SplitGroup = {
  id: number
  reservation_id: number
  expected_amount: number
  paid_amount: number
  status: 'pending' | 'confirmed' | 'expired' | string
  expires_at: string
  shares: SplitShare[]
  reservation: {
    id: number
    code: string
    total_amount: number
    status: string
  }
}

export type SplitShare = {
  id: number
  share_number: number
  amount: number
  paid_amount: number
  status: 'pending' | 'paid' | 'expired' | string
  paid_at: string | null
  payment_reference: string | null
  // Bank-transfer instructions of the share; null when the venue has no verified receiving account.
  payment_attempt: ReservationPaymentAttempt | null
}

export type SplitReservation = {
  id: number
  code: string
  status: string
  total_amount: number
  hold_expires_at: string | null
}
