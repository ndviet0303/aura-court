import { apiV2 } from '@/core/api/client'
import type {
  ReconciliationStats,
  ReconciliationRun,
  ReconciliationDetail,
  ReconciliationFilterParams,
} from '../types/reconciliation'

export const reconciliationApi = {
  getStats: () => apiV2.get<ReconciliationStats>('/finance/reconciliation/stats'),

  getRuns: (params?: ReconciliationFilterParams) =>
    apiV2.get<{ data: ReconciliationRun[]; meta?: any }>('/finance/reconciliation/runs', {
      params,
    }),

  getRunDetail: (id: number | string) =>
    apiV2.get<ReconciliationDetail>(`/finance/reconciliation/runs/${id}`),

  triggerRun: (payload?: { run_date?: string; force?: boolean }) =>
    apiV2.post<ReconciliationRun>('/finance/reconciliation/trigger', payload || {}),

  autoResolve: (payload?: { run_id?: number; scope?: string; notes?: string }) =>
    apiV2.post<{ success: boolean; resolved_count: number; message: string }>(
      '/finance/reconciliation/auto-resolve',
      payload || {}
    ),
}
