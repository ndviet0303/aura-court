export type { AppNotification } from '@/core/types/common'

export type NotificationFilterType = 'all' | 'unread' | 'booking' | 'payment' | 'social'

export interface NotificationMeta {
  icon: string
  color: string
  category: 'booking' | 'payment' | 'social' | 'system'
  badgeText: string
}

export interface StaffInvitation {
  id: number
  role: string
  expires_at: string | null
  organization?: { id: number; name: string } | null
  inviter?: { id: number; name: string } | null
  venue?: { id: number; name: string | Record<string, string> } | null
}
