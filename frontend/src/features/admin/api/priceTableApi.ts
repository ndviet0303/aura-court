import { api } from '@/core/api/client'
import type {
  CreatePriceTablePayload,
  PriceTable,
  PriceTableDetail,
  PriceTableKind,
} from '../types/priceTable'

export const priceTableApi = {
  list: (venueId: number) =>
    api.get<{ data: PriceTable[] }>(`/admin/venues/${venueId}/price-tables`),
  create: (venueId: number, payload: CreatePriceTablePayload) =>
    api.post<{ data: PriceTableDetail }>(`/admin/venues/${venueId}/price-tables`, payload),
  show: (tableId: number) => api.get<{ data: PriceTableDetail }>(`/admin/price-tables/${tableId}`),
  applyLinks: (tableId: number, payload: { court_ids: number[]; starts_at?: string }) =>
    api.post<{ data: PriceTableDetail }>(`/admin/price-tables/${tableId}/links`, payload),
  endLink: (courtId: number, payload: { kind: PriceTableKind; ends_at?: string }) =>
    api.post<{ data: { court_id: number; kind: PriceTableKind; ends_at: string } }>(
      `/admin/courts/${courtId}/price-table-links/end`,
      payload,
    ),
  retire: (tableId: number) =>
    api.post<{ data: PriceTableDetail }>(`/admin/price-tables/${tableId}/retire`),
}
