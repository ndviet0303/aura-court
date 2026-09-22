import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/features/auth/api/authApi'
import { api, apiV2 } from '@/core/api/client'
import { i18n } from '@/i18n'
import OrganizationOwnerView from '../views/OrganizationOwnerView.vue'
import { useOrganizationOwner } from '../composables/useOrganizationOwner'

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
  errorMessage: (_err: unknown, fallback: string) => fallback,
}))

describe('Organization Owner & Scoped Access (Task 3.1 & 3.4)', () => {
  const mockOrg = {
    id: 1,
    name: 'Aura Tenant Organization',
    slug: 'aura-tenant-org',
    status: 'active',
    venues: [
      {
        id: 101,
        name: 'Tenant Court Center',
        address: '100 Tenant Way',
        is_active: true,
        organization_id: 1,
        courts: [{ id: 1, name: 'Court 1' }, { id: 2, name: 'Court 2' }],
      },
    ],
    members: [
      { id: 1, user_id: 10, role: 'organization_owner', status: 'active', user: { name: 'Owner User' } },
    ],
  }

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.spyOn(authApi, 'me').mockImplementation(async () => {
      const auth = useAuthStore()
      if (auth.user) return auth.user
      throw new Error('Unauthenticated')
    })

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url === '/organizations') {
        return { organizations: [mockOrg] }
      }
      if (url === '/organizations/1') {
        return { organization: mockOrg }
      }
      if (url === '/organizations/1/members') {
        return { members: mockOrg.members }
      }
      return []
    })

    await router.push('/courts')
  })

  describe('Route Guards: Owner vs Cashier vs Customer', () => {
    it('allows organization_owner into /admin/organization and /pos, but denies /admin/venues', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 10,
        name: 'Owner User',
        email: 'owner@tenant.test',
        role: 'customer', // Preserved customer global role
        onboarded: true,
        organizations: [
          {
            organization_id: 1,
            organization_name: 'Aura Tenant Org',
            role: 'organization_owner',
            status: 'active',
          },
        ],
        capabilities: {
          is_platform_admin: false,
          can_manage_venues: true,
          active_organization_id: 1,
        },
      }
      auth.loaded = true

      // 1. Owner can access /admin/organization
      await router.push('/admin/organization')
      expect(router.currentRoute.value.path).toBe('/admin/organization')

      // 2. Owner can access /pos (redirects to /admin/pos)
      await router.push('/pos')
      expect(router.currentRoute.value.path).toBe('/admin/pos')

      // 3. Owner CANNOT access platform-admin /admin/venues
      await router.push('/admin/venues')
      expect(router.currentRoute.value.path).toBe('/')

      // 4. Owner CANNOT access platform-admin /admin/manage
      await router.push('/admin/manage')
      expect(router.currentRoute.value.path).toBe('/')
    })

    it('allows cashier into /admin/pos and /pos, but denies /admin/organization and /admin/venues', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 20,
        name: 'Cashier User',
        email: 'cashier@tenant.test',
        role: 'customer',
        onboarded: true,
        organizations: [
          {
            organization_id: 1,
            organization_name: 'Aura Tenant Org',
            role: 'cashier',
            status: 'active',
          },
        ],
        capabilities: {
          is_platform_admin: false,
          active_organization_id: 1,
        },
      }
      auth.loaded = true

      // 1. Cashier can access /pos
      await router.push('/pos')
      expect(router.currentRoute.value.path).toBe('/admin/pos')

      // 2. Cashier CANNOT access owner-only /admin/organization
      await router.push('/admin/organization')
      expect(router.currentRoute.value.path).toBe('/')

      // 3. Cashier CANNOT access platform-admin /admin/venues
      await router.push('/admin/venues')
      expect(router.currentRoute.value.path).toBe('/')
    })

    it('denies pure customer without organization membership from entering /admin/organization or /pos', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 99,
        name: 'Pure Customer',
        email: 'customer@aura.test',
        role: 'customer',
        onboarded: true,
        organizations: [],
      }
      auth.loaded = true

      await router.push('/admin/organization')
      expect(router.currentRoute.value.path).toBe('/')

      await router.push('/pos')
      expect(router.currentRoute.value.path).toBe('/')
    })
  })

  describe('useOrganizationOwner & OrganizationOwnerView', () => {
    it('loads same-organization data without calling platform-admin /admin/venues', async () => {
      const { currentOrg, loadOrgData, error } = useOrganizationOwner()
      await loadOrgData(1)

      expect(api.get).not.toHaveBeenCalledWith('/admin/venues')
      expect(apiV2.get).not.toHaveBeenCalledWith('/admin/venues')
      expect(currentOrg.value?.name).toBe('Aura Tenant Organization')
      expect(currentOrg.value?.venues?.length).toBe(1)
      expect(currentOrg.value?.venues?.[0]?.name).toBe('Tenant Court Center')
      expect(error.value).toBe('')
    })

    it('renders organization owner view with loaded data', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 10,
        name: 'Owner User',
        email: 'owner@tenant.test',
        role: 'customer',
        onboarded: true,
        organizations: [
          {
            organization_id: 1,
            role: 'organization_owner',
            status: 'active',
          },
        ],
      }
      auth.loaded = true

      const wrapper = mount(OrganizationOwnerView, {
        global: { plugins: [i18n] },
      })
      await flushPromises()

      expect(wrapper.text()).toContain('Aura Tenant Organization')
      expect(api.get).not.toHaveBeenCalledWith('/admin/venues')
    })
  })
})
