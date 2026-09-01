import { api, apiV2 } from '@/core/api/client'
import type { Court, Venue, SportZone, PriceSlot, CourtBlock } from '@/features/court/types/court'
import type { Booking, Refund, Coupon } from '@/features/booking/types/booking'
import type { CourtForm, PriceForm, BlockForm, VenueForm } from '../types/admin'

export const adminApi = {
  // Venues
  getVenues: () => api.get<Venue[]>('/admin/venues'),
  createVenue: (payload: VenueForm) => api.post<Venue>('/admin/venues', payload),
  updateVenue: (id: number, payload: Partial<VenueForm> | { is_active?: boolean }) =>
    api.patch<Venue>(`/admin/venues/${id}`, payload),
  deleteVenue: (id: number) => api.delete(`/admin/venues/${id}`),

  // Sport Zones
  getSportZones: (venueId?: number) =>
    api.get<SportZone[]>(`/admin/sport-zones${venueId ? `?venue_id=${venueId}` : ''}`),
  createSportZone: (payload: any) => api.post<SportZone>('/admin/sport-zones', payload),
  updateSportZone: (id: number, payload: any) => api.patch<SportZone>(`/admin/sport-zones/${id}`, payload),
  deleteSportZone: (id: number) => api.delete(`/admin/sport-zones/${id}`),

  // Courts
  getCourts: () => api.get<Court[]>('/admin/courts'),
  createCourt: (payload: CourtForm) => api.post<Court>('/admin/courts', payload),
  updateCourt: (id: number, payload: Partial<CourtForm>) => api.patch<Court>(`/admin/courts/${id}`, payload),
  deleteCourt: (id: number) => api.delete(`/admin/courts/${id}`),

  // Upload
  uploadImage: (formData: FormData) =>
    api.post<{ url: string; filename: string }>('/admin/upload', formData),

  // Price slots
  createPrice: (courtId: number, payload: PriceForm) => api.post(`/admin/courts/${courtId}/prices`, payload),
  bulkCreatePrices: <T = { message: string; slots?: PriceSlot[] }>(courtId: number, payload: any) =>
    api.post<T>(`/admin/courts/${courtId}/prices/bulk`, payload),
  updatePrice: (courtId: number, priceId: number, payload: Partial<PriceForm> | { price: number }) =>
    api.patch(`/admin/courts/${courtId}/prices/${priceId}`, payload),
  deletePrice: (courtId: number, priceId: number) =>
    api.delete(`/admin/courts/${courtId}/prices/${priceId}`),

  // Blocks
  createBlock: (courtId: number, payload: BlockForm) => api.post(`/admin/courts/${courtId}/blocks`, payload),
  getBlocks: (courtId: number) => api.get<CourtBlock[]>(`/admin/courts/${courtId}/blocks`),
  deleteBlock: (courtId: number, blockId: number) =>
    api.delete(`/admin/courts/${courtId}/blocks/${blockId}`),

  // Bookings & Payments
  getBookings: <T = { data: Booking[] } | Booking[]>(query?: string) =>
    api.get<T>(`/admin/bookings${query ? `?${query}` : ''}`),
  getPayments: <T = any>() => api.get<T>('/admin/payments'),
  updatePayment: (paymentId: number | string, payload: { status: string }) =>
    api.patch(`/admin/payments/${paymentId}`, payload),
  markNoShow: (bookingId: number | string) =>
    api.patch(`/admin/bookings/${bookingId}/no-show`, {}),
  getRefunds: <T = Refund[]>() => api.get<T>('/admin/refunds'),
  processRefund: (refundId: number, payload: { status: string; note?: string }) =>
    api.patch(`/admin/refunds/${refundId}`, payload),

  // Coupons
  getCoupons: <T = Coupon[]>() => api.get<T>('/admin/coupons'),
  createCoupon: (payload: any) => api.post<Coupon>('/admin/coupons', payload),

  // Reports & Logs
  getReports: <T = any>(query?: string) =>
    api.get<T>(`/admin/reports${query ? `?${query}` : ''}`),
  getAuditLogs: <T = unknown>() => api.get<T>('/admin/audit-logs'),

  // Organizations
  getOrganizations: <T = unknown>() =>
    api.get<T>('/organizations'),
  updateOrganization: <T = unknown>(orgId: number | string, payload: any) =>
    api.patch<T>(`/organizations/${orgId}`, payload),

  // Organization Members
  getOrgMembers: <T = any[]>(orgId: number | string = 1) =>
    api.get<T>(`/organizations/${orgId}/members`),
  addOrgMember: <T = any>(orgId: number | string = 1, payload: any) =>
    api.post<T>(`/organizations/${orgId}/members`, payload),
  updateOrgMember: <T = any>(orgId: number | string = 1, memberId: number | string, payload: any) =>
    api.patch<T>(`/organizations/${orgId}/members/${memberId}`, payload),
  removeOrgMember: (orgId: number | string = 1, memberId: number | string) =>
    api.delete(`/organizations/${orgId}/members/${memberId}`),

  // Pricing Rules Calendar (V2)
  getPriceRules: <T = { rules: any[] }>(params?: Record<string, any>) =>
    apiV2.get<T>('/price-rules', { params }),
  getPricingCalendar: <T = { rules: any[] }>(params?: Record<string, any>) =>
    apiV2.get<T>('/pricing/calendar', { params }),
  createPriceRule: <T = { rule: any }>(payload: any) =>
    apiV2.post<T>('/price-rules', payload),
  updatePriceRule: <T = { rule: any }>(id: number | string, payload: any) =>
    apiV2.patch<T>(`/price-rules/${id}`, payload),
  deletePriceRule: (id: number | string) =>
    apiV2.delete(`/price-rules/${id}`),
  quotePrice: <T = { quote: any }>(payload: { court_id: number; slots: string[] }) =>
    apiV2.post<T>('/price-quotes', payload),
}
