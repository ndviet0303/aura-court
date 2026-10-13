import { api } from '@/core/api/client'
import type {
  InventoryProduct,
  StockMovement,
  InventoryKpis,
  ProductFormPayload,
  StockActionPayload,
} from '../types/inventory'

export const inventoryApi = {
  getProducts: (params?: Record<string, any>) =>
    api.get<InventoryProduct[]>('/pos/products', { params }),

  createProduct: async (payload: ProductFormPayload): Promise<InventoryProduct> => {
    const res = await api.post<{ product: InventoryProduct }>('/pos/products', payload)
    return res.product || (res as unknown as InventoryProduct)
  },

  updateProduct: async (
    id: number,
    payload: Partial<ProductFormPayload>,
  ): Promise<InventoryProduct> => {
    const res = await api.put<{ product: InventoryProduct }>(`/pos/products/${id}`, payload)
    return res.product || (res as unknown as InventoryProduct)
  },

  deleteProduct: (id: number) => api.delete<{ message: string }>(`/pos/products/${id}`),

  adjustStock: async (payload: StockActionPayload): Promise<InventoryProduct> => {
    const res = await api.post<{ product: InventoryProduct }>(
      `/pos/products/${payload.productId}/stock`,
      {
        quantity: payload.quantity,
        type: payload.type,
        note: payload.note,
        reference: payload.reference,
      },
    )
    return res.product || (res as unknown as InventoryProduct)
  },

  getMovements: (params?: Record<string, any>) =>
    api.get<StockMovement[]>('/pos/inventory/movements', { params }),

  getKpis: () => api.get<InventoryKpis>('/pos/inventory/kpis'),
}
