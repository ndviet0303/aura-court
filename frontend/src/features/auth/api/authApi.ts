import { api } from '@/core/api/client'
import type {
  User,
  LoginPayload,
  RegisterPayload,
  OnboardingPayload,
  ForgotPasswordPayload,
  ResetPasswordPayload,
  UpdateProfilePayload,
  ChangePasswordPayload,
} from '../types/auth'

export const authApi = {
  csrf: () => api.csrf(),

  me: () => api.get<User>('/auth/me'),

  login: async (payload: LoginPayload) => {
    await api.csrf()
    const result = await api.post<{ user: User }>('/auth/login', payload)
    await api.csrf()
    return result
  },

  register: async (payload: RegisterPayload) => {
    await api.csrf()
    const result = await api.post<{ user: User; account_type?: string }>('/auth/register', payload)
    await api.csrf()
    return result
  },

  getSocialRedirect: (provider: string) =>
    api.get<{ url?: string | null; demo_mode?: boolean }>(`/auth/${provider}/redirect`),

  onboarding: async (payload: OnboardingPayload) => {
    await api.csrf()
    const result = await api.post<{ user: User }>('/auth/onboarding', payload)
    await api.csrf()
    return result
  },

  forgotPassword: (payload: ForgotPasswordPayload) =>
    api.post<{ message?: string }>('/auth/forgot-password', payload),

  resetPassword: (payload: ResetPasswordPayload) =>
    api.post<{ message?: string }>('/auth/reset-password', payload),

  updateProfile: (payload: string | UpdateProfilePayload) => {
    const body = typeof payload === 'string' ? { name: payload } : payload
    return api.patch<User>('/profile', body)
  },

  changePassword: (payload: ChangePasswordPayload) =>
    api.patch<{ message?: string }>('/profile/password', payload),

  resendVerification: () =>
    api.post<{ message?: string }>('/auth/email/verification-notification'),

  logout: () => api.post('/auth/logout'),
}
