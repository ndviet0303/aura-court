import { apiV2 } from '@/core/api/client'
import type { InventoryProduct, StockMovement, InventoryAudit } from '../types/inventory'

export const inventoryApi = {
  getProducts: () => apiV2.get<InventoryProduct[]>('/pos/products'),
  createProduct: (payload: Partial<InventoryProduct>) => apiV2.post<InventoryProduct>('/pos/products', payload),
  updateProduct: (id: number, payload: Partial<InventoryProduct>) => apiV2.put<InventoryProduct>(`/pos/products/${id}`, payload),
  deleteProduct: (id: number) => apiV2.delete(`/pos/products/${id}`),

  adjustStock: (productId: number, payload: { quantity: number; type: string; note?: string }) =>
    apiV2.post(`/pos/products/${productId}/stock`, payload),

  getMovements: () => apiV2.get<StockMovement[]>('/pos/inventory/movements'),
  getAudits: () => apiV2.get<InventoryAudit[]>('/pos/inventory/audits'),
}
