import { api } from '@/core/api/client'

export interface AdminUser {
  id: number
  name: string
  email: string
  role: string
  status: string
  created_at: string | null
}

export interface AdminUserPage {
  data: AdminUser[]
  current_page: number
  last_page: number
  total: number
}

type LockResult = { message?: string; user: Pick<AdminUser, 'id' | 'email' | 'status'> }

// BR-23: platform admins list accounts and lock or unlock them with a reason.
export const adminUserApi = {
  list: (params: { search?: string; status?: string; page?: number }) =>
    api.get<AdminUserPage>('/admin/users', { params }),

  lock: (id: number, reason: string) => api.post<LockResult>(`/admin/users/${id}/lock`, { reason }),

  unlock: (id: number, reason: string) =>
    api.post<LockResult>(`/admin/users/${id}/unlock`, { reason }),
}
