import { apiV2 } from '@/core/api/client'
import type { PurchaseListingPayload, CreateListingPayload } from '../types/marketplace'

export const marketplaceApi = {
  getListings: <T = { data: any[] }>() =>
    apiV2.get<T>('/marketplace/listings'),

  purchaseListing: <T = unknown>(listingId: number | string, payload: PurchaseListingPayload) =>
    apiV2.post<T>(`/marketplace/listings/${listingId}/purchase`, payload),

  listEntitlement: (entitlementId: number | string, payload: CreateListingPayload) =>
    apiV2.post(`/marketplace/entitlements/${entitlementId}/list`, payload),

  withdrawListing: (listingId: number | string) =>
    apiV2.post(`/marketplace/listings/${listingId}/withdraw`),
}
