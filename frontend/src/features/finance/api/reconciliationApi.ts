import { api } from '@/core/api/client'
import type {
  ReconciliationStats,
  ReconciliationRun,
  ReconciliationDetail,
  ReconciliationFilterParams,
} from '../types/reconciliation'

export const reconciliationApi = {
  getStats: () => api.get<ReconciliationStats>('/finance/reconciliation/stats'),

  getRuns: (params?: ReconciliationFilterParams) =>
    api.get<{ data: ReconciliationRun[]; meta?: any }>('/finance/reconciliation/runs', {
      params,
    }),

  getRunDetail: (id: number | string) =>
    api.get<ReconciliationDetail>(`/finance/reconciliation/runs/${id}`),

  triggerRun: (payload?: { run_date?: string; force?: boolean }) =>
    api.post<ReconciliationRun>('/finance/reconciliation/trigger', payload || {}),

  autoResolve: (payload?: { run_id?: number; scope?: string; notes?: string }) =>
    api.post<{ success: boolean; resolved_count: number; message: string }>(
      '/finance/reconciliation/auto-resolve',
      payload || {},
    ),
}
