import { api } from '@/core/api/client'
import type {
  ApiResaleListing,
  CreateListingPayload,
  ResaleSourceReservation,
  ResaleState,
} from '../types/marketplace'

const listing = (id: number) => `/marketplace/listings/${id}`

export const marketplaceApi = {
  getListings: (params?: { mine?: boolean; page?: number }) =>
    api.get<{ data: ApiResaleListing[] }>('/marketplace/listings', {
      params: { mine: params?.mine ? 1 : undefined, page: params?.page },
    }),

  listEntitlement: (entitlementId: number, payload: CreateListingPayload) =>
    api.post<{ listing: ApiResaleListing }>(
      `/marketplace/entitlements/${entitlementId}/list`,
      payload,
    ),

  withdrawListing: (listingId: number) =>
    api.post<{ listing: ApiResaleListing }>(`${listing(listingId)}/withdraw`),

  // Peer-to-peer lock: the buyer pays the seller directly; the seller confirms receipt.
  getListingState: (listingId: number) => api.get<ResaleState>(listing(listingId)),

  lockListing: (listingId: number, acceptFreeTransfer = false) =>
    api.post<ResaleState>(
      `${listing(listingId)}/checkout`,
      acceptFreeTransfer ? { accept_free_transfer: true } : {},
    ),

  reportPaymentSent: (listingId: number) =>
    api.post<ResaleState>(`${listing(listingId)}/payment-sent`),

  releaseLock: (listingId: number, reason?: string) =>
    api.post<ResaleState>(`${listing(listingId)}/release`, reason ? { reason } : {}),

  confirmReceipt: (listingId: number) =>
    api.post<ResaleState>(`${listing(listingId)}/confirm-receipt`),

  openPaymentDispute: (listingId: number, title: string, summary: string) =>
    api.post<unknown>('/disputes', {
      title,
      summary,
      dispute_type: 'payment_issue',
      reference_type: 'marketplace_listing',
      reference_id: listingId,
    }),

  getUserReservations: () => api.get<{ data: ResaleSourceReservation[] }>('/reservations'),
}
