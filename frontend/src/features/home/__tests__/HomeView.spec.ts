import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import HomeView from '../views/HomeView.vue'
import { i18n, setAppLocale } from '@/i18n'
import { useBookingStore } from '@/stores/booking'

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
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
    expect(text).not.toContain('home.availability.title')
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
})
