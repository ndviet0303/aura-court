import { api } from '@/core/api/client'
import type {
  MonitoredVenue,
  MonitoredVenueDetail,
  MonitoringFilters,
  Page,
  Remediation,
  RemediationDecision,
  RemediationStatus,
  Suspension,
  SuspensionPreview,
} from '../types/suspension'

const ownerPath = (organizationId: number, venueId: number) =>
  `/partner/organizations/${organizationId}/venues/${venueId}/suspensions`

const withoutEmpty = (params: Record<string, unknown>) =>
  Object.fromEntries(Object.entries(params).filter(([, value]) => value !== ''))

/** Design D13/D14: platform-admin suspension, monitoring and review; the owner's decisions and remediations. */
export const suspensionApi = {
  // Platform admin
  previewSuspension: (venueId: number) =>
    api.get<{ data: SuspensionPreview }>(`/admin/venues/${venueId}/suspensions/preview`),
  suspendVenue: (venueId: number, payload: { reason: string; acknowledge_affected: boolean }) =>
    api.post<{ message: string; data: Suspension }>(
      `/admin/venues/${venueId}/suspensions`,
      payload,
    ),
  listRemediations: (status: RemediationStatus | '', page = 1) =>
    api.get<Page<Remediation>>('/admin/venue-remediations', {
      params: { ...withoutEmpty({ status }), page },
    }),
  getRemediation: (id: number) => api.get<{ data: Remediation }>(`/admin/venue-remediations/${id}`),
  reviewRemediation: (id: number, payload: { decision: RemediationDecision; feedback: string }) =>
    api.post<{ message: string; data: Remediation }>(
      `/admin/venue-remediations/${id}/review`,
      payload,
    ),
  listMonitoredVenues: (filters: MonitoringFilters, page = 1) =>
    api.get<Page<MonitoredVenue>>('/admin/monitoring/venues', {
      params: { ...withoutEmpty(filters), page },
    }),
  getMonitoredVenue: (venueId: number) =>
    api.get<{ data: MonitoredVenueDetail }>(`/admin/monitoring/venues/${venueId}`),

  // Owner only (BR-45): platform admins read through monitoring instead.
  listVenueSuspensions: (organizationId: number, venueId: number) =>
    api.get<{ data: Suspension[] }>(ownerPath(organizationId, venueId)),
  getVenueSuspension: (organizationId: number, venueId: number, suspensionId: number) =>
    api.get<{ data: Suspension }>(`${ownerPath(organizationId, venueId)}/${suspensionId}`),
  submitRemediation: (
    organizationId: number,
    venueId: number,
    suspensionId: number,
    form: FormData,
  ) =>
    api.post<{ message: string; data: Remediation }>(
      `${ownerPath(organizationId, venueId)}/${suspensionId}/remediations`,
      form,
    ),
}
