import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import PosView from '../views/PosView.vue'
import { i18n } from '@/i18n'
import { posApi } from '../api/posApi'
import { api } from '@/core/api/client'
import { useAuthStore } from '@/stores/auth'
import { useTenantCapabilities } from '@/features/admin/composables/useTenantCapabilities'

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {}, params: {} }),
  useRouter: () => ({ push: vi.fn() }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
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

vi.mock('../api/posApi', () => ({
  posApi: {
    getProducts: vi.fn(),
    createProduct: vi.fn(),
    adjustStock: vi.fn(),
    completeOrder: vi.fn(),
    getShifts: vi.fn(),
    getActiveShift: vi.fn(),
    openShift: vi.fn(),
    getVenues: vi.fn().mockResolvedValue([]),
    closeShift: vi.fn(),
    reviewShift: vi.fn(),
    getActiveReservations: vi.fn().mockResolvedValue([]),
    searchCustomers: vi.fn().mockResolvedValue([]),
  },
}))

describe('POS Capabilities & Scoped Access Integration (Tasks 3.2 & 3.3)', () => {
  const mockProducts = [
    {
      id: 1,
      sku: 'PROD-1',
      name: 'Nước suối Aquafina 500ml',
      category: 'drinks',
      price: 10000,
      stock_quantity: 50,
      unit: 'chai',
      is_rental: false,
      is_service: false,
    },
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    const { resetCapabilities } = useTenantCapabilities()
    resetCapabilities()

    const auth = useAuthStore()
    auth.user = {
      id: 10,
      name: 'Cashier John',
      email: 'cashier@aura.test',
      role: 'customer',
      onboarded: true,
      organizations: [
        {
          organization_id: 1,
          organization_name: 'Pilot Org',
          role: 'staff',
          status: 'active',
        },
      ],
      capabilities: {
        is_platform_admin: false,
        active_organization_id: 1,
      },
    }
    auth.loaded = true

    vi.mocked(posApi.getProducts).mockResolvedValue(mockProducts)
    vi.mocked(posApi.getActiveShift).mockResolvedValue(null)
  })

  it('resolves capabilities before loading products and active shift', async () => {
    const callOrder: string[] = []

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url.includes('capabilities')) {
        callOrder.push('capabilities')
        return {
          organization_id: 1,
          capabilities: { tenant_pos_and_wallet: true },
          role: 'staff',
          is_platform_admin: false,
        }
      }
      return []
    })

    vi.mocked(posApi.getProducts).mockImplementation(async () => {
      callOrder.push('products')
      return mockProducts
    })

    vi.mocked(posApi.getActiveShift).mockImplementation(async () => {
      callOrder.push('shift')
      return null
    })

    mount(PosView, {
      global: { plugins: [i18n] },
    })

    await flushPromises()

    expect(callOrder[0]).toBe('capabilities')
    expect(callOrder).toContain('products')
    expect(callOrder).toContain('shift')
  })

  it('displays retryable error banner when capability fetch fails and avoids concluding POS is disabled', async () => {
    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url.includes('capabilities')) {
        throw new Error('500 Internal Server Error')
      }
      return []
    })

    const wrapper = mount(PosView, {
      global: { plugins: [i18n] },
    })
    await flushPromises()

    // 1. Error banner is shown
    const errorBanner = wrapper.find('.pos-error-banner')
    expect(errorBanner.exists()).toBe(true)

    // 2. Disabled banner is NOT shown (not treated as definitive disabled)
    const disabledBanner = wrapper.find('.pos-disabled-banner:not(.pos-error-banner)')
    expect(disabledBanner.exists()).toBe(false)

    // 3. Products were not loaded because capability resolution failed
    expect(posApi.getProducts).not.toHaveBeenCalled()
  })

  it('displays disabled banner and blocks checkout when POS capability is disabled', async () => {
    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url.includes('capabilities')) {
        return {
          organization_id: 2,
          capabilities: { tenant_pos_and_wallet: false },
          role: 'staff',
          is_platform_admin: false,
        }
      }
      return []
    })
    const wrapper = mount(PosView, {
      global: { plugins: [i18n] },
    })
    await flushPromises()
    // 1. Disabled banner is displayed
    const disabledBanner = wrapper.find('.pos-disabled-banner')
    expect(disabledBanner.exists()).toBe(true)

    // 2. Active shift is not checked
    expect(posApi.getActiveShift).not.toHaveBeenCalled()

    // 3. Checkout button is disabled
    const checkoutBtn = wrapper.find('.checkout-submit-btn')
    expect(checkoutBtn.attributes('disabled')).toBeDefined()
  })

  it('discards stale capability responses when organization context switches', async () => {
    const { fetchCapabilities, isPosEnabled } = useTenantCapabilities()

    const { promise: slowFirstPromise, resolve: resolveFirstCall } = (
      Promise as any
    ).withResolvers()
    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url.includes('/organizations/1/capabilities')) {
        // Slow response from Org 1 (POS enabled)
        await slowFirstPromise
        return {
          organization_id: 1,
          capabilities: { tenant_pos_and_wallet: true },
          role: 'staff',
          is_platform_admin: false,
        }
      }
      if (url.includes('/organizations/2/capabilities')) {
        // Fast response from Org 2 (POS disabled)
        return {
          organization_id: 2,
          capabilities: { tenant_pos_and_wallet: false },
          role: 'staff',
          is_platform_admin: false,
        }
      }
      return []
    })

    // 1. Start fetching for Org 1 (in-flight)
    const org1Promise = fetchCapabilities(1)

    // 2. User quickly switches to Org 2 and it finishes first
    await fetchCapabilities(2)
    expect(isPosEnabled.value).toBe(false)

    // 3. Org 1 late response finally resolves
    resolveFirstCall!({})
    await org1Promise

    // Org 1 stale response MUST NOT overwrite Org 2's false status!
    expect(isPosEnabled.value).toBe(false)
  })

  it('resets capabilities on logout and clears context', async () => {
    const { fetchCapabilities, resetCapabilities, isPosEnabled, currentOrgId } =
      useTenantCapabilities()

    vi.mocked(api.get).mockResolvedValue({
      organization_id: 5,
      capabilities: { tenant_pos_and_wallet: true },
      role: 'organization_owner',
      is_platform_admin: false,
    })

    await fetchCapabilities(5)
    expect(isPosEnabled.value).toBe(true)
    expect(currentOrgId.value).toBe(5)

    // Logout
    resetCapabilities()
    expect(isPosEnabled.value).toBe(false)
    expect(currentOrgId.value).toBe(null)
  })

  it('discards deferred capability response arriving after logout/reset', async () => {
    const auth = useAuthStore()
    const { fetchCapabilities, resetCapabilities, isPosEnabled, currentOrgId, loaded } =
      useTenantCapabilities()

    const { promise: slowPromise, resolve: resolveCall } = (Promise as any).withResolvers()

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url.includes('/organizations/1/capabilities')) {
        await slowPromise
        return {
          organization_id: 1,
          capabilities: { tenant_pos_and_wallet: true },
          role: 'staff',
          is_platform_admin: false,
        }
      }
      return []
    })

    // 1. Start capability fetch for Org 1 while user is logged in
    const org1Promise = fetchCapabilities(1)

    // 2. Before request resolves, user logs out
    auth.user = null
    resetCapabilities()

    // 3. Deferred request resolves
    resolveCall()
    const res = await org1Promise

    // 4. Result must be discarded (null returned), state remains uninitialized / reset
    expect(res).toBeNull()
    expect(isPosEnabled.value).toBe(false)
    expect(currentOrgId.value).toBeNull()
    expect(loaded.value).toBe(false)
  })

  it('updates capabilities when switching from Account 101/Org 1 to Account 102/Org 2 and clears loading', async () => {
    const auth = useAuthStore()
    const { fetchCapabilities, isPosEnabled, currentOrgId, currentAccountId, loading, loaded } =
      useTenantCapabilities()

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url.includes('/organizations/1/capabilities')) {
        return {
          organization_id: 1,
          capabilities: { tenant_pos_and_wallet: true },
          role: 'organization_owner',
          is_platform_admin: false,
        }
      }
      if (url.includes('/organizations/2/capabilities')) {
        return {
          organization_id: 2,
          capabilities: { tenant_pos_and_wallet: false },
          role: 'staff',
          is_platform_admin: false,
        }
      }
      return []
    })

    // 1. Fetch capabilities for Account 101, Org 1 (resolves and commits)
    auth.user = { id: 101, name: 'User 101', role: 'customer' } as any
    const res1 = await fetchCapabilities(1, 101)
    expect(res1).not.toBeNull()
    expect(currentOrgId.value).toBe(1)
    expect(currentAccountId.value).toBe(101)
    expect(isPosEnabled.value).toBe(true)
    expect(loading.value).toBe(false)
    expect(loaded.value).toBe(true)

    // 2. Switch to Account 102, Org 2 and fetch capabilities
    auth.user = { id: 102, name: 'User 102', role: 'customer' } as any
    const res2 = await fetchCapabilities(2, 102)

    // 3. Assert that the new response commits, capabilities are updated for Org 2, and loading.value clears to false
    expect(res2).not.toBeNull()
    expect(currentOrgId.value).toBe(2)
    expect(currentAccountId.value).toBe(102)
    expect(isPosEnabled.value).toBe(false)
    expect(loading.value).toBe(false)
    expect(loaded.value).toBe(true)
  })
})
