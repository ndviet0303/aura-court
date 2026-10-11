import { apiV2 } from '@/core/api/client'
import type { FinancialException, ExceptionStats, AuditLog } from '../types/exceptions'

export interface ListExceptionsParams {
  status?: string
  severity?: string
  search?: string
  per_page?: number
}

export interface ResolveExceptionPayload {
  resolution: string
  action_type?: string
}

export const financialExceptionsApi = {
  getExceptions: (params?: ListExceptionsParams) => {
    const query = new URLSearchParams()
    if (params?.status) query.set('status', params.status)
    if (params?.severity) query.set('severity', params.severity)
    if (params?.search) query.set('search', params.search)
    if (params?.per_page) query.set('per_page', String(params.per_page))

    const qs = query.toString()
    return apiV2.get<{ data: FinancialException[]; meta?: unknown }>(
      `/finance/exceptions${qs ? `?${qs}` : ''}`,
    )
  },

  getStats: () => apiV2.get<{ stats: ExceptionStats }>('/finance/exceptions/stats'),

  resolveException: (id: number | string, payload: ResolveExceptionPayload) =>
    apiV2.post<{ message: string; exception: FinancialException }>(
      `/finance/exceptions/${id}/resolve`,
      payload,
    ),

  getAuditLogs: (action?: string, perPage: number = 50) => {
    const query = new URLSearchParams()
    if (action) query.set('action', action)
    if (perPage) query.set('per_page', String(perPage))

    const qs = query.toString()
    return apiV2.get<{ data: AuditLog[] }>(`/finance/audit-logs${qs ? `?${qs}` : ''}`)
  },
}
