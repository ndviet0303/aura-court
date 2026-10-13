import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/features/auth/api/authApi'
import { api } from '@/core/api/client'
import { i18n } from '@/i18n'
import AdminVenuesView from '../views/AdminVenuesView.vue'
import AdminManageView from '../views/AdminManageView.vue'
import CourtFormModal from '../components/manage/CourtFormModal.vue'
import AdminStaffView from '../views/AdminStaffView.vue'
import AdminScheduleView from '../views/AdminScheduleView.vue'
import AdminShiftReconciliationView from '../views/AdminShiftReconciliationView.vue'
import type { Venue } from '@/features/court/types/court'

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
  errorMessage: (_err: unknown, fallback: string) => fallback,
}))

vi.mock('@/features/pos/api/posApi', () => ({
  posApi: {
    getActiveShift: vi.fn().mockResolvedValue(null),
    getShifts: vi.fn().mockResolvedValue([]),
    openShift: vi.fn().mockResolvedValue({ id: 1 }),
    closeShift: vi.fn().mockResolvedValue({ id: 1 }),
    reviewShift: vi.fn().mockResolvedValue({ id: 1 }),
  },
}))

describe('Role-Aware Route & View Integration (Task 2.4)', () => {
  const mockVenues: Venue[] = [
    {
      id: 1,
      name: 'Aura Downtown Arena',
      slug: 'aura-downtown-arena',
      address: '142 Wellness Blvd, Downtown',
      google_maps_url: 'https://maps.google.com/?q=142+Wellness+Blvd',
      is_active: true,
      share_url: 'https://auracourt.vn/venues/aura-downtown-arena/directions',
      courts_count: 6,
    },
    {
      id: 2,
      name: 'Riverside Club',
      slug: 'riverside-club',
      address: '88 Waterway Point, East Side',
      google_maps_url: 'https://maps.google.com/?q=88+Waterway+Point',
      is_active: true,
      share_url: 'https://auracourt.vn/venues/riverside-club/directions',
      courts_count: 4,
    },
  ]

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.spyOn(authApi, 'me').mockImplementation(async () => {
      const auth = useAuthStore()
      if (auth.user) return auth.user
      throw new Error('Unauthenticated')
    })

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url === '/admin/venues') return mockVenues
      if (url === '/admin/courts')
        return [
          {
            id: 1,
            name: 'Court 1',
            venue_id: 1,
            venue: mockVenues[0],
            is_active: true,
            blocks: [],
          },
        ]
      if (url.startsWith('/admin/sport-zones')) return []
      if (url === '/admin/refunds') return []
      if (url === '/admin/coupons') return []
      if (url === '/admin/audit-logs') return { data: [] }
      if (url.includes('/members'))
        return {
          members: [{ id: 10, user_id: 100, name: 'Staff John', role: 'Staff', venue_id: 1 }],
        }
      return []
    })

    // Reset router navigation to a neutral page
    await router.push('/courts')
  })

  describe('Route Guards: Role-Based Access Control', () => {
    it('redirects unauthenticated users attempting to access /admin/venues to login', async () => {
      const auth = useAuthStore()
      auth.user = null
      auth.loaded = true

      await router.push('/admin/venues')

      expect(router.currentRoute.value.path).toBe('/login')
      expect(router.currentRoute.value.query.redirect).toBe('/admin/venues')
    })

    it('redirects unauthenticated users attempting to access /admin/manage to login', async () => {
      const auth = useAuthStore()
      auth.user = null
      auth.loaded = true

      await router.push('/admin/manage')

      expect(router.currentRoute.value.path).toBe('/login')
      expect(router.currentRoute.value.query.redirect).toBe('/admin/manage')
    })

    it('denies customer role access to /admin/venues and redirects to home', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 1,
        name: 'Regular Customer',
        email: 'customer@aura.com',
        role: 'customer',
        onboarded: true,
      }
      auth.loaded = true

      await router.push('/admin/venues')

      expect(router.currentRoute.value.path).toBe('/')
    })

    it('denies customer role access to /admin/manage and redirects to home', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 1,
        name: 'Regular Customer',
        email: 'customer@aura.com',
        role: 'customer',
        onboarded: true,
      }
      auth.loaded = true

      await router.push('/admin/manage')

      expect(router.currentRoute.value.path).toBe('/')
    })

    it('denies customer role access to /admin/schedule and redirects to home', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 1,
        name: 'Regular Customer',
        email: 'customer@aura.com',
        role: 'customer',
        onboarded: true,
      }
      auth.loaded = true

      await router.push('/admin/schedule')

      expect(router.currentRoute.value.path).toBe('/')
    })

    it('permits authorized admin role to navigate to /admin/venues', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 2,
        name: 'Platform Admin',
        email: 'admin@aura.com',
        role: 'admin',
        onboarded: true,
      }
      auth.loaded = true

      await router.push('/admin/venues')

      expect(router.currentRoute.value.path).toBe('/admin/venues')
    })

    it('permits authorized admin role to navigate to /admin/manage', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 2,
        name: 'Platform Admin',
        email: 'admin@aura.com',
        role: 'admin',
        onboarded: true,
      }
      auth.loaded = true

      await router.push('/admin/manage')

      expect(router.currentRoute.value.path).toBe('/admin/manage')
    })

    it('permits authorized admin role to navigate to /admin/schedule', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 2,
        name: 'Platform Admin',
        email: 'admin@aura.com',
        role: 'admin',
        onboarded: true,
      }
      auth.loaded = true

      await router.push('/admin/schedule')

      expect(router.currentRoute.value.path).toBe('/admin/schedule')
    })
  })

  describe('View Rendering with Authorized Admin & Venue Contract', () => {
    const adminUser = {
      id: 2,
      name: 'Platform Admin',
      email: 'admin@aura.com',
      role: 'admin',
      onboarded: true,
    }

    it('AdminVenuesView loads venues and renders venue list properly', async () => {
      const auth = useAuthStore()
      auth.user = adminUser
      auth.loaded = true

      const wrapper = mount(AdminVenuesView, {
        global: {
          plugins: [i18n],
          stubs: {
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })
      await flushPromises()

      expect(api.get).toHaveBeenCalledWith('/admin/venues')
      expect(wrapper.text()).toContain('Aura Downtown Arena')
      expect(wrapper.text()).toContain('Riverside Club')
      expect(wrapper.find('.alert-pill-error').exists()).toBe(false)
    })

    it('AdminManageView loads venues and renders management content', async () => {
      const auth = useAuthStore()
      auth.user = adminUser
      auth.loaded = true

      const wrapper = mount(AdminManageView, {
        global: {
          plugins: [i18n],
          stubs: {
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })
      await flushPromises()

      expect(api.get).toHaveBeenCalledWith('/admin/venues')
      expect(wrapper.find('.alert-pill-error').exists()).toBe(false)
    })

    it('AdminManageView keeps court form edits in the view and saves them', async () => {
      const auth = useAuthStore()
      auth.user = adminUser
      auth.loaded = true
      vi.mocked(api.post).mockResolvedValueOnce({ id: 2 })

      const wrapper = mount(AdminManageView, {
        global: {
          plugins: [i18n],
          stubs: {
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })
      await flushPromises()
      const modal = wrapper.findComponent(CourtFormModal)

      // Cancel saves nothing, and reopening starts from a fresh form.
      await wrapper.find('.add-court-btn').trigger('click')
      await modal.find('input[type="text"]').setValue('Draft court')
      await modal.find('input[type="number"]').setValue('5')
      await modal.find('input[type="number"]').setValue('')
      expect(modal.emitted('update:capacity')).toEqual([[5], [null]])
      await modal.find('.btn-cancel').trigger('click')
      expect(modal.find('.modal-backdrop').exists()).toBe(false)
      await wrapper.find('.add-court-btn').trigger('click')
      expect(modal.find<HTMLInputElement>('input[type="text"]').element.value).toBe('')

      await modal.find('input[type="text"]').setValue('Court 9')
      await modal.find('input[type="number"]').setValue('8')
      await modal.find('input[type="checkbox"]').setValue(false)
      const tag = modal.find('.tag-btn')
      const tagLabel = tag.text().replace(/^\+\s*/, '')
      await tag.trigger('click')
      await modal.find('.preset-thumb-btn').trigger('click')
      await modal.find('.btn-primary').trigger('click')
      await flushPromises()

      expect(api.post).toHaveBeenCalledWith('/admin/courts', {
        name: 'Court 9',
        venue_id: 1,
        sport_zone_id: null,
        surface_type: null,
        capacity: 8,
        is_active: false,
        description: tagLabel,
        image_url: '/courts/football-mini-turf.jpg',
      })
    })

    it('AdminStaffView loads venues and associates staff with venues', async () => {
      const auth = useAuthStore()
      auth.user = adminUser
      auth.loaded = true

      const wrapper = mount(AdminStaffView, {
        global: {
          plugins: [i18n],
        },
      })
      await flushPromises()

      expect(api.get).toHaveBeenCalledWith('/admin/venues')
      expect(wrapper.text()).toContain('Staff John')
      expect(wrapper.find('.alert-error').exists()).toBe(false)
    })

    it('AdminScheduleView loads venues and renders schedule view', async () => {
      const auth = useAuthStore()
      auth.user = adminUser
      auth.loaded = true

      const wrapper = mount(AdminScheduleView, {
        global: {
          plugins: [i18n],
        },
      })
      await flushPromises()

      expect(api.get).toHaveBeenCalledWith('/admin/venues')
      expect(wrapper.find('.alert-pill-error').exists()).toBe(false)
    })

    it('AdminShiftReconciliationView loads venues', async () => {
      const auth = useAuthStore()
      auth.user = adminUser
      auth.loaded = true

      mount(AdminShiftReconciliationView, {
        global: {
          plugins: [i18n],
        },
      })
      await flushPromises()

      expect(api.get).toHaveBeenCalledWith('/admin/venues')
    })
  })

  describe('Surfaced Request Failures: Honest Error Rendering', () => {
    const adminUser = {
      id: 2,
      name: 'Platform Admin',
      email: 'admin@aura.com',
      role: 'admin',
      onboarded: true,
    }

    it('AdminVenuesView surfaces error when /admin/venues fails with 500 error', async () => {
      const auth = useAuthStore()
      auth.user = adminUser
      auth.loaded = true

      vi.mocked(api.get).mockImplementation(async (url: string) => {
        if (url === '/admin/venues') throw new Error('500 Internal Server Error')
        return []
      })

      const wrapper = mount(AdminVenuesView, {
        global: {
          plugins: [i18n],
          stubs: {
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })
      await flushPromises()

      expect(wrapper.find('.alert-pill-error').exists()).toBe(true)
      expect(wrapper.find('.alert-pill-error').text()).not.toBe('')
    })

    it('AdminManageView surfaces error when /admin/venues fails', async () => {
      const auth = useAuthStore()
      auth.user = adminUser
      auth.loaded = true

      vi.mocked(api.get).mockImplementation(async (url: string) => {
        if (url === '/admin/venues') throw new Error('500 Internal Server Error')
        return []
      })

      const wrapper = mount(AdminManageView, {
        global: {
          plugins: [i18n],
          stubs: {
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })
      await flushPromises()

      expect(wrapper.find('.alert-pill-error').exists()).toBe(true)
      expect(wrapper.find('.alert-pill-error').text()).not.toBe('')
    })

    it('AdminStaffView surfaces error when /admin/venues fails', async () => {
      const auth = useAuthStore()
      auth.user = adminUser
      auth.loaded = true

      vi.mocked(api.get).mockImplementation(async (url: string) => {
        if (url === '/admin/venues') throw new Error('500 Internal Server Error')
        return []
      })

      const wrapper = mount(AdminStaffView, {
        global: {
          plugins: [i18n],
        },
      })
      await flushPromises()

      expect(wrapper.find('.alert-error').exists()).toBe(true)
      expect(wrapper.find('.alert-error').text()).not.toBe('')
    })

    it('AdminScheduleView surfaces error when /admin/venues fails', async () => {
      const auth = useAuthStore()
      auth.user = adminUser
      auth.loaded = true

      vi.mocked(api.get).mockImplementation(async (url: string) => {
        if (url === '/admin/venues') throw new Error('500 Internal Server Error')
        return []
      })

      const wrapper = mount(AdminScheduleView, {
        global: {
          plugins: [i18n],
        },
      })
      await flushPromises()

      expect(wrapper.find('.alert-pill-error').exists()).toBe(true)
      expect(wrapper.find('.alert-pill-error').text()).not.toBe('')
    })

    it('AdminShiftReconciliationView surfaces error when /admin/venues fails', async () => {
      const auth = useAuthStore()
      auth.user = adminUser
      auth.loaded = true

      vi.mocked(api.get).mockImplementation(async (url: string) => {
        if (url === '/admin/venues') throw new Error('500 Internal Server Error')
        return []
      })

      const wrapper = mount(AdminShiftReconciliationView, {
        global: {
          plugins: [i18n],
        },
      })
      await flushPromises()

      expect(wrapper.find('.alert-error').exists()).toBe(true)
      expect(wrapper.find('.alert-error').text()).not.toBe('')
    })
  })
})
