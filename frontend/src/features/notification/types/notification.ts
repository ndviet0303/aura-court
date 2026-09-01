export type { AppNotification } from '@/core/types/common'

export type NotificationFilterType = 'all' | 'unread' | 'booking' | 'payment' | 'social'

export interface NotificationMeta {
  icon: string
  color: string
  category: 'booking' | 'payment' | 'social' | 'system'
  badgeText: string
}
