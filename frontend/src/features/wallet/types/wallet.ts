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
  organization_id: number
  venue_id: number
  venue_name: string
  card_number: string
  address: string
  balance: number
  points: number
  is_active: boolean
  currency: string
  /** Active tenant membership plan name; null when the customer has no active membership. */
  tier_label: string | null
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

export interface SpendWalletPayload {
  amount: number
  order_id?: number
  reference?: string
}
