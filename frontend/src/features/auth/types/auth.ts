export type { User, UserRole } from '@/core/types/common'

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  account_type?: 'customer' | 'court_owner'
  organization_name?: string
  phone?: string
  venue_address?: string
  courts_count?: number
}

export interface OnboardingPayload {
  account_type: 'customer' | 'court_owner'
  phone?: string
  play_level?: string
  organization_name?: string
  venue_address?: string
  courts_count?: number
}

export interface ForgotPasswordPayload {
  email: string
}

export interface ResetPasswordPayload {
  email: string
  token: string
  password: string
  password_confirmation: string
}

export interface UpdateProfilePayload {
  name?: string
  phone?: string
  play_level?: string
  favorite_sports?: string[]
  avatar_url?: string
}

export interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}
