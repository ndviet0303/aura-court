import { api } from '@/core/api/client'
import type { ProfileMembershipResponse, ProfileBookingsResponse } from '../types/profile'

export const profileApi = {
  getMembership: () =>
    api.get<ProfileMembershipResponse>('/membership'),

  getBookings: () =>
    api.get<ProfileBookingsResponse>('/bookings'),
}
