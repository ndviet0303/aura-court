export type MemberTier = 'standard' | 'silver' | 'gold' | 'diamond'
export type CardAccentTheme = 'burgundy' | 'gold' | 'navy' | 'emerald'
export type TransactionType = 'booking' | 'topup' | 'pos' | 'refund'
export type PaymentMethodType = 'sepay' | 'stripe'

export type CardDetails = {
  cardNumber: string
  cardExpiry: string
  cardCvc: string
  cardName: string
}

export interface VenueWallet {
  id: string
  venue_id: number
  venue_name: string
  card_number: string
  address: string
  balance: number
  points: number
  is_active: boolean
  currency: string
  member_tier: MemberTier
  tier_label: string
  accent_theme: CardAccentTheme
  card_holder: string
  expiry_date?: string
}

export interface Transaction {
  id: string
  type: TransactionType
  title: string
  venue_name: string
  description: string
  date: string
  time: string
  amount: number
  balance_after: number
  status: 'success' | 'pending' | 'failed'
  reference: string
  method_label?: string
}

export interface CreditWalletPayload {
  amount: number
  method?: string
  wallet_id?: string | number
  note?: string
}

export interface SpendWalletPayload {
  amount: number
  order_id?: number
  reference?: string
}
