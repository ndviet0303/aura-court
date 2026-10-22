import { api } from '@/core/api/client'
import type { PackageTermDays } from '@/features/platform-fees/types/platformFee'
import type { PackagePaymentRequest, VenuePackage, VenuePackagesOverview } from '../types'

const organization = (organizationId: number) => `/partner/organizations/${organizationId}`

// D4, D8: the owner reads the venues' packages, creates or reopens a payment request and terminates a package.
export const platformSubscriptionApi = {
  getPackages: (organizationId: number) =>
    api.get<{ data: VenuePackagesOverview }>(`${organization(organizationId)}/packages`),

  createRequest: (organizationId: number, venueId: number, termDays: PackageTermDays) =>
    api.post<{ data: PackagePaymentRequest }>(
      `${organization(organizationId)}/venues/${venueId}/package-requests`,
      { term_days: termDays },
    ),

  getRequest: (organizationId: number, code: string) =>
    api.get<{ data: PackagePaymentRequest }>(
      `${organization(organizationId)}/package-requests/${code}`,
    ),

  terminate: (organizationId: number, venueId: number) =>
    api.post<{ data: VenuePackage; message: string }>(
      `${organization(organizationId)}/venues/${venueId}/package/terminate`,
      { confirm_consequences: true },
    ),
}
