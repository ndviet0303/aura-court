import { api } from '@/core/api/client'
import { venueClockRange } from '@/core/utils/venueTime'
import { resolveLocalizedText } from '@/i18n'
import type {
  Product,
  PosShift,
  CustomerProfile,
  CourtReservation,
  PosTender,
  VenueCreditCashOut,
} from '../types/pos'
import type { ReservationPaymentAttempt } from '@/features/booking/api/bookingApi'

export const posApi = {
  getProducts: async (): Promise<Product[]> => {
    const res = await api.get<Product[]>('/pos/products')
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
    const res = await api.post<{ product: Product } | Product>('/pos/products', payload)
    return (res as any)?.product || res
  },

  adjustStock: async (
    productId: number,
    payload: { quantity_delta: number; type?: 'receipt' | 'adjustment' },
  ): Promise<Product> => {
    const res = await api.post<{ product: Product } | Product>(
      `/pos/products/${productId}/stock`,
      payload,
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
    discount_amount?: number
    payment_method?: 'cash' | 'qr' | 'wallet'
    cash_tendered?: number
    rounding_amount?: number
  }) => {
    const res = await api.post<{ quote: any }>('/pos/quote', payload)
    return (res as any)?.quote || res
  },

  completeOrder: async (
    payload: {
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
      payment_method: 'cash' | 'qr' | 'wallet' | 'credit' | 'mixed'
      allow_mixed_tender?: boolean
      tenders?: PosTender[]
      payment_reference?: string
      discount_amount?: number
      cash_tendered?: number
      rounding_amount?: number
      offline?: boolean
    },
    idempotencyKey?: string,
  ) => {
    const config = idempotencyKey ? { headers: { 'Idempotency-Key': idempotencyKey } } : undefined
    const res = await api.post<{ order: any; receipt?: any } | any>('/pos/orders', payload, config)
    return res
  },

  getOrder: async (orderId: number | string) => {
    return await api.get<{
      order: any
      receipt: any
      payment_attempt: ReservationPaymentAttempt | null
    }>(`/pos/orders/${orderId}`)
  },

  cancelQrPayment: async (orderId: number | string, payload: { reason?: string } = {}) => {
    return await api.post<{ order: any; receipt: any }>(`/pos/orders/${orderId}/cancel-qr`, payload)
  },
  getShifts: async (): Promise<PosShift[]> => {
    const res = await api.get<{ data?: PosShift[] } | PosShift[]>('/pos/shifts')
    return Array.isArray(res) ? res : (res as any)?.data || []
  },

  getActiveShift: async (): Promise<PosShift | null> => {
    const res = await api.get<{ shift: PosShift | null }>('/pos/shifts/active')
    return res?.shift ?? null
  },

  // The caller's own venues (owner: all, staff: assigned ones); admin routes return names as { vi, en }.
  getVenues: async (): Promise<Array<{ id: number; name: string }>> => {
    const venues = await api.get<Array<{ id: number; name: unknown }>>('/admin/venues')
    return venues.map((venue) => ({ id: venue.id, name: resolveLocalizedText(venue.name) }))
  },

  openShift: async (payload: { opening_cash: number; venue_id?: number }): Promise<PosShift> => {
    const res = await api.post<{ shift: PosShift } | PosShift>('/pos/shifts', payload)
    return (res as any)?.shift || res
  },

  closeShift: async (
    shiftId: number,
    payload: { declared_cash: number; note?: string },
  ): Promise<PosShift> => {
    const res = await api.post<{ shift: PosShift } | PosShift>(
      `/pos/shifts/${shiftId}/close`,
      payload,
    )
    return (res as any)?.shift || res
  },

  reviewShift: async (
    shiftId: number,
    payload: { status: string; note?: string },
  ): Promise<PosShift> => {
    const res = await api.post<{ shift: PosShift } | PosShift>(
      `/pos/shifts/${shiftId}/review`,
      payload,
    )
    return (res as any)?.shift || res
  },

  getTab: async (reservationId: number | string) => {
    return api.get<{ tab: any }>(`/service-tabs/${reservationId}`)
  },

  addItemToTab: async (payload: {
    reservation_id: number
    items: Array<{ product_id: number; quantity: number }>
  }) => {
    return api.post<{ tab: any; message: string }>('/service-tabs', payload)
  },

  removeItemFromTab: async (reservationId: number | string, itemId: number) => {
    return api.delete<{ tab: any; message: string }>(
      `/service-tabs/${reservationId}/items/${itemId}`,
    )
  },

  settleTab: async (
    tabId: number,
    payload: {
      payment_method: 'cash' | 'wallet' | 'qr'
      cash_tendered?: number
      customer_id?: number
    },
  ) => {
    return api.post<{ tab: any; order: any; receipt: any; message: string }>(
      `/service-tabs/${tabId}/settle`,
      payload,
    )
  },

  cancelTab: async (tabId: number, reason?: string) => {
    return api.post<{ tab: any; message: string }>(`/service-tabs/${tabId}/cancel`, { reason })
  },

  getActiveReservations: async (): Promise<CourtReservation[]> => {
    try {
      const res = await api.get<{ data?: any[] } | any[]>('/reservations?status=confirmed')
      const list = Array.isArray(res) ? res : res?.data || []
      return list.map((r: any) => {
        const timeSlot = r.slots?.[0]
          ? venueClockRange(r.slots[0].starts_at, r.slots[r.slots.length - 1]?.ends_at)
          : r.time_slot || '18:00 - 20:00'

        return {
          id: r.id,
          court_name: r.court?.code || `Sân #${r.court_id || 1}`,
          court_number: r.court?.id || r.court_id || 1,
          // The contact recorded when the booking was made, never the account's current profile.
          customer_name: r.booker?.name || 'Khách vãng lai',
          phone: r.booker?.phone || '',
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
      const res = await api.get<{ customers?: any[] } | any[]>(url)
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

  // Venue credit at the counter (venue-credit spec): the served customer's balance at this organization.
  getCustomerCredit: (userId: number) =>
    api.get<{ user_id: number; balance: number }>(`/pos/venue-credits/customers/${userId}`),

  lookupCashOut: (shiftId: number, code: string) =>
    api.post<VenueCreditCashOut>(`/pos/shifts/${shiftId}/venue-credit-cash-outs/lookup`, { code }),

  // Run after the cash is handed over; the key keeps a double click to one cash-out.
  confirmCashOut: (shiftId: number, code: string, idempotencyKey: string) =>
    api.post<VenueCreditCashOut & { balance: number }>(
      `/pos/shifts/${shiftId}/venue-credit-cash-outs`,
      { code },
      { headers: { 'Idempotency-Key': idempotencyKey } },
    ),
}
