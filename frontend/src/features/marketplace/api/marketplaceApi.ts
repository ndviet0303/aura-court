import { apiV2 } from '@/core/api/client'
import type { ResaleListing } from '../types/marketplace'

export const marketplaceApi = {
  getListings: () => apiV2.get<ResaleListing[]>('/marketplace/listings'),

  createListing: (entitlementId: number, payload: { resale_price: number }) =>
    apiV2.post(`/marketplace/entitlements/${entitlementId}/list`, payload),

  purchaseListing: (listingId: number) =>
    apiV2.post(`/marketplace/listings/${listingId}/purchase`),

  withdrawListing: (listingId: number) =>
    apiV2.post(`/marketplace/listings/${listingId}/withdraw`),
}
