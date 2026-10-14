export type ProductCategory =
  'all' | 'drinks' | 'nutrition' | 'equipment' | 'rental' | 'services' | 'membership' | string

export interface Product {
  id: number
  sku: string
  barcode?: string
  name: string
  category: ProductCategory
  price: number
  cost?: number
  stock_quantity: number
  image_url?: string
  unit?: string
  is_rental?: boolean
  is_service?: boolean
  vat_rate?: number
  description?: string
  metadata?: Record<string, any>
}

export interface CartItem {
  product: Product
  quantity: number
  rental_hours?: number
  vat_rate?: number
  note?: string
}

export interface VatBreakdown {
  rate: number
  percent: number
  amount: number
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
  // The venue has a verified owner receiving connection, so QR tender is offered.
  qr_available?: boolean
  cashier_id?: number
  cashier_name?: string
  cashier?: {
    id: number
    name: string
    email: string
  }
  status: 'open' | 'closed' | string
  opened_at: string
  closed_at?: string
  initial_cash?: number
  opening_cash?: number
  cash_sales?: number
  qr_sales?: number
  wallet_sales?: number
  expected_cash?: number
  total_orders?: number
  total_cash?: number
  declared_cash?: number
  variance?: number
  discrepancy?: number
  note?: string
}

export interface Draft {
  id: string
  name: string
  items: Array<{
    product_id: number
    quantity: number
    rental_hours?: number
    product_name?: string
    price?: number
  }>
  reservation_id?: number | null
  customer_id?: number | null
  customer_name?: string
  saved_at: string
  total_amount: number
}

export interface OrderReceiptItem {
  name: string
  quantity: number
  price: number
  total: number
  vat_rate?: number
}

export interface OrderReceipt {
  order_code: string
  created_at: string
  cashier_name: string
  customer_name?: string
  court_info?: string
  items: OrderReceiptItem[]
  subtotal: number
  discount_amount: number
  tax_amount: number
  vat_percent?: number
  vat_breakdown?: VatBreakdown[]
  grand_total: number
  payment_method: 'cash' | 'qr' | 'wallet' | 'court_tab' | string
  cash_tendered?: number
  cash_change?: number
  change_due?: number
}
