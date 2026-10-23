export interface InventoryProduct {
  id: number
  sku: string
  barcode?: string | null
  name: string
  category: 'drinks' | 'equipment' | 'accessories' | 'rental' | 'services' | 'snacks' | string
  price: number
  cost: number
  stock_quantity: number
  min_stock: number
  unit: string
  tax_rate?: number
  image_url?: string | null
  description?: string | null
  is_active: boolean
  /** Services and untracked items keep no stock. */
  is_service?: boolean
  last_restocked?: string | null
  supplier?: string | null
  created_at?: string
  updated_at?: string
}

export interface StockMovement {
  id: number
  organization_id?: number
  product_id: number
  product_name: string
  product_sku: string
  type: 'in' | 'out' | 'adjust' | 'receipt' | 'adjustment' | 'sale' | 'return' | 'disposal' | string
  quantity_delta?: number
  quantity: number
  unit_cost?: number
  unit: string
  reference_type?: string | null
  reference_id?: number | null
  reference_code?: string | null
  note?: string | null
  created_by?: number | null
  created_by_name?: string
  created_at: string
  balance_after?: number | null
  product?: InventoryProduct
}

export interface InventoryKpis {
  totalItems: number
  totalValue: number
  lowStockCount: number
  outOfStockCount: number
}

export interface ProductFormPayload {
  name: string
  sku: string
  barcode?: string | null
  category: string
  price: number
  cost: number
  stock_quantity?: number
  min_stock: number
  unit: string
  tax_rate?: number
  description?: string | null
  supplier?: string | null
  is_active?: boolean
  /** The venue that receives the opening stock; required by the server when stock_quantity > 0. */
  venue_id?: number
}

export interface StockActionPayload {
  productId: number
  venueId: number
  type: 'in' | 'out' | 'adjust'
  quantity: number
  unitCost?: number
  note?: string
  reference?: string
}

export interface StockTransferPayload {
  product_id: number
  from_venue_id: number
  to_venue_id: number
  quantity: number
  note?: string
}

export interface StockTransfer {
  id: number
  product_id: number
  from_venue_id: number
  to_venue_id: number
  quantity: number
  note?: string | null
  created_by?: number | null
  created_at: string
}
