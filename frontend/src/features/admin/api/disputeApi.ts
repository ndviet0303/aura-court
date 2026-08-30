import { apiV2 } from '@/core/api/client'
import type { DisputeCase, DisputeStats, DisputeFilterParams, ArbitrateDisputePayload } from '../types/dispute'

export const disputeApi = {
  getDisputes: async (params?: DisputeFilterParams): Promise<DisputeCase[]> => {
    const query = new URLSearchParams()
    if (params?.status && params.status !== 'all') query.set('status', params.status)
    if (params?.severity && params.severity !== 'all') query.set('severity', params.severity)
    if (params?.search) query.set('search', params.search)
    if (params?.perPage) query.set('per_page', String(params.perPage))

    const qs = query.toString()
    const res = await apiV2.get<{ data: DisputeCase[] }>(`/admin/disputes${qs ? `?${qs}` : ''}`)
    return res?.data || []
  },

  getStats: async (): Promise<DisputeStats> => {
    const res = await apiV2.get<{ stats: DisputeStats }>('/admin/disputes/stats')
    return (
      res?.stats || {
        pending_count: 0,
        investigatingCount: 0,
        awaitingReplyCount: 0,
        resolvedCount: 0,
        criticalSlaCount: 0,
        avgResolutionTimeMinutes: 0,
        consensusRate: 100,
      }
    )
  },

  getDisputeDetail: async (id: number): Promise<DisputeCase | null> => {
    const res = await apiV2.get<{ data: DisputeCase }>(`/admin/disputes/${id}`)
    return res?.data || null
  },

  arbitrateDispute: async (
    id: number,
    payload: ArbitrateDisputePayload
  ): Promise<{ message: string; data: DisputeCase }> => {
    return apiV2.post<{ message: string; data: DisputeCase }>(
      `/admin/disputes/${id}/arbitrate`,
      payload
    )
  },
}
