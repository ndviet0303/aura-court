import { apiV2 } from '@/core/api/client'
import type { HostKyc, KycStats, KycFilterParams, ReviewKycPayload } from '../types/kyc'

export const adminKycApi = {
  getQueue: async (params?: KycFilterParams): Promise<{ data: HostKyc[] }> => {
    const query = new URLSearchParams()
    if (params?.status && params.status !== 'all') query.set('status', params.status)
    if (params?.search) query.set('search', params.search)
    if (params?.perPage) query.set('per_page', String(params.perPage))

    const qs = query.toString()
    const res = await apiV2.get<{ data: HostKyc[] }>(`/admin/kyc${qs ? `?${qs}` : ''}`)
    return res
  },

  getStats: async (): Promise<{ stats: KycStats }> => {
    const res = await apiV2.get<{ stats: KycStats }>('/admin/kyc/stats')
    return res
  },

  getDetail: async (id: number): Promise<{ data: HostKyc }> => {
    return apiV2.get<{ data: HostKyc }>(`/admin/kyc/${id}`)
  },

  reviewKyc: async (
    id: number,
    payload: ReviewKycPayload
  ): Promise<{ message: string; data: HostKyc }> => {
    return apiV2.post<{ message: string; data: HostKyc }>(`/admin/kyc/${id}/review`, payload)
  },
}
