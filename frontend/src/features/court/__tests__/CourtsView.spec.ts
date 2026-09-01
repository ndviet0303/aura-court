import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import CourtsView from '../CourtsView.vue'
import { useBookingStore } from '@/stores/booking'
import { api } from '@/core/api/client'

vi.mock('vue-router', () => ({
  useRoute: () => ({
    query: {},
    params: {},
  }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn().mockResolvedValue([]),
    post: vi.fn().mockResolvedValue({}),
  },
  apiV2: {
    get: vi.fn().mockResolvedValue([]),
    post: vi.fn().mockResolvedValue({}),
  },
  errorMessage: (_err: unknown, fallback: string) => fallback,
}))

describe('CourtsView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.mocked(api.get).mockResolvedValue([])
  })

  it('renders skeleton loader when courts are loading and list is empty', async () => {
    const store = useBookingStore()
    store.courts = []
    store.courtsLoading = true

    const wrapper = mount(CourtsView)

    expect(wrapper.find('[data-testid="courts-skeleton"]').exists()).toBe(true)
    const skeletonCards = wrapper.findAll('.skeleton-court-card')
    expect(skeletonCards.length).toBe(6)
    expect(wrapper.find('[data-testid="courts-grid"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="courts-empty-state"]').exists()).toBe(false)
  })

  it('renders courts grid when data is loaded', async () => {
    const store = useBookingStore()
    store.courtsLoading = false
    store.courts = [
      {
        id: 1,
        name: 'Sân 01 - Tiêu Chuẩn Quốc Tế',
        slug: 'san-01',
        venue: {
          id: 1,
          name: 'Aura Center Thảo Điền',
          slug: 'aura-center-thao-dien',
          address: '12 Quốc Hương, Thảo Điền, Quận 2',
          google_maps_url: null,
          is_active: true,
          share_url: '',
        },
        image_url: '/court1.jpg',
        price_slots: [
          { id: 1, court_id: 1, weekday: 1, starts_at: '06:00', ends_at: '22:00', price: 150000 },
        ],
      },
      {
        id: 2,
        name: 'Sân 02 - VIP Lounge',
        slug: 'san-02',
        venue: {
          id: 2,
          name: 'Aura Premium Landmark',
          slug: 'aura-premium-landmark',
          address: 'Landmark 81, Bình Thạnh',
          google_maps_url: null,
          is_active: true,
          share_url: '',
        },
        image_url: '/court2.jpg',
        price_slots: [
          { id: 2, court_id: 2, weekday: 1, starts_at: '06:00', ends_at: '22:00', price: 200000 },
        ],
      },
    ]

    const wrapper = mount(CourtsView)

    expect(wrapper.find('[data-testid="courts-skeleton"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="courts-grid"]').exists()).toBe(true)
    expect(wrapper.findAll('.court-card-item').length).toBe(2)
    expect(wrapper.text()).toContain('Sân 01 - Tiêu Chuẩn Quốc Tế')
    expect(wrapper.text()).toContain('Sân 02 - VIP Lounge')
  })

  it('renders empty state when filter matches nothing', async () => {
    const store = useBookingStore()
    store.courtsLoading = false
    store.courts = [
      {
        id: 1,
        name: 'Sân 01',
        slug: 'san-01',
        venue: { id: 1, name: 'Aura Thảo Điền', slug: 'aura-thao-dien', address: 'Quận 2', google_maps_url: null, is_active: true, share_url: '' },
      },
    ]

    const wrapper = mount(CourtsView)

    const searchInput = wrapper.find('.search-input')
    await searchInput.setValue('Tên sân không tồn tại')

    expect(wrapper.find('[data-testid="courts-empty-state"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Không tìm thấy sân đấu phù hợp')
  })

  it('filters courts when selecting a venue from SelectDropdown', async () => {
    const store = useBookingStore()
    store.courtsLoading = false
    store.courts = [
      {
        id: 1,
        name: 'Sân 01',
        slug: 'san-01',
        venue: { id: 1, name: 'Aura Thảo Điền', slug: 'aura-thao-dien', address: 'Quận 2', google_maps_url: null, is_active: true, share_url: '' },
      },
      {
        id: 2,
        name: 'Sân 02',
        slug: 'san-02',
        venue: { id: 2, name: 'Aura Landmark', slug: 'aura-landmark', address: 'Bình Thạnh', google_maps_url: null, is_active: true, share_url: '' },
      },
    ]

    const wrapper = mount(CourtsView)

    // Open dropdown and click Aura Landmark
    const dropdownTrigger = wrapper.find('.custom-dropdown-trigger')
    await dropdownTrigger.trigger('click')

    const items = wrapper.findAll('.dropdown-item')
    const landmarkItem = items.find((it) => it.text().includes('Aura Landmark'))
    await landmarkItem?.trigger('click')

    expect(wrapper.findAll('.court-card-item').length).toBe(1)
    expect(wrapper.text()).toContain('Sân 02')
    expect(wrapper.text()).not.toContain('Sân 01')
  })
})
