import { describe, it, expect, beforeEach, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import ScheduleView from '@/features/court/views/ScheduleView.vue'
import { i18n } from '@/i18n'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
import { courtApi } from '../api/courtApi'
import { bookingApi } from '@/features/booking/api/bookingApi'
import type { Reservation } from '@/features/booking/types/reservation'

const mockRoute = vi.hoisted(() => ({
  params: { slug: 'san-1-tham-bwf-cau-giay' },
  query: {},
  fullPath: '/courts/san-1-tham-bwf-cau-giay/schedule',
}))

vi.mock('../api/courtApi', () => ({ courtApi: { getBySlug: vi.fn() } }))
vi.mock('@/features/booking/api/bookingApi', () => ({
  bookingApi: { getAvailability: vi.fn(), getCourts: vi.fn(), checkoutReservation: vi.fn() },
}))

const mockPush = vi.fn()
const mockReplace = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
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
    mockRoute.params.slug = 'san-1-tham-bwf-cau-giay'
    vi.mocked(courtApi.getBySlug).mockResolvedValue(mockCourt)
    vi.mocked(bookingApi.getAvailability).mockResolvedValue(mockSlots)
    vi.mocked(bookingApi.getCourts).mockResolvedValue([mockCourt])
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
    sport_type: 'badminton' as const,
    slot_duration: 60,
  }

  const mockSlots = [
    {
      startsAt: '2026-08-31T08:00:00.000Z',
      endsAt: '2026-08-31T09:00:00.000Z',
      price: 100000,
      available: true,
    },
    {
      startsAt: '2026-08-31T09:00:00.000Z',
      endsAt: '2026-08-31T10:00:00.000Z',
      price: 100000,
      available: true,
    },
    {
      startsAt: '2026-08-31T14:00:00.000Z',
      endsAt: '2026-08-31T15:00:00.000Z',
      price: 100000,
      available: false,
    },
    {
      startsAt: '2026-08-31T18:00:00.000Z',
      endsAt: '2026-08-31T19:00:00.000Z',
      price: 125000,
      available: true,
    },
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

    await flushPromises()

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

    await flushPromises()

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

    await flushPromises()

    const filterBtns = wrapper.findAll('.filter-tab-btn')
    expect(filterBtns.length).toBe(5)

    // Click "Giờ vàng" tab
    await filterBtns[4]?.trigger('click')
    expect(filterBtns[4]?.classes()).toContain('active')
  })

  it('loads the requested court when it is missing from the cached list', async () => {
    const store = useBookingStore()
    store.courts = [mockCourt]
    mockRoute.params.slug = '243'
    const requestedCourt = {
      ...mockCourt,
      id: 243,
      name: 'Sân mới được công bố',
      slug: 'san-moi',
      venue: { ...mockCourt.venue, id: 73, payment_model: 'direct_owner_v1' as const },
    }
    vi.mocked(courtApi.getBySlug).mockResolvedValue(requestedCourt)

    const wrapper = mount(ScheduleView, { global: { plugins: [i18n] } })
    await flushPromises()

    expect(wrapper.find('.court-name').text()).toBe(requestedCourt.name)
    expect(store.selectedCourt).toBe(243)
    expect(bookingApi.getAvailability).toHaveBeenCalledTimes(1)
    expect(bookingApi.getAvailability).toHaveBeenCalledWith(243, expect.any(String))
    expect(bookingApi.getCourts).not.toHaveBeenCalled()
  })

  it('shows an error and clears stale booking selection when the requested court is unavailable', async () => {
    const store = useBookingStore()
    store.courts = [mockCourt]
    store.selectedCourt = mockCourt.id
    store.slots = mockSlots
    store.selectedSlots = [mockSlots[0]!]
    mockRoute.params.slug = 'missing-court'
    vi.mocked(courtApi.getBySlug).mockRejectedValue(new Error('Không tìm thấy sân được yêu cầu.'))

    const wrapper = mount(ScheduleView, { global: { plugins: [i18n] } })
    await flushPromises()

    expect(wrapper.find('.alert-pill-error').text()).toContain('Không tìm thấy sân được yêu cầu.')
    expect(wrapper.find('.schedule-summary-card').exists()).toBe(false)
    expect(store.selectedCourt).toBeNull()
    expect(store.selectedSlots).toEqual([])
    expect(store.slots).toEqual([])
    expect(bookingApi.getAvailability).not.toHaveBeenCalled()
    await wrapper.findAll('.btn-quick')[0]!.trigger('click')
    expect(store.selectedSlots).toEqual([])
    expect(bookingApi.checkoutReservation).not.toHaveBeenCalled()
  })

  it('checks out the selected slots and opens the reservation', async () => {
    useAuthStore().user = { id: 1, name: 'Player', email: 'p@example.com', role: 'customer' }
    vi.mocked(bookingApi.checkoutReservation).mockResolvedValue({
      reservation: { code: 'RSV-NEW-1', status: 'held' } as Reservation,
    })

    const wrapper = mount(ScheduleView, {
      global: { plugins: [i18n], stubs: { LoyaltyPointSelector: true } },
    })
    await flushPromises()
    await wrapper.findAll('.btn-quick')[0]!.trigger('click')
    wrapper.findComponent({ name: 'LoyaltyPointSelector' }).vm.$emit('change', {
      loyalty_points: 50,
      loyalty_policy_version: 2,
      idempotency_key: 'loy-key-1',
    })
    await wrapper.find('.btn-checkout').trigger('click')
    await flushPromises()

    // The selector's stable key travels as the Idempotency-Key request option, not in the body.
    expect(bookingApi.checkoutReservation).toHaveBeenCalledWith(
      {
        court_id: 1,
        slots: [mockSlots[0]!.startsAt],
        loyalty_points: 50,
        loyalty_policy_version: 2,
      },
      'loy-key-1',
    )
    expect(mockPush).toHaveBeenCalledWith({ name: 'reservations', params: { code: 'RSV-NEW-1' } })
  })
})
