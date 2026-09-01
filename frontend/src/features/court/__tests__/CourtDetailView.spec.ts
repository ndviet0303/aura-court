import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import CourtDetailView from '../views/CourtDetailView.vue'
import { i18n } from '@/i18n'
import { useBookingStore } from '@/stores/booking'

vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: { slug: 'san-01-tieu-chuan', id: '1' },
    query: {},
  }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

describe('CourtDetailView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders court header, specs, amenities, and sticky booking card', async () => {
    const store = useBookingStore()
    store.courts = [
      {
        id: 1,
        name: 'Sân 01 - Tiêu Chuẩn',
        slug: 'san-01-tieu-chuan',
        venue: {
          id: 1,
          name: 'Aura Center Thảo Điền',
          slug: 'aura-center-thao-dien',
          address: '12 Quốc Hương, Thảo Điền, Quận 2',
          google_maps_url: 'https://maps.google.com/?q=Aura+Center',
          is_active: true,
          share_url: 'https://auracourt.vn/venues/aura-center-thao-dien',
        },
        description: 'Sân thi đấu chuyên nghiệp với mặt thảm cao cấp',
        image_url: '/aura/court-1.jpg',
        price_slots: [
          { id: 1, court_id: 1, weekday: 1, starts_at: '06:00', ends_at: '16:00', price: 120000 },
          { id: 2, court_id: 1, weekday: 1, starts_at: '16:00', ends_at: '22:00', price: 180000 },
        ],
        reviews_count: 42,
        reviews_avg_rating: 4.8,
      },
    ]

    const wrapper = mount(CourtDetailView, {
      global: {
        plugins: [i18n],
      },
    })

    expect(wrapper.find('.court-title').text()).toContain('Sân 01 - Tiêu Chuẩn')
    expect(wrapper.find('.court-title').text()).toContain('Aura Center Thảo Điền')
    expect(wrapper.find('.rating-display').text()).toContain('4.8')
    expect(wrapper.find('.specs-grid').exists()).toBe(true)
    expect(wrapper.findAll('.spec-card').length).toBe(4)
    expect(wrapper.findAll('.amenity-card').length).toBe(6)
    expect(wrapper.find('.court-reviews-section').exists()).toBe(true)
    expect(wrapper.find('.sticky-booking-card').exists()).toBe(true)
    expect(wrapper.find('.price-num').text()).toContain('120.000')
  })

  it('allows changing duration and updating estimated total', async () => {
    const store = useBookingStore()
    store.courts = [
      {
        id: 1,
        name: 'Sân 01',
        slug: 'san-01',
        venue: {
          id: 1,
          name: 'Aura Center',
          slug: 'aura-center',
          address: 'Quận 2',
          google_maps_url: null,
          is_active: true,
          share_url: '',
        },
        price_slots: [
          { id: 1, court_id: 1, weekday: 1, starts_at: '06:00', ends_at: '22:00', price: 100000 },
        ],
      },
    ]

    const wrapper = mount(CourtDetailView, {
      global: {
        plugins: [i18n],
      },
    })

    // Default is 1.0h -> 100.000
    expect(wrapper.find('.estimate-amount').text()).toContain('100.000')

    // Click 2.0h duration
    const durationBtns = wrapper.findAll('.duration-btn')
    expect(durationBtns.length).toBe(4)
    await durationBtns[2]?.trigger('click') // 2.0h
    expect(wrapper.find('.estimate-amount').text()).toContain('200.000')
  })

  it('opens and closes the photo gallery modal', async () => {
    const store = useBookingStore()
    store.courts = [
      {
        id: 1,
        name: 'Sân 01',
        slug: 'san-01',
        venue: {
          id: 1,
          name: 'Aura Center',
          slug: 'aura-center',
          address: 'Quận 2',
          google_maps_url: null,
          is_active: true,
          share_url: '',
        },
      },
    ]

    const wrapper = mount(CourtDetailView, {
      global: {
        plugins: [i18n],
      },
      attachTo: document.body,
    })

    expect(document.querySelector('.gallery-modal')).toBeNull()

    // Click main gallery
    await wrapper.find('.gallery-main').trigger('click')
    expect(document.querySelector('.gallery-modal')).not.toBeNull()

    // Click close button in modal
    const closeBtn = document.querySelector('.modal-close-btn') as HTMLElement
    closeBtn?.click()
    await wrapper.vm.$nextTick()
  })
})
