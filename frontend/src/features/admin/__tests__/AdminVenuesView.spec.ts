import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminVenuesView from '../views/AdminVenuesView.vue'
import { i18n } from '@/i18n'
import { api, apiV2 } from '@/core/api/client'

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/admin/venues' }),
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
  const mockVenues = [
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

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url === '/admin/venues') return mockVenues
      if (url.startsWith('/admin/sport-zones')) return []
      return []
    })
    vi.mocked(apiV2.get).mockImplementation(async (url: string) => {
      if (url === '/admin/venues') {
        throw new Error('404 Not Found: /api/v2/admin/venues is undefined')
      }
      return []
    })
  })

  it('renders header, KPI metrics and venue cards properly', async () => {
    const wrapper = mount(AdminVenuesView, {
      global: {
        plugins: [i18n],
        stubs: {
          RouterLink: {
            template: '<a :href="$attrs.to"><slot /></a>',
          },
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Quản lý Cụm sân & Cơ sở')
    expect(wrapper.text()).toContain('Aura Center')
    expect(wrapper.text()).toContain('Riverside Club')
    expect(wrapper.text()).toContain('District 1 Hub')
    expect(wrapper.text()).toContain('Đang mở cửa')
    expect(wrapper.text()).toContain('Đang bảo trì / Tạm dừng')

    // KPI total venues count
    expect(wrapper.text()).toContain('3')
    // Total courts: 12 + 8 + 16 = 36
    expect(wrapper.text()).toContain('36')
  })

  it('filters venues based on search query', async () => {
    const wrapper = mount(AdminVenuesView, {
      global: {
        plugins: [i18n],
        stubs: {
          RouterLink: {
            template: '<a :href="$attrs.to"><slot /></a>',
          },
        },
      },
    })

    await flushPromises()

    const searchInput = wrapper.find('.venues-search-input')
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
        stubs: {
          RouterLink: {
            template: '<a :href="$attrs.to"><slot /></a>',
          },
        },
      },
    })

    await flushPromises()

    const switches = wrapper.findAll('.switch-control-wrap input')
    expect(switches.length).toBeGreaterThan(0)

    await switches[0]?.trigger('change')
    await flushPromises()

    expect(api.patch).toHaveBeenCalledWith('/admin/venues/1', { is_active: false })
  })

  it('opens QR modal when clicking QR button', async () => {
    const wrapper = mount(AdminVenuesView, {
      global: {
        plugins: [i18n],
        stubs: {
          RouterLink: {
            template: '<a :href="$attrs.to"><slot /></a>',
          },
        },
      },
    })

    await flushPromises()

    const qrBtns = wrapper.findAll('.btn-action-sm')
    const qrBtn = qrBtns.find((b) => b.text().includes('Mã QR Đặt Sân'))
    expect(qrBtn).toBeDefined()

    await qrBtn?.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Mã QR Cơ Sở: Aura Center')
    expect(wrapper.text()).toContain('Tải Mã QR (PNG)')
  })

  it('calls v1 /admin/venues endpoint and never the undefined v2 endpoint', async () => {
    mount(AdminVenuesView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/venues')
    expect(apiV2.get).not.toHaveBeenCalledWith('/admin/venues')
  })
})
