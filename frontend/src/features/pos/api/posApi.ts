import { apiV2 } from '@/core/api/client'
import type { Product, PosShift } from '../types/pos'

export const posApi = {
  getProducts: () => apiV2.get<Product[]>('/pos/products'),

  createProduct: (payload: any) => apiV2.post<Product>('/pos/products', payload),

  adjustStock: (productId: number, payload: { quantity: number; reason?: string }) =>
    apiV2.post(`/pos/products/${productId}/stock`, payload),

  completeOrder: (payload: any) => apiV2.post('/pos/orders', payload),

  getShifts: () => apiV2.get<PosShift[]>('/pos/shifts'),

  getActiveShift: () => apiV2.get<PosShift | null>('/pos/shifts/active'),

  openShift: (payload: { initial_cash: number }) => apiV2.post<PosShift>('/pos/shifts', payload),

  closeShift: (shiftId: number, payload: any) => apiV2.post(`/pos/shifts/${shiftId}/close`, payload),

  reviewShift: (shiftId: number, payload: any) => apiV2.post(`/pos/shifts/${shiftId}/review`, payload),
}
