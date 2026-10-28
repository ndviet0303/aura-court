import { api } from '@/core/api/client'
import type {
  BookingTransfer,
  CreateTransferPayload,
  PricedItem,
  TransferCandidate,
  TransferListing,
  TransferPage,
} from '../types/transfer'

// D10: the marketplace is shared by every organization, so these calls carry no organization header.
export const transferApi = {
  candidates: () => api.get<{ data: TransferCandidate[] }>('/booking-transfers/candidates'),
  listings: (q: string, page: number) =>
    api.get<TransferPage<TransferListing>>('/booking-transfers/listings', {
      params: { q: q || undefined, page },
    }),
  listing: (id: number) =>
    api.get<{ listing: TransferListing }>(`/booking-transfers/listings/${id}`),
  createListing: (note: string, items: PricedItem[]) =>
    api.post<{ listing: TransferListing }>('/booking-transfers/listings', {
      note: note || undefined,
      items,
    }),
  withdraw: (id: number, reservationIds?: number[]) =>
    api.post<{ listing: TransferListing }>(`/booking-transfers/listings/${id}/withdraw`, {
      reservation_ids: reservationIds,
    }),
  contact: (id: number, reservationIds: number[]) =>
    api.post<{ conversation_id: number }>(`/booking-transfers/listings/${id}/contact`, {
      reservation_ids: reservationIds,
    }),
  create: (payload: CreateTransferPayload) =>
    api.post<{ transfer: BookingTransfer }>('/booking-transfers', payload),
  transfers: () => api.get<{ data: BookingTransfer[] }>('/booking-transfers'),
  transfer: (id: number) => api.get<{ transfer: BookingTransfer }>(`/booking-transfers/${id}`),
  confirm: (id: number) =>
    api.post<{ transfer: BookingTransfer }>(`/booking-transfers/${id}/confirm`),
  cancel: (id: number) =>
    api.post<{ transfer: BookingTransfer }>(`/booking-transfers/${id}/cancel`),
}
