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

  // Owner-collected hold: the buyer pays the venue owner's QR; the verified receipt transfers the slot.
  getListingState: (listingId: number) => api.get<ResaleState>(listing(listingId)),

  lockListing: (listingId: number, acceptFreeTransfer = false) =>
    api.post<ResaleState>(
      `${listing(listingId)}/checkout`,
      acceptFreeTransfer ? { accept_free_transfer: true } : {},
    ),

  releaseLock: (listingId: number) => api.post<ResaleState>(`${listing(listingId)}/release`),

  getUserReservations: () => api.get<{ data: ResaleSourceReservation[] }>('/reservations'),
}
