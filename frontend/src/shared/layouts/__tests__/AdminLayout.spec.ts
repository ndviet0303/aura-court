import { describe, it, expect, beforeEach, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminLayout from '../admin/AdminLayout.vue'
import type { User } from '@/features/auth/types/auth'
import { useAuthStore } from '@/stores/auth'
import { i18n, setAppLocale } from '@/i18n'
import { api } from '@/core/api/client'
import { useTenantCapabilities } from '@/features/admin/composables/useTenantCapabilities'
const mockRoute = {
  path: '/admin',
  query: {},
  meta: {} as Record<string, unknown>,
}
let payoutsEnabled = true

vi.mock('@/core/api/client', () => ({ api: { get: vi.fn() } }))

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  RouterLink: {
    props: ['to'],
    template: '<a :href="to" class="router-link-stub"><slot /></a>',
  },
  RouterView: {
    template: '<div class="router-view-stub"></div>',
  },
}))

vi.mock('@/lib/seo', () => ({
  applySeo: vi.fn(),
}))

describe('AdminLayout', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockRoute.path = '/admin'
    mockRoute.meta = {}
    setAppLocale('vi')
    vi.clearAllMocks()
    useTenantCapabilities().resetCapabilities()
    payoutsEnabled = true
    vi.mocked(api.get).mockImplementation(async () => ({
      organization_id: 1,
      capabilities: { marketplace_payouts: payoutsEnabled },
      role: null,
      is_platform_admin: true,
    }))
  })

  async function createLoadedWrapper() {
    const wrapper = createWrapper()
    await flushPromises()
    return wrapper
  }

  function createWrapper() {
    return mount(AdminLayout, {
      global: {
        plugins: [i18n],
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="to" class="router-link-stub"><slot /></a>',
          },
          RouterView: {
            template: '<div class="router-view-stub"></div>',
          },
        },
      },
    })
  }

  it('renders sidebar brand, search input and user info', () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Admin Aura',
      email: 'admin@aura.test',
      role: 'admin',
      email_verified_at: '2026-01-01',
    }

    const wrapper = createWrapper()
    expect(wrapper.find('.brand-title').text()).toBe('AURA CONTROL')
    expect(wrapper.find('.search-input').exists()).toBe(true)
    expect(wrapper.find('.user-name').text()).toBe('Admin Aura')
  })

  it('renders standalone dashboard and collapsible nav groups', async () => {
    const wrapper = await createLoadedWrapper()
    const groupButtons = wrapper.findAll('.group-header-btn')
    expect(groupButtons.length).toBe(5) // courts, pos, finance, tournaments, organization
  })

  it('toggles group open/close on header click', async () => {
    const wrapper = createWrapper()
    const firstGroupBlock = wrapper.findAll('.nav-group-block')[0]!
    const firstGroupBtn = firstGroupBlock.find('.group-header-btn')

    // Initial state: courts is open by default
    expect(firstGroupBlock.classes()).toContain('is-group-open')

    // Click to collapse
    await firstGroupBtn.trigger('click')
    expect(firstGroupBlock.classes()).not.toContain('is-group-open')

    // Click to expand again
    await firstGroupBtn.trigger('click')
    expect(firstGroupBlock.classes()).toContain('is-group-open')
  })

  it('filters navigation items when typing in search input', async () => {
    const wrapper = createWrapper()
    const searchInput = wrapper.find('.search-input')

    // Search for a keyword like "Sân" (courts)
    await searchInput.setValue('Sân')

    // Search results view is displayed
    expect(wrapper.findAll('.sidebar-nav-item').length).toBeGreaterThan(0)
    expect(wrapper.find('.btn-clear-search').exists()).toBe(true)

    // Clear search
    await wrapper.find('.btn-clear-search').trigger('click')
    expect((wrapper.find('.search-input').element as HTMLInputElement).value).toBe('')
  })

  it('switches language when clicking language pill', async () => {
    const wrapper = createWrapper()
    const langBtns = wrapper.findAll('.lang-pill-btn')
    const enBtn = langBtns.find((b) => b.text() === 'EN')
    expect(enBtn).toBeDefined()

    await enBtn!.trigger('click')
    expect(i18n.global.locale.value).toBe('en')

    const viBtn = langBtns.find((b) => b.text() === 'VI')
    await viBtn!.trigger('click')
    expect(i18n.global.locale.value).toBe('vi')
  })

  it('renders operational groups for organization owner and hides finance platform-admin items', () => {
    const auth = useAuthStore()
    auth.user = {
      id: 5,
      name: 'Club Owner',
      email: 'owner@club.test',
      role: 'customer',
      email_verified_at: '2026-01-01',
      organizations: [
        {
          organization_id: 1,
          role: 'organization_owner',
          status: 'active',
        },
      ],
    } as unknown as User
    const wrapper = createWrapper()
    // Owner should see: courts, pos, tournaments, organization (4 groups)
    // But NOT finance group (which has payouts, reconciliation, exceptions)
    const groupHeaders = wrapper.findAll('.group-header-btn').map((b) => b.text())
    expect(groupHeaders.length).toBe(4)
    expect(wrapper.find('a[href="/admin/payouts"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/admin/kyc"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/admin/disputes"]').exists()).toBe(false)
  })

  it('shows a platform admin without a membership only platform-admin items (BR-23)', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Admin Aura',
      email: 'admin@aura.test',
      role: 'admin',
      email_verified_at: '2026-01-01',
    }
    const wrapper = await createLoadedWrapper()

    expect(wrapper.find('a[href="/admin/payouts"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/admin/venues"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/admin/pos"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/admin/staff"]').exists()).toBe(false)
  })

  it('hides owner-only items from staff', () => {
    const auth = useAuthStore()
    auth.user = {
      id: 6,
      name: 'Club Staff',
      email: 'staff@club.test',
      role: 'customer',
      email_verified_at: '2026-01-01',
      organizations: [{ organization_id: 1, role: 'staff', status: 'active' }],
    } as unknown as User
    const wrapper = createWrapper()

    expect(wrapper.find('a[href="/admin/pos"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/admin/venues"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/admin/staff"]').exists()).toBe(false)
  })

  it('hides the finance group when marketplace_payouts is off', async () => {
    payoutsEnabled = false
    const wrapper = await createLoadedWrapper()

    expect(wrapper.findAll('.group-header-btn').length).toBe(4)
    expect(wrapper.find('a[href="/admin/payouts"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/admin/reconciliation"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/admin/financial-exceptions"]').exists()).toBe(false)
  })

  it('reports a direct link to a disabled finance page as unavailable', async () => {
    payoutsEnabled = false
    mockRoute.path = '/admin/payouts'
    mockRoute.meta = { capability: 'marketplace_payouts' }
    const wrapper = await createLoadedWrapper()

    expect(wrapper.find('[data-testid="capability-unavailable"]').text()).toContain(
      'Tính năng không khả dụng',
    )
    expect(wrapper.find('.router-view-stub').exists()).toBe(false)
  })

  it('renders a finance page when marketplace_payouts is on', async () => {
    mockRoute.path = '/admin/payouts'
    mockRoute.meta = { capability: 'marketplace_payouts' }
    const wrapper = await createLoadedWrapper()

    expect(wrapper.find('.router-view-stub').exists()).toBe(true)
    expect(wrapper.find('[data-testid="capability-unavailable"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/admin/payouts"]').exists()).toBe(true)
  })
})
