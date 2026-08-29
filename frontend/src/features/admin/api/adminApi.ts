import { api, apiV2 } from '@/core/api/client'
import type { Court, Venue, SportZone, PriceSlot, CourtBlock } from '@/features/court/types/court'
import type { Booking, Refund, Coupon } from '@/features/booking/types/booking'
import type { CourtForm, PriceForm, BlockForm, VenueForm } from '../types/admin'

export const adminApi = {
  // Venues
  getVenues: () => api.get<Venue[]>('/admin/venues'),
  createVenue: (payload: VenueForm) => api.post<Venue>('/admin/venues', payload),
  updateVenue: (id: number, payload: Partial<VenueForm>) => api.patch<Venue>(`/admin/venues/${id}`, payload),
  deleteVenue: (id: number) => api.delete(`/admin/venues/${id}`),

  // Courts
  getCourts: () => api.get<Court[]>('/admin/courts'),
  createCourt: (payload: CourtForm) => api.post<Court>('/admin/courts', payload),
  updateCourt: (id: number, payload: Partial<CourtForm>) => api.patch<Court>(`/admin/courts/${id}`, payload),
  deleteCourt: (id: number) => api.delete(`/admin/courts/${id}`),

  // Price slots
  createPrice: (courtId: number, payload: PriceForm) => api.post(`/admin/courts/${courtId}/prices`, payload),
  updatePrice: (courtId: number, priceId: number, payload: Partial<PriceForm>) =>
    api.patch(`/admin/courts/${courtId}/prices/${priceId}`, payload),
  deletePrice: (courtId: number, priceId: number) =>
    api.delete(`/admin/courts/${courtId}/prices/${priceId}`),

  // Blocks
  createBlock: (courtId: number, payload: BlockForm) => api.post(`/admin/courts/${courtId}/blocks`, payload),
  getBlocks: (courtId: number) => api.get<CourtBlock[]>(`/admin/courts/${courtId}/blocks`),
  deleteBlock: (courtId: number, blockId: number) =>
    api.delete(`/admin/courts/${courtId}/blocks/${blockId}`),

  // Bookings & Payments
  getBookings: () => api.get<Booking[]>('/admin/bookings'),
  getPayments: () => api.get<any[]>('/admin/payments'),
  getRefunds: () => api.get<Refund[]>('/admin/refunds'),
  processRefund: (refundId: number, payload: { status: string; note?: string }) =>
    api.patch(`/admin/refunds/${refundId}`, payload),

  // Coupons
  getCoupons: () => api.get<Coupon[]>('/admin/coupons'),
  createCoupon: (payload: any) => api.post<Coupon>('/admin/coupons', payload),

  // Reports
  getReports: () => api.get<any>('/admin/reports'),
}
