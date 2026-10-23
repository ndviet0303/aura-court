import { api } from '@/core/api/client'
import type {
  ContentReport,
  ContentReportDetail,
  ContentReportPage,
  ModerationCommand,
  ReportFilters,
} from '../types/moderation'

// FR-132: platform admin only.
export const moderationApi = {
  list: (filters: ReportFilters, page: number) =>
    api.get<ContentReportPage>('/admin/content-reports', {
      params: { status: filters.status, type: filters.type || undefined, page },
    }),
  show: (id: number) => api.get<ContentReportDetail>(`/admin/content-reports/${id}`),
  act: (id: number, command: ModerationCommand, note: string) =>
    api.post<{ report: ContentReport }>(`/admin/content-reports/${id}/${command}`, { note }),
}
