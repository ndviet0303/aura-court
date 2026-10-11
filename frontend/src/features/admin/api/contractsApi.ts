import { api } from '@/core/api/client'
import type { ContractsApiResponse } from '../types/contract'

export const contractsApi = {
  list: (params?: {
    status?: string
    search?: string
    venue_id?: number
    page?: number
    per_page?: number
  }) => api.get<ContractsApiResponse>('/admin/contracts', { params }),

  getById: (id: number | string) => api.get<any>(`/admin/contracts/${id}`),

  cancel: (id: number | string, reason?: string) =>
    api.post<any>(`/admin/contracts/${id}/cancel`, { reason }),

  getVenues: () => api.get<any[]>('/admin/venues'),
}
