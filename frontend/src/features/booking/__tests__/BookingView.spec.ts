import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import BookingView from '@/features/booking/views/BookingView.vue'
import { bookingApi } from '@/features/booking/api/bookingApi'
import { i18n } from '@/i18n'
import type { Reservation } from '@/features/booking/types/reservation'

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
  }),
  useRoute: () => ({
    path: '/booking',
    query: {},
  }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'string\' ? to : JSON.stringify(to)"><slot /></a>',
  },
}))

const mockBookings = [
  {
    id: 101,
    code: 'RSV-20260831-01',
    status: 'confirmed',
    total_amount: 300000,
    court: {
      id: 1,
      name: 'Sân VIP A1 (Thảm Yonex)',
      slug: 'san-vip-a1',
      venue: { id: 1, name: 'Aura Badminton Center Tân Bình' },
      sport_type: 'badminton',
      slot_duration: 60,
    },
    slots: [
      {
        starts_at: '2099-09-01T18:00:00Z',
        ends_at: '2099-09-01T20:00:00Z',
        unit_price: 350000,
      },
    ],
  },
  {
    id: 102,
    code: 'RSV-20260831-02',
    status: 'held',
    payment_model: 'direct_owner_v1',
    total_amount: 200000,
    court: {
      id: 2,
      name: 'Sân Pickleball Pro 1',
      slug: 'san-pickleball-pro-1',
      venue: { id: 1, name: 'Aura Badminton Center Tân Bình' },
      sport_type: 'pickleball',
      slot_duration: 60,
    },
    slots: [
      {
        starts_at: '2099-09-02T08:00:00Z',
        ends_at: '2099-09-02T10:00:00Z',
        unit_price: 200000,
      },
    ],
  },
  {
    id: 103,
    code: 'RSV-20260820-03',
    status: 'cancelled',
    total_amount: 150000,
    court: {
      id: 3,
      name: 'Sân Standard 2',
      slug: 'san-standard-2',
      venue: { id: 1, name: 'Aura Badminton Center Tân Bình' },
      sport_type: 'badminton',
      slot_duration: 60,
    },
    slots: [
      {
        starts_at: '2026-08-20T14:00:00Z',
        ends_at: '2026-08-20T16:00:00Z',
        unit_price: 150000,
      },
    ],
  },
]

describe('BookingView (/booking)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders booking hero header and loads booking cards list', async () => {
    vi.spyOn(bookingApi, 'getMyReservations').mockResolvedValue({
      data: mockBookings as any,
    })

    const wrapper = mount(BookingView, {
      global: { plugins: [i18n] },
    })

    expect(wrapper.find('[data-testid="booking-loading"]').exists()).toBe(true)

    await flushPromises()

    expect(wrapper.find('.page-title').text()).toContain('Quản Lý Lịch Đặt Sân Của Tôi')
    expect(wrapper.find('[data-testid="booking-list"]').exists()).toBe(true)

    const cards = wrapper.findAll('.user-booking-card')
    expect(cards.length).toBe(3)
    expect(cards[0]?.text()).toContain('#RSV-20260831-01')
    expect(cards[0]?.text()).toContain('Sân VIP A1')
    expect(cards[0]?.text()).toContain('ĐÃ XÁC NHẬN')
  })

  it('filters by status tabs (upcoming, completed, cancelled)', async () => {
    vi.spyOn(bookingApi, 'getMyReservations').mockResolvedValue({
      data: mockBookings as any,
    })

    const wrapper = mount(BookingView, {
      global: { plugins: [i18n] },
    })

    await flushPromises()

    const tabs = wrapper.findAll('.tab-btn')
    expect(tabs.length).toBe(4)

    // Click "Sắp diễn ra" tab (both confirmed upcoming + held)
    await tabs[1]?.trigger('click')
    await flushPromises()
    expect(wrapper.findAll('.user-booking-card').length).toBe(2)

    // Click "Đã huỷ" tab
    await tabs[3]?.trigger('click')
    await flushPromises()
    const cancelledCards = wrapper.findAll('.user-booking-card')
    expect(cancelledCards.length).toBe(1)
    expect(cancelledCards[0]?.text()).toContain('#RSV-20260820-03')
  })

  it('filters bookings by search query', async () => {
    vi.spyOn(bookingApi, 'getMyReservations').mockResolvedValue({
      data: mockBookings as any,
    })

    const wrapper = mount(BookingView, {
      global: { plugins: [i18n] },
    })

    await flushPromises()

    const searchInput = wrapper.find('.search-input')
    await searchInput.setValue('Pickleball')
    await flushPromises()

    const filteredCards = wrapper.findAll('.user-booking-card')
    expect(filteredCards.length).toBe(1)
    expect(filteredCards[0]?.text()).toContain('Sân Pickleball Pro 1')
  })

  it('opens and closes QR Check-in modal', async () => {
    vi.spyOn(bookingApi, 'getMyReservations').mockResolvedValue({
      data: mockBookings as any,
    })

    const wrapper = mount(BookingView, {
      global: { plugins: [i18n] },
    })

    await flushPromises()

    // Find first card's QR button
    const qrBtn = wrapper.find('.btn-card-secondary')
    expect(qrBtn.text()).toContain('Mã Check-in')
    await qrBtn.trigger('click')
    await flushPromises()

    // QR modal is visible
    expect(wrapper.find('.modal-title').text()).toContain('Thẻ Check-In Sân Đấu')
    expect(wrapper.find('.qr-image').exists()).toBe(true)

    // Close QR modal
    await wrapper.find('.btn-done').trigger('click')
    await flushPromises()
    expect(wrapper.find('.qr-image').exists()).toBe(false)
  })

  it('offers resale instead of cancel or reschedule on confirmed bookings', async () => {
    vi.spyOn(bookingApi, 'getMyReservations').mockResolvedValue({
      data: mockBookings as unknown as Reservation[],
    })

    const wrapper = mount(BookingView, {
      global: { plugins: [i18n] },
    })

    await flushPromises()

    // 1. Confirmed booking card exposes resale only; bookings are final
    const confirmedCard = wrapper.findAll('.user-booking-card')[0]
    expect(confirmedCard?.find('[data-testid="booking-resale-btn"]').exists()).toBe(true)
    expect(confirmedCard?.find('[data-testid="booking-cancel-btn"]').exists()).toBe(false)
    expect(confirmedCard?.find('[data-testid="booking-reschedule-btn"]').exists()).toBe(false)

    // 2. Policy notice names resale as the only remedy
    const policyNotice = wrapper.find('.policy-notice')
    expect(policyNotice.exists()).toBe(true)
    expect(policyNotice.text()).toContain('pass sân')

    // 3. Cancelled booking card offers no resale
    const cancelledCard = wrapper.findAll('.user-booking-card')[2]
    expect(cancelledCard?.find('[data-testid="booking-resale-btn"]').exists()).toBe(false)
    expect(cancelledCard?.text()).toContain('ĐÃ HỦY')
  })

  it('opens review modal and submits review', async () => {
    vi.spyOn(bookingApi, 'getMyReservations').mockResolvedValue({
      data: mockBookings as any,
    })
    const reviewSpy = vi.spyOn(bookingApi, 'submitReview').mockResolvedValue({} as any)

    const wrapper = mount(BookingView, {
      global: { plugins: [i18n] },
    })

    await flushPromises()

    // Click "Đánh Giá" button on confirmed card
    const buttons = wrapper.findAll('.btn-card-secondary')
    const reviewBtn = buttons.find((b) => b.text().includes('Đánh Giá'))
    expect(reviewBtn).toBeDefined()
    await reviewBtn?.trigger('click')
    await flushPromises()

    expect(wrapper.find('.modal-title').text()).toContain('Đánh Giá Trải Nghiệm Sân Đấu')

    // Submit review
    const submitBtn = wrapper.find('.btn-submit')
    await submitBtn.trigger('click')
    await flushPromises()

    expect(reviewSpy).toHaveBeenCalledWith('RSV-20260831-01', {
      rating: 5,
      body: undefined,
    })
  })

  it('shows a mapped legacy booking with its original code and status, and links held ones to the reservation', async () => {
    const legacy = {
      ...mockBookings[2],
      id: 104,
      code: 'LEGACY-BK-OLD-1',
      status: 'legacy',
      legacy_mapping: [
        { id: 1, reservation_id: 104, legacy_code: 'BK-OLD-1', legacy_status: 'refunded' },
      ],
    }
    vi.spyOn(bookingApi, 'getMyReservations').mockResolvedValue({
      data: [mockBookings[1], legacy] as unknown as Reservation[],
    })

    const wrapper = mount(BookingView, { global: { plugins: [i18n] } })
    await flushPromises()

    const [held, legacyCard] = wrapper.findAll('.user-booking-card')
    expect(legacyCard?.text()).toContain('#BK-OLD-1')
    expect(legacyCard?.text()).not.toContain('LEGACY-')
    expect(legacyCard?.text()).toContain('ĐÃ HOÀN TIỀN')
    expect(legacyCard?.text()).not.toContain('Đánh Giá')
    expect(held?.text()).toContain('CHỜ THANH TOÁN')
    expect(JSON.parse(held?.find('.btn-card-pay').attributes('href') ?? '{}')).toEqual({
      name: 'reservations',
      params: { code: 'RSV-20260831-02' },
    })
    expect(held?.text()).not.toContain('Đánh Giá')

    await wrapper.findAll('.tab-btn')[3]?.trigger('click')
    expect(wrapper.findAll('.user-booking-card').map((c) => c.text())).toEqual([
      expect.stringContaining('#BK-OLD-1'),
    ])
  })

  it('offers a review on a mapped legacy booking the V1 rules allowed (no_show)', async () => {
    const legacy = {
      ...mockBookings[2],
      id: 105,
      code: 'LEGACY-BK-OLD-2',
      status: 'legacy',
      legacy_mapping: [
        { id: 2, reservation_id: 105, legacy_code: 'BK-OLD-2', legacy_status: 'no_show' },
      ],
    }
    vi.spyOn(bookingApi, 'getMyReservations').mockResolvedValue({
      data: [legacy] as unknown as Reservation[],
    })
    const reviewSpy = vi.spyOn(bookingApi, 'submitReview').mockResolvedValue({})

    const wrapper = mount(BookingView, { global: { plugins: [i18n] } })
    await flushPromises()

    const reviewBtn = wrapper
      .findAll('.btn-card-secondary')
      .find((b) => b.text().includes('Đánh Giá'))
    await reviewBtn?.trigger('click')
    expect(wrapper.find('.code-badge').text()).toContain('#BK-OLD-2')
    await wrapper.find('.btn-submit').trigger('click')
    await flushPromises()

    expect(reviewSpy).toHaveBeenCalledWith('LEGACY-BK-OLD-2', { rating: 5, body: undefined })
  })

  it('renders empty state when there are no bookings', async () => {
    vi.spyOn(bookingApi, 'getMyReservations').mockResolvedValue({
      data: [],
    })

    const wrapper = mount(BookingView, {
      global: { plugins: [i18n] },
    })

    await flushPromises()

    expect(wrapper.find('[data-testid="booking-empty-state"]').exists()).toBe(true)
    expect(wrapper.find('.empty-title').text()).toContain('Không Tìm Thấy Lịch Đặt Sân')
  })
})
