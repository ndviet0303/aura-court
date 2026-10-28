import { api } from '@/core/api/client'
import type {
  PackagePrice,
  PackageTermPrice,
  PlatformFeePage,
  PlatformFeeReport,
  PlatformFeeReportQuery,
  PlatformFeeRequest,
  PlatformFeeRequestQuery,
  PlatformFeeVenue,
  PlatformFeeVenuePage,
  PlatformFeeVenueQuery,
  SchedulePackagePricePayload,
} from '../types/platformFee'

// D3, D15: platform admins set package prices and read payment requests, venue package status and the fee report.
export const platformFeeApi = {
  getPrices: () => api.get<{ data: PackageTermPrice[] }>('/admin/platform-fees/prices'),

  schedulePrice: (payload: SchedulePackagePricePayload) =>
    api.post<{ data: PackagePrice; message: string; notification_failed: boolean }>(
      '/admin/platform-fees/prices',
      payload,
    ),

  getRequests: (params: PlatformFeeRequestQuery) =>
    api.get<PlatformFeePage<PlatformFeeRequest>>('/admin/platform-fees/requests', { params }),

  exportRequests: (params: PlatformFeeRequestQuery) =>
    api.download('/admin/platform-fees/requests/export', 'yeu-cau-thanh-toan-goi.xlsx', { params }),

  getRequest: (code: string) =>
    api.get<{ data: PlatformFeeRequest }>(
      `/admin/platform-fees/requests/${encodeURIComponent(code)}`,
    ),

  getVenues: (params: PlatformFeeVenueQuery) =>
    api.get<PlatformFeeVenuePage>('/admin/platform-fees/venues', { params }),

  exportVenues: (params: PlatformFeeVenueQuery) =>
    api.download('/admin/platform-fees/venues/export', 'tinh-trang-goi.xlsx', { params }),

  getVenue: (venueId: number) =>
    api.get<{ data: PlatformFeeVenue }>(`/admin/platform-fees/venues/${venueId}`),

  getReport: (params: PlatformFeeReportQuery) =>
    api.get<{ data: PlatformFeeReport }>('/admin/platform-fees/report', { params }),

  exportReport: (params: PlatformFeeReportQuery) =>
    api.download(
      '/admin/platform-fees/report/export',
      `bao-cao-thu-phi-${params.from}-${params.to}.xlsx`,
      { params },
    ),
}
