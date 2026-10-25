import { api } from '@/core/api/client'
import type { User } from '@/core/types/common'
import type { VenueCreditAccount, VenueCreditCashOutCode } from '@/core/types/venueCredit'
import type { ProfileMembershipResponse } from '../types/profile'
import type { UpdateProfilePayload, ChangePasswordPayload } from '@/features/auth/types/auth'

export const profileApi = {
  getMembership: () => api.get<ProfileMembershipResponse>('/membership'),

  updateProfile: (payload: UpdateProfilePayload) => api.patch<User>('/profile', payload),

  changePassword: (payload: ChangePasswordPayload) =>
    api.patch<{ message?: string; logged_out?: boolean }>('/profile/password', payload),

  sendPasswordSetupLink: () => api.post<{ message?: string }>('/profile/password/setup-link'),

  resendVerification: () => api.post<{ message?: string }>('/auth/email/verification-notification'),

  // BR-06: an account created without an email (Facebook) adds one, then verifies it.
  addEmail: (email: string) =>
    api.post<{ message?: string; user?: User }>('/auth/email', { email }),

  getVenueCredits: () => api.get<{ data: VenueCreditAccount[] }>('/me/venue-credits'),

  // A new code replaces the customer's live one at this organization; it is valid for 10 minutes.
  createCashOutCode: (organizationId: number, amount: number) =>
    api.post<VenueCreditCashOutCode>(`/me/venue-credits/${organizationId}/cash-out-codes`, {
      amount,
    }),
}
