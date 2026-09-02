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

  completeOrder: async (payload: {
    items: Array<{ product_id: number; quantity: number }>
    customer_id?: number | null
    reservation_id?: number | null
    payment_method: 'cash' | 'qr' | 'wallet'
    payment_reference?: string
    discount_amount?: number
    offline?: boolean
  }) => {
    const res = await apiV2.post<{ order: any } | any>('/pos/orders', payload)
    return res?.order || res
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

  getActiveReservations: async (): Promise<CourtReservation[]> => {
    try {
      const res = await api.get<{ data?: any[] } | any[]>('/admin/bookings?status=confirmed')
      const list = Array.isArray(res) ? res : res?.data || []
      return list.map((b: any) => {
        let courtName = b.court?.name
        if (typeof courtName === 'object' && courtName !== null) {
          courtName = courtName.vi || courtName.en || Object.values(courtName)[0]
        }
        let customerName = b.user?.name || b.customer_name
        if (typeof customerName === 'object' && customerName !== null) {
          customerName = customerName.vi || customerName.en || Object.values(customerName)[0]
        }
        return {
          id: b.id,
          court_name: (courtName as string) || `Sân #${b.court_id}`,
          court_number: b.court?.id || b.court_id || 1,
          customer_name: (customerName as string) || 'Khách vãng lai',
          phone: b.user?.phone || b.phone || '',
          status: b.status === 'confirmed' ? 'playing' : 'waiting',
          time_slot: b.time_slot || `${b.start_time || '18:00'} - ${b.end_time || '20:00'}`,
          deposit_paid: b.deposit_paid ?? b.total_amount ?? b.total_price ?? 0,
        }
      })
    } catch {
      return []
    }
  },

  searchCustomers: async (query?: string): Promise<CustomerProfile[]> => {
    try {
      const orgsRes = await apiV2.get<{ organizations?: any[] } | any[]>('/organizations')
      const orgs = Array.isArray(orgsRes) ? orgsRes : (orgsRes as any)?.organizations || []
      const orgId = orgs[0]?.id || 2
      const res = await apiV2.get<{ members?: any[] } | any[]>(`/organizations/${orgId}/members`)
      const list = Array.isArray(res) ? res : (res as any)?.members || []
      
      const q = query ? query.toLowerCase().trim() : ''
      return list
        .filter((m: any) => {
          if (!q) return true
          const name = (m.user?.name || m.name || '').toLowerCase()
          const phone = (m.user?.phone || m.phone || '').toLowerCase()
          const email = (m.user?.email || m.email || '').toLowerCase()
          return name.includes(q) || phone.includes(q) || email.includes(q)
        })
        .map((m: any) => {
          const isOwner = m.role === 'organization_owner'
          return {
            id: m.user?.id || m.user_id,
            name: m.user?.name || m.name || 'Hội viên',
            phone: m.user?.phone || m.phone || '0901234567',
            tier: isOwner ? 'VIP' : (m.tier || 'Gold'),
            discount_rate: isOwner ? 0.2 : 0.1,
            wallet_balance: m.wallet_balance ?? 500000,
            loyalty_points: m.loyalty_points ?? 250,
          }
        })
    } catch {
      return []
    }
  },
}
