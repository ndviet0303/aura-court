import { describe, it, expect, beforeEach, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import HomeView from '../views/HomeView.vue'
import { i18n, setAppLocale } from '@/i18n'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
import { bookingApi } from '@/features/booking/api/bookingApi'
import type { Reservation } from '@/features/booking/types/reservation'
import type { Court } from '@/features/court/types/court'

const mockPush = vi.hoisted(() => vi.fn())

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush }),
  useRoute: () => ({ path: '/', query: {} }),
  RouterLink: {
    template: '<a><slot /></a>',
  },
}))

describe('HomeView i18n & rendering', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    setAppLocale('vi')
  })

  it('renders all home sections without raw untranslated translation keys in Vietnamese', async () => {
    const store = useBookingStore()
    store.courts = [
      {
        id: 1,
        name: 'Sân 1 - Thảm BWF Tiêu Chuẩn',
        slug: 'san-1',
        description: 'Sân cầu lông đạt chuẩn thi đấu BWF',
        price_slots: [{ id: 1, price: 120000, start_time: '08:00', end_time: '09:00' }],
      } as any,
    ]

    const wrapper = mount(HomeView, {
      global: {
        plugins: [i18n],
      },
    })

    const text = wrapper.text()

    // Assert that no raw home.* keys leak into the DOM
    expect(text).not.toContain('home.bookNow')
    expect(text).not.toContain('home.exploreAura')
    expect(text).not.toContain('home.open247')
    expect(text).not.toContain('home.openBadge')
    expect(text).not.toContain('home.search.court')
    expect(text).not.toContain('home.search.date')
    expect(text).not.toContain('home.search.timeSlot')
    expect(text).not.toContain('home.availability.summary')
    expect(text).not.toContain('home.availability.eyebrow')
    expect(text).not.toContain('home.features.title')

    // Assert Vietnamese translations appear properly
    expect(text).toContain('Đặt sân ngay')
    expect(text).toContain('Khám phá Aura')
    expect(text).toContain('24/7')
    expect(text).toContain('Mở cửa')
    expect(text).toContain('Sân thi đấu')
    expect(text).toContain('Ngày chơi')
    expect(text).toContain('Khung giờ')
    expect(text).toContain('Lịch sân trống trực quan')
    expect(text).toContain('Trải nghiệm thể thao chuẩn quốc tế')
  })

  it('renders properly in English when locale is switched to en', async () => {
    setAppLocale('en')
    const store = useBookingStore()
    store.courts = [
      {
        id: 1,
        name: 'Court 1 - BWF Standard',
        slug: 'court-1',
        description: 'BWF standard tournament court',
        price_slots: [{ id: 1, price: 120000, start_time: '08:00', end_time: '09:00' }],
      } as any,
    ]

    const wrapper = mount(HomeView, {
      global: {
        plugins: [i18n],
      },
    })

    const text = wrapper.text()

    // Assert English translations appear properly
    expect(text).toContain('Book Court Now')
    expect(text).toContain('Explore Aura')
    expect(text).toContain('Open Daily')
    expect(text).toContain('Sports Court')
    expect(text).toContain('Play Date')
    expect(text).toContain('Time Slot')
    expect(text).toContain('Live Availability')
    expect(text).toContain('International Standard Sports Experience')
  })

  it('checks out the selected slots and opens the reservation', async () => {
    const court = { id: 7, name: 'Sân 7', slug: 'san-7' } as Court
    const slot = {
      startsAt: '2099-09-22T17:00:00+07:00',
      endsAt: '2099-09-22T18:00:00+07:00',
      price: 120000,
      available: true,
    }
    vi.spyOn(bookingApi, 'getCourts').mockResolvedValue([court])
    vi.spyOn(bookingApi, 'getAvailability').mockResolvedValue([slot])
    const checkout = vi.spyOn(bookingApi, 'checkoutReservation').mockResolvedValue({
      reservation: { code: 'RSV-HOME-1', status: 'confirmed' } as Reservation,
    })
    useAuthStore().user = { id: 1, name: 'Player', email: 'p@example.com', role: 'customer' }

    const wrapper = mount(HomeView, {
      global: { plugins: [i18n], stubs: { LoyaltyPointSelector: true } },
    })
    await flushPromises()
    useBookingStore().toggle(slot)
    await flushPromises()
    await wrapper.find('.btn-floating-proceed').trigger('click')
    await flushPromises()

    // No loyalty selection: no key, so the client generates its own Idempotency-Key.
    expect(checkout).toHaveBeenCalledWith({ court_id: 7, slots: [slot.startsAt] }, undefined)
    expect(mockPush).toHaveBeenCalledWith({ name: 'reservations', params: { code: 'RSV-HOME-1' } })
  })

  describe('area filter and near me', () => {
    const courts = [
      {
        id: 1,
        name: 'Sân 1 - Thảm BWF',
        slug: 'san-1',
        venue: { district: 'Cầu Giấy', city: 'Hà Nội', latitude: 21.0306, longitude: 105.783 },
      },
      {
        id: 2,
        name: 'Sân 2 - Pickleball',
        slug: 'san-2',
        venue: { district: 'Tây Hồ', city: 'Hà Nội', latitude: 21.0655, longitude: 105.827 },
      },
    ] as Court[]

    function stubGeolocation(
      getCurrentPosition: (ok: PositionCallback, fail: PositionErrorCallback) => void,
    ) {
      Object.defineProperty(navigator, 'geolocation', {
        value: { getCurrentPosition },
        configurable: true,
      })
    }

    async function mountHome() {
      vi.spyOn(bookingApi, 'getCourts').mockResolvedValue(courts)
      vi.spyOn(bookingApi, 'getAvailability').mockResolvedValue([])
      const wrapper = mount(HomeView, {
        global: { plugins: [i18n], stubs: { LoyaltyPointSelector: true } },
      })
      await flushPromises()
      return wrapper
    }

    const chipTexts = (wrapper: Awaited<ReturnType<typeof mountHome>>) =>
      wrapper.findAll('.court-chip').map((chip) => chip.text())

    it('filters court chips by district and shows where each court is', async () => {
      const wrapper = await mountHome()
      expect(chipTexts(wrapper)).toEqual([
        'sports_tennisSân 1 - Thảm BWFCầu Giấy',
        'sports_tennisSân 2 - PickleballTây Hồ',
      ])

      const tayHo = wrapper.findAll('.area-pill').find((pill) => pill.text().startsWith('Tây Hồ'))
      await tayHo!.trigger('click')
      await flushPromises()

      expect(chipTexts(wrapper)).toEqual(['sports_tennisSân 2 - PickleballTây Hồ'])
      expect(wrapper.find('.court-chip.active').text()).toContain('Sân 2')
    })

    it('sorts courts nearest first and picks the nearest one when location is granted', async () => {
      stubGeolocation((ok) =>
        ok({ coords: { latitude: 21.06, longitude: 105.82 } } as GeolocationPosition),
      )
      const wrapper = await mountHome()

      await wrapper.find('.near-me').trigger('click')
      await flushPromises()

      const chips = chipTexts(wrapper)
      expect(chips[0]).toMatch(/Sân 2 - PickleballTây Hồ · \d+\.\d km/)
      expect(chips[1]).toMatch(/Sân 1 - Thảm BWFCầu Giấy · \d+\.\d km/)
      expect(wrapper.find('.court-chip.active').text()).toContain('Sân 2')
      expect(wrapper.find('.near-me').attributes('aria-pressed')).toBe('true')
    })

    it('explains how to re-enable location when permission is denied', async () => {
      stubGeolocation((_ok, fail) =>
        fail({ code: 1, PERMISSION_DENIED: 1 } as GeolocationPositionError),
      )
      const wrapper = await mountHome()

      await wrapper.find('.near-me').trigger('click')
      await flushPromises()

      expect(wrapper.find('.locate-error').text()).toContain('Bạn đã chặn quyền vị trí')
      expect(wrapper.find('.court-chip.active').text()).toContain('Sân 1')
    })
  })
})
