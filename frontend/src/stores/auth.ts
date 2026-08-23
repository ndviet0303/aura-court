import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/lib/api'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)
  const loaded = ref(false)

  async function load() {
    if (loaded.value) return user.value
    loading.value = true
    try {
      user.value = await api.get<User>('/auth/me')
    } catch {
      user.value = null
    } finally {
      loading.value = false
      loaded.value = true
    }
    return user.value
  }

  async function login(email: string, password: string) {
    await api.csrf()
    const result = await api.post<{ user: User }>('/auth/login', { email, password })
    await api.csrf()
    user.value = result.user
    loaded.value = true
  }

  async function register(
    name: string,
    email: string,
    password: string,
    password_confirmation: string,
    extra?: {
      account_type?: 'customer' | 'court_owner'
      organization_name?: string
      phone?: string
      venue_address?: string
      courts_count?: number
    }
  ) {
    await api.csrf()
    const result = await api.post<{ user: User; account_type?: string }>('/auth/register', {
      name,
      email,
      password,
      password_confirmation,
      ...extra,
    })
    await api.csrf()
    user.value = result.user
    loaded.value = true
    return result.user
  }

  async function socialLogin(
    provider: 'google' | 'facebook',
    email: string,
    name: string,
    provider_id?: string,
    account_type?: 'customer' | 'court_owner'
  ) {
    await api.csrf()
    const result = await api.post<{ user: User }>('/auth/social-login', {
      provider,
      email,
      name,
      provider_id,
      account_type,
    })
    await api.csrf()
    user.value = result.user
    loaded.value = true
    return result.user
  }

  async function completeOnboarding(payload: {
    account_type: 'customer' | 'court_owner'
    phone?: string
    play_level?: string
    organization_name?: string
    venue_address?: string
    courts_count?: number
  }) {
    await api.csrf()
    const result = await api.post<{ user: User }>('/auth/onboarding', payload)
    await api.csrf()
    user.value = result.user
    loaded.value = true
    return result.user
  }

  async function forgotPassword(email: string) {
    await api.post('/auth/forgot-password', { email })
  }

  async function resetPassword(email: string, token: string, password: string, password_confirmation: string) {
    await api.post('/auth/reset-password', { email, token, password, password_confirmation })
  }

  async function updateProfile(name: string) {
    user.value = await api.patch<User>('/profile', { name })
  }

  async function changePassword(current_password: string, password: string, password_confirmation: string) {
    await api.patch('/profile/password', { current_password, password, password_confirmation })
  }

  async function resendVerification() {
    await api.post('/auth/email/verification-notification')
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch {
      // ignore
    }
    user.value = null
    loaded.value = true
  }

  return {
    user,
    loading,
    loaded,
    load,
    login,
    register,
    socialLogin,
    completeOnboarding,
    forgotPassword,
    resetPassword,
    updateProfile,
    changePassword,
    resendVerification,
    logout,
  }
})
