import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminVenuesView from '../views/AdminVenuesView.vue'
import { i18n } from '@/i18n'
import { api } from '@/core/api/client'

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/admin/venues' }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

vi.mock('qrcode', () => ({
  default: {
    toDataURL: vi.fn().mockResolvedValue('data:image/png;base64,mockqr'),
  },
}))

vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  apiV2: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  errorMessage: (err: unknown, fallback: string) => fallback,
}))

describe('AdminVenuesView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url === '/admin/venues') {
        return [
          {
            id: 1,
            name: 'Aura Center',
            slug: 'aura-center',
            address: '142 Wellness Blvd, Downtown',
            google_maps_url: 'https://maps.google.com/?q=142+Wellness+Blvd',
            is_active: true,
            share_url: 'https://auracourt.vn/venues/aura-center/directions',
            courts_count: 12,
          },
          {
            id: 2,
            name: 'Riverside Club',
            slug: 'riverside-club',
            address: '88 Waterway Point, East Side',
            google_maps_url: 'https://maps.google.com/?q=88+Waterway+Point',
            is_active: true,
            share_url: 'https://auracourt.vn/venues/riverside-club/directions',
            courts_count: 8,
          },
          {
            id: 3,
            name: 'District 1 Hub',
            slug: 'district-1-hub',
            address: '404 Innovation Sector, North',
            google_maps_url: 'https://maps.google.com/?q=404+Innovation+Sector',
            is_active: false,
            share_url: 'https://auracourt.vn/venues/district-1-hub/directions',
            courts_count: 16,
          },
        ]
      }
      if (url.startsWith('/admin/sport-zones')) {
        return [
          {
            id: 10,
            venue_id: 1,
            name: 'Khu Cầu Lông VIP',
            sport_type: 'badminton',
            slot_duration: 60,
            description: 'Sàn gỗ BWF',
            is_active: true,
            courts_count: 8,
          },
        ]
      }
      return []
    })
  })

  it('renders header, KPI metrics and venue cards properly', async () => {
    const wrapper = mount(AdminVenuesView, {
      global: {
        plugins: [i18n],
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Quản lý Cụm sân & Cơ sở')
    expect(wrapper.text()).toContain('Aura Center')
    expect(wrapper.text()).toContain('Riverside Club')
    expect(wrapper.text()).toContain('District 1 Hub')
    expect(wrapper.text()).toContain('ACTIVE')
    expect(wrapper.text()).toContain('MAINTENANCE')

    // KPI total venues count
    expect(wrapper.text()).toContain('3')
    // Total courts: 12 + 8 + 16 = 36
    expect(wrapper.text()).toContain('36')
  })

  it('filters venues based on search query', async () => {
    const wrapper = mount(AdminVenuesView, {
      global: {
        plugins: [i18n],
      },
    })

    await flushPromises()

    const searchInput = wrapper.find('.search-input')
    await searchInput.setValue('Riverside')

    expect(wrapper.text()).toContain('Riverside Club')
    expect(wrapper.text()).not.toContain('Aura Center')
    expect(wrapper.text()).not.toContain('District 1 Hub')
  })

  it('toggles venue active status via switch', async () => {
    vi.mocked(api.patch).mockResolvedValue({})

    const wrapper = mount(AdminVenuesView, {
      global: {
        plugins: [i18n],
      },
    })

    await flushPromises()

    const switches = wrapper.findAll('.switch-control-wrap input')
    expect(switches.length).toBeGreaterThan(0)

    await switches[0]?.trigger('change')
    await flushPromises()

    expect(api.patch).toHaveBeenCalledWith('/admin/venues/1', { is_active: false })
  })
})
