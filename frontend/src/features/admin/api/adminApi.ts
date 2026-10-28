import { api } from '@/core/api/client'
import type { Court, OperationalStop, Venue } from '@/features/court/types/court'
import type { CourtForm, VenueForm } from '../types/admin'
import type { LifecycleCommand, StopPayload } from '../types/lifecycle'
import type { CourtDetail } from '../types/venueDetail'
import type { VenueBookingPolicySettings, VenueBookingPolicyVersion } from '../types/admin'

export const adminApi = {
  // Venues
  getVenues: () => api.get<Venue[]>('/admin/venues'),
  // D12: the partner path is the only venue write path; an update sends the full venue information.
  createVenue: (organizationId: number, payload: VenueForm) =>
    api.post<{ data: Venue; message: string }>(
      `/partner/organizations/${organizationId}/venues`,
      payload,
    ),
  updateVenue: (organizationId: number, venueId: number, payload: VenueForm) =>
    api.put<{ data: Venue; message: string }>(
      `/partner/organizations/${organizationId}/venues/${venueId}`,
      payload,
    ),
  venueCommand: (
    organizationId: number,
    venueId: number,
    command: LifecycleCommand,
    payload?: StopPayload,
  ) =>
    api.post<{ data: Venue; stop?: OperationalStop; message: string }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/${command}`,
      payload,
    ),
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
    api.get<{ data: any }>(
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
  // Read-only detail for the owner and assigned staff.
  getPartnerVenue: (organizationId: number, venueId: number) =>
    api.get<{ data: Venue }>(`/partner/organizations/${organizationId}/venues/${venueId}`),
  getPartnerCourt: (organizationId: number, venueId: number, courtId: number) =>
    api.get<{ data: CourtDetail }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/courts/${courtId}`,
    ),
  saveScheduleException: (organizationId: number, venueId: number, payload: any) =>
    api.put<{ data: any }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/schedule-exceptions`,
      payload,
    ),
  // DELETE carries no body; the backend reads the design D7 acknowledgement from the query.
  deleteScheduleException: (
    organizationId: number,
    venueId: number,
    exceptionId: number,
    acknowledge = false,
  ) =>
    api.delete(
      `/partner/organizations/${organizationId}/venues/${venueId}/schedule-exceptions/${exceptionId}`,
      { params: acknowledge ? { acknowledge_affected: 1 } : undefined },
    ),

  // Courts
  getCourts: (venueId?: number) =>
    api.get<Court[]>(venueId ? `/admin/courts?venue_id=${venueId}` : '/admin/courts'),
  createCourt: (payload: CourtForm) => api.post<Court>('/admin/courts', payload),
  updateCourt: (id: number, payload: Partial<CourtForm>) =>
    api.patch<Court>(`/admin/courts/${id}`, payload),
  courtCommand: (courtId: number, command: LifecycleCommand, payload?: StopPayload) =>
    api.post<{ data: Court; stop?: OperationalStop; message: string }>(
      `/admin/courts/${courtId}/${command}`,
      payload,
    ),

  // Upload
  uploadImage: (formData: FormData) =>
    api.post<{ url: string; filename: string }>('/admin/upload', formData),

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
