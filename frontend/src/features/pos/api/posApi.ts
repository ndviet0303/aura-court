import { api, apiV2 } from '@/core/api/client'
import type { Product, PosShift, CustomerProfile, CourtReservation } from '../types/pos'

export const posApi = {
  getProducts: async (): Promise<Product[]> => {
    const res = await apiV2.get<Product[]>('/pos/products')
    return Array.isArray(res) ? res : []
  },

  createProduct: async (payload: {
    sku: string
    name: string
    price: number
    cost?: number
    barcode?: string
    description?: string
  }): Promise<Product> => {
    const res = await apiV2.post<{ product: Product } | Product>('/pos/products', payload)
    return (res as any)?.product || res
  },

  adjustStock: async (
    productId: number,
    payload: { quantity_delta: number; type?: 'receipt' | 'adjustment' }
  ): Promise<Product> => {
    const res = await apiV2.post<{ product: Product } | Product>(
      `/pos/products/${productId}/stock`,
      payload
    )
    return (res as any)?.product || res
  },

  getQuote: async (payload: {
    items: Array<{
      product_id: number
      quantity: number
      rental_duration_hours?: number
      rental_hours?: number
      vat_rate?: number
    }>
    customer_id?: number | null
    reservation_id?: number | null
    venue_id?: number | null
    coupon_code?: string
    discount_amount?: number
    payment_method?: 'cash' | 'qr' | 'wallet'
    cash_tendered?: number
    rounding_amount?: number
  }) => {
    const res = await apiV2.post<{ quote: any }>('/pos/quote', payload)
    return (res as any)?.quote || res
  },

  completeOrder: async (payload: {
    items: Array<{
      product_id: number
      quantity: number
      rental_duration_hours?: number
      rental_hours?: number
      vat_rate?: number
    }>
    customer_id?: number | null
    reservation_id?: number | null
    venue_id?: number | null
    payment_method: 'cash' | 'qr' | 'wallet'
    payment_reference?: string
    discount_amount?: number
    coupon_code?: string
    cash_tendered?: number
    rounding_amount?: number
    offline?: boolean
  }, idempotencyKey?: string) => {
    const config = idempotencyKey ? { headers: { 'Idempotency-Key': idempotencyKey } } : undefined
    const res = await apiV2.post<{ order: any; receipt?: any } | any>('/pos/orders', payload, config)
    return res
  },

  getOrder: async (orderId: number | string) => {
    return await apiV2.get<{ order: any; receipt: any }>(`/pos/orders/${orderId}`)
  },

  confirmQrPayment: async (
    orderId: number | string,
    payload: {
      external_transaction_id?: string
      amount?: number
      provider?: string
      notes?: string
    } = {}
  ) => {
    return await apiV2.post<{ order: any; receipt: any }>(
      `/pos/orders/${orderId}/confirm-qr`,
      payload
    )
  },

  cancelQrPayment: async (
    orderId: number | string,
    payload: { reason?: string } = {}
  ) => {
    return await apiV2.post<{ order: any; receipt: any }>(
      `/pos/orders/${orderId}/cancel-qr`,
      payload
    )
  },
  getShifts: async (): Promise<PosShift[]> => {
    const res = await apiV2.get<{ data?: PosShift[] } | PosShift[]>('/pos/shifts')
    return Array.isArray(res) ? res : (res as any)?.data || []
  },

  getActiveShift: async (): Promise<PosShift | null> => {
    const res = await apiV2.get<{ shift: PosShift | null }>('/pos/shifts/active')
    return res?.shift ?? null
  },

  openShift: async (payload: { opening_cash: number }): Promise<PosShift> => {
    const res = await apiV2.post<{ shift: PosShift } | PosShift>('/pos/shifts', payload)
    return (res as any)?.shift || res
  },

  closeShift: async (
    shiftId: number,
    payload: { declared_cash: number; note?: string }
  ): Promise<PosShift> => {
    const res = await apiV2.post<{ shift: PosShift } | PosShift>(
      `/pos/shifts/${shiftId}/close`,
      payload
    )
    return (res as any)?.shift || res
  },

  reviewShift: async (
    shiftId: number,
    payload: { status: string; note?: string }
  ): Promise<PosShift> => {
    const res = await apiV2.post<{ shift: PosShift } | PosShift>(
      `/pos/shifts/${shiftId}/review`,
      payload
    )
    return (res as any)?.shift || res
  },

  getTab: async (reservationId: number | string) => {
    return apiV2.get<{ tab: any }>(`/service-tabs/${reservationId}`)
  },

  addItemToTab: async (payload: {
    reservation_id: number
    items: Array<{ product_id: number; quantity: number }>
  }) => {
    return apiV2.post<{ tab: any; message: string }>('/service-tabs', payload)
  },

  removeItemFromTab: async (reservationId: number | string, itemId: number) => {
    return apiV2.delete<{ tab: any; message: string }>(`/service-tabs/${reservationId}/items/${itemId}`)
  },

  settleTab: async (
    tabId: number,
    payload: {
      payment_method: 'cash' | 'wallet' | 'qr'
      cash_tendered?: number
      customer_id?: number
    }
  ) => {
    return apiV2.post<{ tab: any; order: any; receipt: any; message: string }>(
      `/service-tabs/${tabId}/settle`,
      payload
    )
  },

  cancelTab: async (tabId: number, reason?: string) => {
    return apiV2.post<{ tab: any; message: string }>(`/service-tabs/${tabId}/cancel`, { reason })
  },

  getActiveReservations: async (): Promise<CourtReservation[]> => {
    try {
      const res = await apiV2.get<{ data?: any[] } | any[]>('/reservations?status=confirmed')
      const list = Array.isArray(res) ? res : res?.data || []
      return list.map((r: any) => {
        let courtName = r.court?.name
        if (typeof courtName === 'object' && courtName !== null) {
          courtName = courtName.vi || courtName.en || Object.values(courtName)[0]
        }
        let customerName = r.user?.name || r.customer_name
        if (typeof customerName === 'object' && customerName !== null) {
          customerName = customerName.vi || customerName.en || Object.values(customerName)[0]
        }
        const timeSlot = r.slots?.[0]
          ? `${r.slots[0].starts_at?.slice(11, 16) || ''} - ${r.slots[r.slots.length - 1]?.ends_at?.slice(11, 16) || ''}`
          : r.time_slot || '18:00 - 20:00'

        return {
          id: r.id,
          court_name: (courtName as string) || `Sân #${r.court_id || 1}`,
          court_number: r.court?.id || r.court_id || 1,
          customer_name: (customerName as string) || 'Khách vãng lai',
          phone: r.user?.phone || r.phone || '',
          status: r.status === 'confirmed' ? 'playing' : 'waiting',
          time_slot: timeSlot,
          deposit_paid: r.deposit_paid ?? r.total_amount ?? 0,
        }
      })
    } catch {
      return []
    }
  },

  searchCustomers: async (query?: string): Promise<CustomerProfile[]> => {
    try {
      const q = query ? query.trim() : ''
      const url = q ? `/customers?q=${encodeURIComponent(q)}` : '/customers'
      const res = await apiV2.get<{ customers?: any[] } | any[]>(url)
      const list = Array.isArray(res) ? res : (res as any)?.customers || []

      return list.map((c: any) => ({
        id: c.id,
        name: c.name || 'Hội viên',
        phone: c.phone || '',
        tier: c.tier || 'Standard',
        discount_rate: typeof c.discount_rate === 'number' ? c.discount_rate : 0,
        wallet_balance: typeof c.wallet_balance === 'number' ? c.wallet_balance : 0,
        loyalty_points: typeof c.loyalty_points === 'number' ? c.loyalty_points : 0,
      }))
    } catch {
      return []
    }
  },
}
