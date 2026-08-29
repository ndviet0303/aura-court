import { api } from '@/core/api/client'
import type { User, Membership } from '@/core/types/common'

export const profileApi = {
  getMembership: () => api.get<Membership>('/membership'),

  getBookings: () => api.get<any[]>('/bookings'),

  updateProfile: (payload: any) => api.patch<User>('/profile', payload),

  changePassword: (payload: any) => api.patch('/profile/password', payload),

  sendVerification: () => api.post('/auth/email/verification-notification'),
}
