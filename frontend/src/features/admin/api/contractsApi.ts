import { api } from '@/core/api/client'
import type { ContractsApiResponse, CreateContractPayload } from '../types/contract'

export const contractsApi = {
  list: (params?: { status?: string; search?: string; venue_id?: number; page?: number; per_page?: number }) =>
    api.get<ContractsApiResponse>('/admin/contracts', { params }),

  getById: (id: number | string) =>
    api.get<any>(`/admin/contracts/${id}`),

  create: (payload: CreateContractPayload) =>
    api.post<any>('/admin/contracts', payload),

  cancel: (id: number | string, reason?: string) =>
    api.post<any>(`/admin/contracts/${id}/cancel`, { reason }),

  payInvoice: (seriesId: number | string, invoiceId: number | string, paymentReference?: string) =>
    api.post<any>(`/admin/contracts/${seriesId}/invoices/${invoiceId}/pay`, { payment_reference: paymentReference }),

  getVenues: () =>
    api.get<any[]>('/admin/venues'),

  getCourts: (params?: { venue_id?: number }) =>
    api.get<any[]>('/courts', { params }),
}
