import { api } from '@/core/api/client'
import type { Court, Venue } from '@/features/court/types/court'
import type {
  OwnerFinanceQuery,
  OwnerFinanceReport,
  OwnerFinanceTransactionPage,
} from '../types/ownerFinance'

const finance = (organizationId: number) => `/partner/organizations/${organizationId}/finance`

// D12: the owner's transactions and report; the venue and court lists only feed the filters.
export const ownerFinanceApi = {
  getTransactions: (organizationId: number, params: OwnerFinanceQuery) =>
    api.get<OwnerFinanceTransactionPage>(`${finance(organizationId)}/transactions`, { params }),

  getReport: (organizationId: number, params: OwnerFinanceQuery) =>
    api.get<{ data: OwnerFinanceReport }>(`${finance(organizationId)}/report`, { params }),

  getVenues: () => api.get<Venue[]>('/admin/venues'),

  getCourts: (venueId: number) =>
    api.get<Court[]>('/admin/courts', { params: { venue_id: venueId } }),
}
