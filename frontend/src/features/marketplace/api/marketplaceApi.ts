import { apiV2 } from '@/core/api/client'
import type { PurchaseListingPayload, CreateListingPayload } from '../types/marketplace'

export const marketplaceApi = {
  getListings: <T = any>(params?: { mine?: boolean; page?: number }) => {
    const query = new URLSearchParams()
    if (params?.mine) query.append('mine', '1')
    if (params?.page) query.append('page', String(params.page))
    const qs = query.toString() ? `?${query.toString()}` : ''
    return apiV2.get<T>(`/marketplace/listings${qs}`)
  },

  purchaseListing: <T = unknown>(listingId: number | string, payload: PurchaseListingPayload) =>
    apiV2.post<T>(`/marketplace/listings/${listingId}/purchase`, payload),

  listEntitlement: (entitlementId: number | string, payload: CreateListingPayload) =>
    apiV2.post(`/marketplace/entitlements/${entitlementId}/list`, payload),

  withdrawListing: (listingId: number | string) =>
    apiV2.post(`/marketplace/listings/${listingId}/withdraw`),

  getUserReservations: <T = any>() => apiV2.get<T>('/reservations'),
}
