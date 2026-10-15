import { api } from '@/core/api/client'
import type { AppNotification, StaffInvitation } from '../types/notification'

export const notificationApi = {
  list: () =>
    api.get<{ unread: number; notifications: { data: AppNotification[] } }>('/notifications'),

  markAsRead: (id: string | number) => api.post<{ message?: string }>(`/notifications/${id}/read`),

  markAllAsRead: () => api.post<{ message?: string }>('/notifications/read-all'),

  // The signed-in invitee's own staff invitations (BR-19).
  myInvitations: () => api.get<{ invitations: StaffInvitation[] }>('/invitations'),

  acceptInvitation: (id: number) => api.post<{ message?: string }>(`/invitations/${id}/accept`),

  declineInvitation: (id: number) => api.post<{ message?: string }>(`/invitations/${id}/decline`),
}
