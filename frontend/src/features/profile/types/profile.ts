import type { User, Membership } from '@/core/types/common'

export interface ProfileMembershipResponse {
  membership: Membership | null
}

export interface ProfileBookingsResponse {
  bookings?: unknown[]
}

export interface SportOption {
  id: string
  label: string
  icon: string
}

export type ProfileTabType = 'general' | 'security' | 'membership' | 'shortcuts'
