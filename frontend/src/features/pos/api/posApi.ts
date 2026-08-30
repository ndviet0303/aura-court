import { api, apiV2 } from '@/core/api/client'
import type { Product, PosShift, CustomerProfile, CourtReservation } from '../types/pos'

export const posApi = {
  getProducts: () => apiV2.get<Product[]>('/pos/products'),

  createProduct: (payload: any) => apiV2.post<Product>('/pos/products', payload),

  adjustStock: (productId: number, payload: { quantity: number; reason?: string }) =>
    apiV2.post(`/pos/products/${productId}/stock`, payload),

  completeOrder: (payload: any) => apiV2.post('/pos/orders', payload),

  getShifts: () => apiV2.get<PosShift[]>('/pos/shifts'),

  getActiveShift: () => apiV2.get<PosShift | null>('/pos/shifts/active'),

  openShift: (payload: { initial_cash?: number; opening_cash?: number } | any) =>
    apiV2.post<PosShift>('/pos/shifts', payload),

  closeShift: (shiftId: number, payload: any) => apiV2.post(`/pos/shifts/${shiftId}/close`, payload),

  reviewShift: (shiftId: number, payload: any) => apiV2.post(`/pos/shifts/${shiftId}/review`, payload),

  getActiveReservations: async (): Promise<CourtReservation[]> => {
    try {
      const res = await api.get<{ data?: any[] } | any[]>('/admin/bookings?status=confirmed')
      const list = Array.isArray(res) ? res : res?.data || []
      return list.map((b: any) => ({
        id: b.id,
        court_name: b.court?.name || `Sân ${b.court_id}`,
        court_number: b.court?.id || b.court_id || 1,
        customer_name: b.user?.name || b.customer_name || 'Khách vãng lai',
        phone: b.user?.phone || b.phone || '',
        status: b.status === 'confirmed' ? 'playing' : 'waiting',
        time_slot: b.time_slot || `${b.start_time || '18:00'} - ${b.end_time || '20:00'}`,
        deposit_paid: b.deposit_paid ?? b.total_price ?? 0,
      }))
    } catch {
      return []
    }
  },

  searchCustomers: async (query?: string): Promise<CustomerProfile[]> => {
    try {
      const res = await api.get<{ data?: any[] } | any[]>(
        `/admin/memberships${query ? `?search=${encodeURIComponent(query)}` : ''}`
      )
      const list = Array.isArray(res) ? res : res?.data || []
      return list.map((m: any) => ({
        id: m.id || m.user_id,
        name: m.user?.name || m.name || '',
        phone: m.user?.phone || m.phone || '',
        tier: m.tier || m.plan?.name || 'Standard',
        discount_rate: m.discount_rate ?? 0,
        wallet_balance: m.wallet_balance ?? 0,
        loyalty_points: m.loyalty_points ?? 0,
      }))
    } catch {
      return []
    }
  },
}
