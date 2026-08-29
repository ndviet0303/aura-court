import { ref } from 'vue'
import { defineStore } from 'pinia'
import { authApi } from '../api/authApi'
import type { User, RegisterPayload, SocialLoginPayload, OnboardingPayload, UpdateProfilePayload } from '../types/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)
  const loaded = ref(false)

  async function load() {
    if (loaded.value) return user.value
    loading.value = true
    try {
      user.value = await authApi.me()
    } catch {
      user.value = null
    } finally {
      loading.value = false
      loaded.value = true
    }
    return user.value
  }

  async function login(email: string, password: string) {
    const result = await authApi.login({ email, password })
    user.value = result.user
    loaded.value = true
    return result.user
  }

  async function register(
    name: string,
    email: string,
    password: string,
    password_confirmation: string,
    extra?: Omit<RegisterPayload, 'name' | 'email' | 'password' | 'password_confirmation'>
  ) {
    const result = await authApi.register({
      name,
      email,
      password,
      password_confirmation,
      ...extra,
    })
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
    const result = await authApi.socialLogin({
      provider,
      email,
      name,
      provider_id,
      account_type,
    })
    user.value = result.user
    loaded.value = true
    return result.user
  }

  async function completeOnboarding(payload: OnboardingPayload) {
    const result = await authApi.onboarding(payload)
    user.value = result.user
    loaded.value = true
    return result.user
  }

  async function forgotPassword(email: string) {
    await authApi.forgotPassword({ email })
  }

  async function resetPassword(email: string, token: string, password: string, password_confirmation: string) {
    await authApi.resetPassword({ email, token, password, password_confirmation })
  }

  async function updateProfile(payload: string | UpdateProfilePayload) {
    user.value = await authApi.updateProfile(payload)
    return user.value
  }

  async function changePassword(current_password: string, password: string, password_confirmation: string) {
    await authApi.changePassword({ current_password, password, password_confirmation })
  }

  async function resendVerification() {
    await authApi.resendVerification()
  }

  async function logout() {
    try {
      await authApi.logout()
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
