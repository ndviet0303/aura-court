import type { Membership } from '@/core/types/common'

export interface ProfileMembershipResponse {
  membership: Membership | null
}

export interface ProfileBookingsResponse {
  bookings?: unknown[]
}
