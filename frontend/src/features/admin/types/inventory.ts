export interface InventoryProduct {
  id: number
  sku: string
  barcode?: string
  name: string
  category: 'drinks' | 'equipment' | 'accessories' | 'rental' | 'services' | 'snacks' | string
  price: number
  cost: number
  stock_quantity: number
  min_stock: number
  unit: string
  image_url?: string
  description?: string
  is_active: boolean
  last_restocked?: string
  supplier?: string
}

export interface StockMovement {
  id: number
  product_id: number
  product_name: string
  product_sku: string
  type: 'in' | 'out' | 'adjust' | 'return' | string
  quantity: number
  unit: string
  reference_code: string
  note?: string
  created_by: string
  created_at: string
  balance_after: number
}

export interface InventoryAudit {
  id: number
  code: string
  status: 'draft' | 'in_progress' | 'completed' | 'cancelled' | string
  created_by: string
  created_at: string
  completed_at?: string
  total_items: number
  matched_items: number
  variance_items: number
}

export interface Supplier {
  id: number
  name: string
  contact_phone?: string
  email?: string
  address?: string
  categories: string[]
}
