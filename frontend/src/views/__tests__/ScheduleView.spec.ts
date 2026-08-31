import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import ScheduleView from '@/features/court/views/ScheduleView.vue'
import { i18n } from '@/i18n'
import { useBookingStore } from '@/stores/booking'

const mockPush = vi.fn()
const mockReplace = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: { slug: 'san-1-tham-bwf-cau-giay' },
    query: {},
    fullPath: '/courts/san-1-tham-bwf-cau-giay/schedule',
  }),
  useRouter: () => ({
    push: mockPush,
    replace: mockReplace,
  }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

describe('ScheduleView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  const mockCourt = {
    id: 1,
    name: 'Sân 1 - Thảm BWF Tiêu Chuẩn',
    slug: 'san-1-tham-bwf-cau-giay',
    venue_id: 1,
    venue: {
      id: 1,
      name: 'Aura Court Cầu Giấy',
      slug: 'aura-court-cau-giay',
      address: '12 Cầu Giấy, Hà Nội',
      google_maps_url: null,
      is_active: true,
      share_url: '',
    },
    sport_zone: {
      id: 1,
      venue_id: 1,
      name: 'Thảm BWF Tiêu Chuẩn',
      sport_type: 'badminton' as const,
      slot_duration: 60,
      is_active: true,
    },
  }

  const mockSlots = [
    { startsAt: '2026-08-31T08:00:00.000Z', endsAt: '2026-08-31T09:00:00.000Z', price: 100000, available: true },
    { startsAt: '2026-08-31T09:00:00.000Z', endsAt: '2026-08-31T10:00:00.000Z', price: 100000, available: true },
    { startsAt: '2026-08-31T14:00:00.000Z', endsAt: '2026-08-31T15:00:00.000Z', price: 100000, available: false },
    { startsAt: '2026-08-31T18:00:00.000Z', endsAt: '2026-08-31T19:00:00.000Z', price: 125000, available: true },
  ]

  it('renders breadcrumbs, header, date picker, court selector, slots grid, and summary', async () => {
    const store = useBookingStore()
    store.courts = [mockCourt]
    store.slots = mockSlots

    const wrapper = mount(ScheduleView, {
      global: {
        plugins: [i18n],
      },
    })

    expect(wrapper.find('.page-title').text()).toContain('Lịch Đặt Sân Theo Ngày')
    expect(wrapper.find('.live-pill').text()).toContain('Cập nhật trực tiếp')
    expect(wrapper.find('.schedule-date-picker-card').exists()).toBe(true)
    expect(wrapper.find('.court-selector-container').exists()).toBe(true)
    expect(wrapper.find('.schedule-slots-section').exists()).toBe(true)
    expect(wrapper.find('.schedule-summary-card').exists()).toBe(true)
    expect(wrapper.find('.court-name').text()).toContain('Sân 1 - Thảm BWF Tiêu Chuẩn')
  })

  it('allows selecting slots, calculates total price, and allows slot removal from summary', async () => {
    const store = useBookingStore()
    store.courts = [mockCourt]
    store.slots = mockSlots

    const wrapper = mount(ScheduleView, {
      global: {
        plugins: [i18n],
      },
    })

    // Click quick select 1h button
    const quickBtn = wrapper.findAll('.btn-quick')[0]
    expect(quickBtn).toBeDefined()
    await quickBtn?.trigger('click')

    expect(store.selectedSlots.length).toBe(1)
    expect(wrapper.find('.total-num').text()).toContain('100.000')

    // Click remove slot button in summary
    const removeBtn = wrapper.find('.btn-remove-slot')
    expect(removeBtn.exists()).toBe(true)
    await removeBtn.trigger('click')

    expect(store.selectedSlots.length).toBe(0)
  })

  it('filters slots by session tab', async () => {
    const store = useBookingStore()
    store.courts = [mockCourt]
    store.slots = mockSlots

    const wrapper = mount(ScheduleView, {
      global: {
        plugins: [i18n],
      },
    })

    const filterBtns = wrapper.findAll('.filter-tab-btn')
    expect(filterBtns.length).toBe(5)

    // Click "Giờ vàng" tab
    await filterBtns[4]?.trigger('click')
    expect(filterBtns[4]?.classes()).toContain('active')
  })
})
