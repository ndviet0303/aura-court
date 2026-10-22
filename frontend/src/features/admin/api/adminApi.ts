import { api } from '@/core/api/client'
import type { Court, Venue, CourtBlock } from '@/features/court/types/court'
import type { CourtForm, BlockForm, VenueForm } from '../types/admin'
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

  // Logs
  getAuditLogs: <T = unknown>() => api.get<T>('/admin/audit-logs'),

  // Organizations
  getOrganizations: <T = unknown>() => api.get<T>('/organizations'),
  getOrganization: <T = unknown>(orgId: number | string) => api.get<T>(`/organizations/${orgId}`),
  updateOrganization: <T = unknown>(orgId: number | string, payload: any) =>
    api.patch<T>(`/organizations/${orgId}`, payload),

  // Organization Members
  getOrgMembers: <T = any[]>(orgId: number | string = 1) =>
    api.get<T>(`/organizations/${orgId}/members`),
  updateOrgMember: <T = any>(
    orgId: number | string,
    memberId: number | string,
    payload: { status?: string; venue_ids?: number[] },
  ) => api.patch<T>(`/organizations/${orgId}/members/${memberId}`, payload),
  removeOrgMember: (orgId: number | string, memberId: number | string) =>
    api.delete<{ message?: string }>(`/organizations/${orgId}/members/${memberId}`),

  // Organization Invitations
  getOrgInvitations: <T = { invitations: any[] }>(orgId: number | string = 1, status?: string) =>
    api.get<T>(`/organizations/${orgId}/invitations`, { params: status ? { status } : undefined }),
  createOrgInvitation: <T = any>(
    orgId: number | string,
    payload: { email: string; venue_ids: number[] },
  ) => api.post<T>(`/organizations/${orgId}/invitations`, payload),
  revokeOrgInvitation: <T = any>(orgId: number | string, invitationId: number | string) =>
    api.post<T>(`/organizations/${orgId}/invitations/${invitationId}/revoke`),

  quotePrice: <T = { quote: any }>(payload: { court_id: number; slots: string[] }) =>
    api.post<T>('/price-quotes', payload),
}
