export interface Product {
  id: number
  sku: string
  barcode?: string
  name: string
  category: 'drinks' | 'equipment' | 'rental' | 'services' | 'membership' | string
  price: number
  cost?: number
  stock_quantity: number
  image_url?: string
  unit?: string
  is_rental?: boolean
  is_service?: boolean
  description?: string
}

export interface CartItem {
  product: Product
  quantity: number
  rental_hours?: number
  note?: string
}

export interface CourtReservation {
  id: number
  court_name: string
  court_number: number
  customer_name: string
  phone: string
  status: 'playing' | 'waiting' | 'finished' | string
  time_slot: string
  deposit_paid: number
}

export interface CustomerProfile {
  id: number
  name: string
  phone: string
  tier: 'Standard' | 'Silver' | 'Gold' | 'VIP' | string
  discount_rate: number
  wallet_balance: number
  loyalty_points: number
}

export interface PosShift {
  id: number
  status: 'open' | 'closed' | string
  opened_at: string
  closed_at?: string
  initial_cash: number
  total_cash?: number
  discrepancy?: number
}
