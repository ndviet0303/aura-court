import { api } from '@/core/api/client'
import type { PurchaseListingPayload, CreateListingPayload } from '../types/marketplace'

export const marketplaceApi = {
  getListings: <T = any>(params?: { mine?: boolean; page?: number }) => {
    const query = new URLSearchParams()
    if (params?.mine) query.append('mine', '1')
    if (params?.page) query.append('page', String(params.page))
    const qs = query.toString() ? `?${query.toString()}` : ''
    return api.get<T>(`/marketplace/listings${qs}`)
  },

  purchaseListing: <T = unknown>(listingId: number | string, payload: PurchaseListingPayload) =>
    api.post<T>(`/marketplace/listings/${listingId}/purchase`, payload),

  listEntitlement: (entitlementId: number | string, payload: CreateListingPayload) =>
    api.post(`/marketplace/entitlements/${entitlementId}/list`, payload),

  withdrawListing: (listingId: number | string) =>
    api.post(`/marketplace/listings/${listingId}/withdraw`),

  getUserReservations: <T = any>() => api.get<T>('/reservations'),
}
