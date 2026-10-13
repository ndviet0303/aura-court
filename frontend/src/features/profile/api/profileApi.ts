import { api } from '@/core/api/client'
import type { User } from '@/core/types/common'
import type { ProfileMembershipResponse } from '../types/profile'
import type { UpdateProfilePayload, ChangePasswordPayload } from '@/features/auth/types/auth'

export const profileApi = {
  getMembership: () => api.get<ProfileMembershipResponse>('/membership'),

  updateProfile: (payload: UpdateProfilePayload) => api.patch<User>('/profile', payload),

  changePassword: (payload: ChangePasswordPayload) =>
    api.patch<{ message?: string; user?: User }>('/profile/password', payload),

  resendVerification: () => api.post<{ message?: string }>('/auth/email/verification-notification'),
}
