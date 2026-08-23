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

  async function register(name: string, email: string, password: string, password_confirmation: string) {
    await api.csrf()
    const result = await api.post<{ user: User }>('/auth/register', { name, email, password, password_confirmation })
    await api.csrf()
    user.value = result.user
    loaded.value = true
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
    forgotPassword,
    resetPassword,
    updateProfile,
    changePassword,
    resendVerification,
    logout,
  }
})
