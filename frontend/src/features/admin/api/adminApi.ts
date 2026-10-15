import { api } from '@/core/api/client'
import type { Court, Venue, SportZone, PriceSlot, CourtBlock } from '@/features/court/types/court'
import type { Coupon } from '@/features/booking/types/booking'
import type { CourtForm, PriceForm, BlockForm, VenueForm } from '../types/admin'
import type { VenueBookingPolicySettings, VenueBookingPolicyVersion } from '../types/admin'

export const adminApi = {
  // Venues
  getVenues: () => api.get<Venue[]>('/admin/venues'),
  createVenue: (payload: VenueForm) => api.post<Venue>('/admin/venues', payload),
  updateVenue: (id: number, payload: Partial<VenueForm> | { is_active?: boolean }) =>
    api.patch<Venue>(`/admin/venues/${id}`, payload),
  deleteVenue: (id: number) => api.delete(`/admin/venues/${id}`),
  getVenueBookingPolicy: (organizationId: number, venueId: number) =>
    api.get<{ data: VenueBookingPolicyVersion | null }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/booking-policy`,
    ),
  saveVenueBookingPolicy: (
    organizationId: number,
    venueId: number,
    settings: VenueBookingPolicySettings,
  ) =>
    api.put<{ data: VenueBookingPolicyVersion }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/booking-policy`,
      { settings },
    ),
  getOperatingSchedule: (organizationId: number, venueId: number, courtId?: number) =>
    api.get<{ data: any; inherited?: boolean }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/operating-schedule`,
      { params: courtId ? { court_id: courtId } : undefined },
    ),
  saveOperatingSchedule: (organizationId: number, venueId: number, payload: any) =>
    api.put<{ data: any }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/operating-schedule`,
      payload,
    ),
  getScheduleExceptions: (organizationId: number, venueId: number, courtId?: number) =>
    api.get<{ data: any[] }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/schedule-exceptions`,
      { params: courtId ? { court_id: courtId } : undefined },
    ),
  saveScheduleException: (organizationId: number, venueId: number, payload: any) =>
    api.put<{ data: any }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/schedule-exceptions`,
      payload,
    ),
  deleteScheduleException: (organizationId: number, venueId: number, exceptionId: number) =>
    api.delete(
      `/partner/organizations/${organizationId}/venues/${venueId}/schedule-exceptions/${exceptionId}`,
    ),

  // Sport Zones
  getSportZones: (venueId?: number) =>
    api.get<SportZone[]>(`/admin/sport-zones${venueId ? `?venue_id=${venueId}` : ''}`),
  createSportZone: (payload: any) => api.post<SportZone>('/admin/sport-zones', payload),
  updateSportZone: (id: number, payload: any) =>
    api.patch<SportZone>(`/admin/sport-zones/${id}`, payload),
  deleteSportZone: (id: number) => api.delete(`/admin/sport-zones/${id}`),

  // Courts
  getCourts: (venueId?: number) =>
    api.get<Court[]>(venueId ? `/admin/courts?venue_id=${venueId}` : '/admin/courts'),
  createCourt: (payload: CourtForm) => api.post<Court>('/admin/courts', payload),
  updateCourt: (id: number, payload: Partial<CourtForm>) =>
    api.patch<Court>(`/admin/courts/${id}`, payload),
  deleteCourt: (id: number) => api.delete(`/admin/courts/${id}`),

  // Upload
  uploadImage: (formData: FormData) =>
    api.post<{ url: string; filename: string }>('/admin/upload', formData),

  // Price slots
  getCourtPrices: (courtId: number) => api.get<PriceSlot[]>(`/admin/courts/${courtId}/prices`),
  createPrice: (courtId: number, payload: PriceForm) =>
    api.post(`/admin/courts/${courtId}/prices`, payload),
  bulkCreatePrices: <T = { message: string; slots?: PriceSlot[] }>(courtId: number, payload: any) =>
    api.post<T>(`/admin/courts/${courtId}/prices/bulk`, payload),
  updatePrice: (
    courtId: number,
    priceId: number,
    payload: Partial<PriceForm> | { price: number },
  ) => api.patch(`/admin/courts/${courtId}/prices/${priceId}`, payload),
  deletePrice: (courtId: number, priceId: number) =>
    api.delete(`/admin/courts/${courtId}/prices/${priceId}`),

  // Blocks
  createBlock: (courtId: number, payload: BlockForm) =>
    api.post(`/admin/courts/${courtId}/blocks`, payload),
  getBlocks: (courtId: number) => api.get<CourtBlock[]>(`/admin/courts/${courtId}/blocks`),
  deleteBlock: (courtId: number, blockId: number) =>
    api.delete(`/admin/courts/${courtId}/blocks/${blockId}`),

  // Reservations & Payments
  getReservations: <T = unknown>(params?: {
    status?: string
    date?: string
    venue_id?: number
    page?: number
  }) => api.get<T>('/admin/reservations', { params }),
  getPayments: <T = any>() => api.get<T>('/admin/payments'),

  // Coupons
  getCoupons: <T = Coupon[]>(organizationId?: number) =>
    api.get<T>(`/admin/coupons${organizationId ? `?organization_id=${organizationId}` : ''}`),
  createCoupon: (payload: Record<string, unknown>) => api.post<Coupon>('/admin/coupons', payload),
  updateCoupon: (id: number, payload: Record<string, unknown>) =>
    api.patch<{ coupon: Coupon }>(`/admin/coupons/${id}`, payload),
  transitionCoupon: (
    id: number,
    action: 'activate' | 'suspend' | 'retire',
    organizationId?: number,
  ) =>
    api.post<{ coupon: Coupon }>(`/admin/coupons/${id}/${action}`, {
      organization_id: organizationId,
    }),
  getPromotionCampaigns: <T = unknown>(organizationId?: number) =>
    api.get<T>(`/admin/promotions${organizationId ? `?organization_id=${organizationId}` : ''}`),
  createPromotionCampaign: (payload: Record<string, unknown>) =>
    api.post('/admin/promotions', payload),
  updatePromotionCampaign: (id: number, payload: Record<string, unknown>) =>
    api.patch(`/admin/promotions/${id}`, payload),
  transitionPromotionCampaign: (
    id: number,
    action: 'activate' | 'suspend' | 'retire',
    organizationId?: number,
  ) => api.post(`/admin/promotions/${id}/${action}`, { organization_id: organizationId }),

  // Reports & Logs
  getReports: <T = any>(query?: string) => api.get<T>(`/admin/reports${query ? `?${query}` : ''}`),
  getAuditLogs: <T = unknown>() => api.get<T>('/admin/audit-logs'),

  // Organizations
  getOrganizations: <T = unknown>() => api.get<T>('/organizations'),
  getOrganization: <T = unknown>(orgId: number | string) => api.get<T>(`/organizations/${orgId}`),
  updateOrganization: <T = unknown>(orgId: number | string, payload: any) =>
    api.patch<T>(`/organizations/${orgId}`, payload),

  // Organization Members
  getOrgMembers: <T = any[]>(orgId: number | string = 1) =>
    api.get<T>(`/organizations/${orgId}/members`),
  updateOrgMember: <T = any>(orgId: number | string = 1, memberId: number | string, payload: any) =>
    api.patch<T>(`/organizations/${orgId}/members/${memberId}`, payload),
  removeOrgMember: (orgId: number | string = 1, memberId: number | string) =>
    api.delete(`/organizations/${orgId}/members/${memberId}`),

  // Organization Invitations
  getOrgInvitations: <T = { invitations: any[] }>(orgId: number | string = 1, status?: string) =>
    api.get<T>(`/organizations/${orgId}/invitations`, { params: status ? { status } : undefined }),
  createOrgInvitation: <T = any>(orgId: number | string = 1, payload: any) =>
    api.post<T>(`/organizations/${orgId}/invitations`, payload),
  revokeOrgInvitation: <T = any>(orgId: number | string, invitationId: number | string) =>
    api.post<T>(`/organizations/${orgId}/invitations/${invitationId}/revoke`),

  // Pricing Rules Calendar
  getPriceRules: <T = { rules: any[] }>(params?: Record<string, any>) =>
    api.get<T>('/price-rules', { params }),
  getPricingCalendar: <T = { rules: any[] }>(params?: Record<string, any>) =>
    api.get<T>('/pricing/calendar', { params }),
  createPriceRule: <T = { rule: any }>(payload: any) => api.post<T>('/price-rules', payload),
  updatePriceRule: <T = { rule: any }>(id: number | string, payload: any) =>
    api.patch<T>(`/price-rules/${id}`, payload),
  deletePriceRule: (id: number | string) => api.delete(`/price-rules/${id}`),
  quotePrice: <T = { quote: any }>(payload: { court_id: number; slots: string[] }) =>
    api.post<T>('/price-quotes', payload),
}
