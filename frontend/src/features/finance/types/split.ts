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
}
