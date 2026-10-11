import { api } from '@/core/api/client'
import type { AppNotification } from '../types/notification'

export const notificationApi = {
  list: () =>
    api.get<{ unread: number; notifications: { data: AppNotification[] } }>('/notifications'),

  markAsRead: (id: string | number) => api.post<{ message?: string }>(`/notifications/${id}/read`),

  markAllAsRead: () => api.post<{ message?: string }>('/notifications/read-all'),
}
