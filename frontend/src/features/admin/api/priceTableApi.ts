import { api } from '@/core/api/client'
import type {
  CreatePriceTablePayload,
  CreatePriceTableVersionPayload,
  PriceCoverageMissingItem,
  PriceTable,
  PriceTableDetail,
  PriceTableKind,
  RetirePriceTablePayload,
} from '../types/priceTable'

export const priceTableApi = {
  list: (venueId: number) =>
    api.get<{ data: PriceTable[] }>(`/admin/venues/${venueId}/price-tables`),
  create: (venueId: number, payload: CreatePriceTablePayload) =>
    api.post<{ data: PriceTableDetail }>(`/admin/venues/${venueId}/price-tables`, payload),
  show: (tableId: number) => api.get<{ data: PriceTableDetail }>(`/admin/price-tables/${tableId}`),
  createVersion: (tableId: number, payload: CreatePriceTableVersionPayload) =>
    api.post<{ data: PriceTableDetail }>(`/admin/price-tables/${tableId}/versions`, payload),
  applyLinks: (tableId: number, payload: { court_ids: number[]; starts_at?: string }) =>
    api.post<{ data: PriceTableDetail }>(`/admin/price-tables/${tableId}/links`, payload),
  endLink: (courtId: number, payload: { kind: PriceTableKind; ends_at?: string }) =>
    api.post<{ data: { court_id: number; kind: PriceTableKind; ends_at: string } }>(
      `/admin/courts/${courtId}/price-table-links/end`,
      payload,
    ),
  rename: (tableId: number, payload: { name: string }) =>
    api.patch<{ data: PriceTableDetail }>(`/admin/price-tables/${tableId}`, payload),
  retire: (tableId: number, payload: RetirePriceTablePayload = {}) =>
    api.post<{ data: PriceTableDetail }>(`/admin/price-tables/${tableId}/retire`, payload),
  coverage: (courtId: number) =>
    api.get<{
      data: { court_id: number; covered: boolean; missing_items: PriceCoverageMissingItem[] }
    }>(`/admin/courts/${courtId}/price-coverage`),
}
