import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/lib/api'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)

  async function load() {
    try { user.value = await api.get<User>('/auth/me') } catch { user.value = null }
  }

  async function login(email: string, password: string) {
    await api.csrf()
    const result = await api.post<{ user: User }>('/auth/login', { email, password })
    user.value = result.user
  }

  async function register(name: string, email: string, password: string, password_confirmation: string) {
    await api.csrf()
    const result = await api.post<{ user: User }>('/auth/register', { name, email, password, password_confirmation })
    user.value = result.user
  }

  async function forgotPassword(email: string) { await api.post('/auth/forgot-password', { email }) }
  async function resetPassword(email: string, token: string, password: string, password_confirmation: string) { await api.post('/auth/reset-password', { email, token, password, password_confirmation }) }
  async function updateProfile(name: string) { user.value = await api.patch<User>('/profile', { name }) }
  async function logout() { await api.post('/auth/logout'); user.value = null }

  return { user, loading, load, login, register, forgotPassword, resetPassword, updateProfile, logout }
})
