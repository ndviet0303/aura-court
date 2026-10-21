import { api } from '@/core/api/client'
import type {
  PackagePrice,
  PackageTermPrice,
  SchedulePackagePricePayload,
} from '../types/platformFee'

// D3: platform admins read the four package terms and schedule a term's price.
export const platformFeeApi = {
  getPrices: () => api.get<{ data: PackageTermPrice[] }>('/admin/platform-fees/prices'),

  schedulePrice: (payload: SchedulePackagePricePayload) =>
    api.post<{ data: PackagePrice; message: string }>('/admin/platform-fees/prices', payload),
}
