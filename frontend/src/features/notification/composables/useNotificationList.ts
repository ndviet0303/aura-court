import { ref, computed, onMounted } from 'vue'
import { notificationApi } from '../api/notificationApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type {
  AppNotification,
  NotificationFilterType,
  NotificationMeta,
} from '../types/notification'

export function getNotificationCategory(
  event?: string,
): 'booking' | 'payment' | 'social' | 'system' {
  if (!event) return 'system'
  const ev = event.toLowerCase()
  if (
    ev.includes('booking') ||
    ev.includes('court') ||
    ev.includes('waitlist') ||
    ev.includes('schedule') ||
    ev.includes('reschedule')
  ) {
    return 'booking'
  }
  if (
    ev.includes('payment') ||
    ev.includes('split_bill') ||
    ev.includes('wallet') ||
    ev.includes('refund') ||
    ev.includes('sepay') ||
    ev.includes('stripe')
  ) {
    return 'payment'
  }
  if (
    ev.includes('match') ||
    ev.includes('tournament') ||
    ev.includes('social') ||
    ev.includes('resale')
  ) {
    return 'social'
  }
  return 'system'
}

export function getNotificationMeta(event?: string): NotificationMeta {
  // Backend events mix separators (booking_expired, social.match_invitation); match on one form.
  const ev = (event || '').toLowerCase().replace(/\./g, '_')

  if (ev.includes('booking_confirmed') || ev.includes('booking_paid')) {
    return {
      icon: 'task_alt',
      color: '#059669',
      category: 'booking',
      badgeText: 'ĐẶT SÂN THÀNH CÔNG',
    }
  }
  if (ev.includes('booking_created')) {
    return {
      icon: 'calendar_month',
      color: '#db2777',
      category: 'booking',
      badgeText: 'GIỮ CHỖ SÂN',
    }
  }
  if (ev.includes('booking_expiring') || ev.includes('expiring_soon')) {
    return {
      icon: 'alarm',
      color: '#d97706',
      category: 'booking',
      badgeText: 'SẮP HẾT HẠN GIỮ CHỖ',
    }
  }
  if (ev.includes('booking_expired') || ev.includes('cancel')) {
    return { icon: 'event_busy', color: '#dc2626', category: 'booking', badgeText: 'LỊCH ĐÃ HỦY' }
  }
  if (ev.includes('waitlist')) {
    return {
      icon: 'how_to_reg',
      color: '#0284c7',
      category: 'booking',
      badgeText: 'HÀNG ĐỢI SÂN TRỐNG',
    }
  }
  if (
    ev.includes('payment_success') ||
    ev.includes('payment_confirmed') ||
    ev.includes('sepay') ||
    ev.includes('stripe')
  ) {
    return {
      icon: 'payments',
      color: '#059669',
      category: 'payment',
      badgeText: 'THANH TOÁN THÀNH CÔNG',
    }
  }
  if (ev.includes('split_bill')) {
    return {
      icon: 'call_split',
      color: '#7c3aed',
      category: 'payment',
      badgeText: 'CHIA TIỀN NHÓM',
    }
  }
  if (ev.includes('wallet')) {
    return {
      icon: 'account_balance_wallet',
      color: '#6B2D3F',
      category: 'payment',
      badgeText: 'VÍ HỘI VIÊN',
    }
  }
  if (ev.includes('matchmaking') || ev.includes('match')) {
    return { icon: 'groups', color: '#0284c7', category: 'social', badgeText: 'KÈO GIAO LƯU' }
  }
  if (ev.includes('friendship')) {
    return { icon: 'person_add', color: '#0284c7', category: 'social', badgeText: 'KẾT BẠN' }
  }
  if (ev.includes('tournament')) {
    return { icon: 'emoji_events', color: '#d97706', category: 'social', badgeText: 'GIẢI ĐẤU' }
  }
  if (ev.includes('resale')) {
    return { icon: 'swap_horiz', color: '#059669', category: 'social', badgeText: 'CHỢ NHƯỢNG VÉ' }
  }

  return {
    icon: 'notifications',
    color: '#6B2D3F',
    category: 'system',
    badgeText: 'THÔNG BÁO HỆ THỐNG',
  }
}

export function useNotificationList() {
  const notifications = ref<AppNotification[]>([])
  const unread = ref(0)
  const loading = ref(false)
  const markingAllRead = ref(false)
  const error = ref('')
  const activeFilter = ref<NotificationFilterType>('all')

  const filterCounts = computed(() => {
    let unreadCount = 0
    let bookingCount = 0
    let paymentCount = 0
    let socialCount = 0

    for (const item of notifications.value) {
      if (!item.read_at) unreadCount++
      const cat = getNotificationCategory(item.data?.event)
      if (cat === 'booking') bookingCount++
      else if (cat === 'payment') paymentCount++
      else if (cat === 'social') socialCount++
    }

    return {
      all: notifications.value.length,
      unread: unreadCount,
      booking: bookingCount,
      payment: paymentCount,
      social: socialCount,
    }
  })

  const filteredNotifications = computed(() => {
    if (activeFilter.value === 'all') return notifications.value
    if (activeFilter.value === 'unread') return notifications.value.filter((n) => !n.read_at)
    return notifications.value.filter(
      (n) => getNotificationCategory(n.data?.event) === activeFilter.value,
    )
  })

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const res = await notificationApi.list()
      unread.value = res.unread ?? 0
      notifications.value = res.notifications?.data ?? []
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách thông báo. Vui lòng thử lại.')
    } finally {
      loading.value = false
    }
  }

  async function markAsRead(notification: AppNotification) {
    if (notification.read_at) return
    try {
      await notificationApi.markAsRead(notification.id)
      notification.read_at = new Date().toISOString()
      unread.value = Math.max(0, unread.value - 1)
    } catch {
      // Non-blocking fallback
      notification.read_at = new Date().toISOString()
      unread.value = Math.max(0, unread.value - 1)
    }
  }

  async function markAllAsRead() {
    if (unread.value === 0 && notifications.value.every((n) => n.read_at)) return
    markingAllRead.value = true
    try {
      await notificationApi.markAllAsRead()
      const nowIso = new Date().toISOString()
      notifications.value.forEach((n) => {
        n.read_at ||= nowIso
      })
      unread.value = 0
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể đánh dấu tất cả đã đọc.')
    } finally {
      markingAllRead.value = false
    }
  }

  onMounted(() => {
    load()
  })

  return {
    notifications,
    unread,
    loading,
    markingAllRead,
    error,
    activeFilter,
    filterCounts,
    filteredNotifications,
    load,
    markAsRead,
    markAllAsRead,
  }
}
