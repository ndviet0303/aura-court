export interface VenueWallet {
  id: string
  venue_id: number
  venue_name: string
  address: string
  balance: number
  is_active: boolean
  currency: string
  gradient_type: 'primary' | 'secondary' | 'neutral'
}

export interface Transaction {
  id: string
  type: 'booking' | 'topup' | 'pos' | 'refund'
  title: string
  venue_name: string
  description: string
  date: string
  time: string
  amount: number
  status: 'success' | 'pending' | 'failed'
}

export interface CreditWalletPayload {
  amount: number
  method?: string
}

export interface SpendWalletPayload {
  amount: number
  order_id?: number
}
