import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import NotificationsView from '../views/NotificationsView.vue'
import { i18n } from '@/i18n'
import { notificationApi } from '../api/notificationApi'
import { getNotificationMeta } from '../composables/useNotificationList'

vi.mock('vue-router', () => ({
  RouterLink: {
    template: '<a :href="to"><slot /></a>',
    props: ['to'],
  },
}))

vi.mock('../api/notificationApi', () => ({
  notificationApi: {
    list: vi.fn(),
    markAsRead: vi.fn(),
    markAllAsRead: vi.fn(),
  },
}))

describe('NotificationsView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders notifications list and filter pills', async () => {
    const mockNotifications = [
      {
        id: 'n-1',
        type: 'App\\Notifications\\AuraBookingNotification',
        data: {
          event: 'booking.created',
          title: 'Đặt sân VIP 1 thành công',
          body: 'Khung giờ 18:00 - 19:00',
          action_url: '/booking',
        },
        read_at: null,
        created_at: '2026-09-01T10:00:00Z',
      },
      {
        id: 'n-2',
        type: 'App\\Notifications\\AuraBookingNotification',
        data: {
          event: 'payment.success',
          title: 'Thanh toán thành công VietQR',
          body: 'Đã nhận 200.000đ',
          action_url: '/reservations',
        },
        read_at: '2026-09-01T11:00:00Z',
        created_at: '2026-09-01T11:00:00Z',
      },
    ]

    vi.mocked(notificationApi.list).mockResolvedValue({
      unread: 1,
      notifications: { data: mockNotifications },
    })

    const wrapper = mount(NotificationsView, {
      global: {
        plugins: [i18n],
      },
    })

    // Wait for async load
    await new Promise((r) => setTimeout(r, 10))

    expect(wrapper.find('.page-title').text()).toContain('Thông Báo Của Bạn')
    expect(wrapper.find('.unread-count-pill').text()).toContain('1 mới')

    const items = wrapper.findAll('.notification-item-card')
    expect(items.length).toBe(2)
  })

  it('renders empty state when there are no notifications', async () => {
    vi.mocked(notificationApi.list).mockResolvedValue({
      unread: 0,
      notifications: { data: [] },
    })

    const wrapper = mount(NotificationsView, {
      global: {
        plugins: [i18n],
      },
    })

    await new Promise((r) => setTimeout(r, 10))

    expect(wrapper.find('.empty-state-card').exists()).toBe(true)
    expect(wrapper.find('.empty-title').text()).toContain('Hộp thư thông báo trống')
  })
})

describe('getNotificationMeta', () => {
  it('maps the event names the backend sends', () => {
    expect(getNotificationMeta('booking_expiring').badgeText).toBe('SẮP HẾT HẠN GIỮ CHỖ')
    expect(getNotificationMeta('booking_expired').badgeText).toBe('LỊCH ĐÃ HỦY')
    expect(getNotificationMeta('payment_confirmed').category).toBe('payment')
    expect(getNotificationMeta('social.friendship_request').category).toBe('social')
    expect(getNotificationMeta('booking.created').badgeText).toBe('GIỮ CHỖ SÂN')
  })
})
