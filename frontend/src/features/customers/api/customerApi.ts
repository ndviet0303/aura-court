import { api } from '@/core/api/client'
import type { VenueCustomerPage, VenueCustomerProfile } from '../types/customer'

// FR-110 runs in the venue's organization; the API checks the role there and the venue (BR-140).
const org = (organizationId: number, params?: Record<string, string | number | undefined>) => ({
  headers: { 'X-Organization-ID': String(organizationId) },
  params,
})

export const customerApi = {
  list: (organizationId: number, venueId: number, quarter: string, search: string, page: number) =>
    api.get<VenueCustomerPage>(
      `/operations/venues/${venueId}/customers`,
      org(organizationId, { quarter, search: search || undefined, page }),
    ),
  show: (organizationId: number, venueId: number, userId: number, quarter: string) =>
    api.get<VenueCustomerProfile>(
      `/operations/venues/${venueId}/customers/${userId}`,
      org(organizationId, { quarter }),
    ),
}
