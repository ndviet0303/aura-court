import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import Navbar from '../header/Navbar.vue'
import { useAuthStore } from '@/stores/auth'
import { useBookingStore } from '@/stores/booking'

const mockPush = vi.fn<() => void>()

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: mockPush,
  }),
  useRoute: () => ({
    path: '/',
    query: {},
  }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="to"><slot /></a>',
  },
}))

describe('Navbar', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  function createWrapper() {
    return mount(Navbar, {
      global: {
        stubs: {
          ThemeToggle: { template: '<div class="stub-theme-toggle"></div>' },
          LanguageSwitcher: { template: '<div class="stub-lang-switcher"></div>' },
        },
        mocks: {
          $t: (key: string) => key,
        },
      },
    })
  }

  it('renders brand and navigation links', () => {
    const wrapper = createWrapper()
    expect(wrapper.find('.brand-title').text()).toBe('Aura Court')
    expect(wrapper.find('.nav-pill-container').exists()).toBe(true)
  })

  it('shows full navbar skeleton when auth is loading and not loaded', () => {
    const auth = useAuthStore()
    auth.loading = true
    auth.loaded = false
    auth.user = null

    const wrapper = createWrapper()
    expect(wrapper.find('.navbar-skeleton').exists()).toBe(true)
    expect(wrapper.find('.topbar').exists()).toBe(false)
  })

  it('shows guest login button when user is not logged in', () => {
    const auth = useAuthStore()
    auth.loading = false
    auth.loaded = true
    auth.user = null

    const wrapper = createWrapper()
    expect(wrapper.find('[data-testid="user-skeleton"]').exists()).toBe(false)
    expect(wrapper.find('.user-name').text()).toBe('nav.guest')
  })

  it('shows user name and role badge when user is logged in', () => {
    const auth = useAuthStore()
    auth.loading = false
    auth.loaded = true
    auth.user = {
      id: 1,
      name: 'Nguyen Van A',
      email: 'a@example.com',
      role: 'customer',
      email_verified_at: '2026-01-01',
    }

    const wrapper = createWrapper()
    expect(wrapper.find('[data-testid="user-skeleton"]').exists()).toBe(false)
    expect(wrapper.find('.user-name').text()).toBe('Nguyen Van A')
    expect(wrapper.find('.user-badge').text()).toBe('PRO MEMBER')
  })

  it('toggles mobile drawer when mobile menu button is clicked', async () => {
    const auth = useAuthStore()
    auth.loading = false
    auth.loaded = true
    auth.user = null

    const wrapper = createWrapper()
    const menuBtn = wrapper.find('.mobile-menu-btn')
    expect(menuBtn.exists()).toBe(true)

    // Open drawer
    await menuBtn.trigger('click')
    expect(document.querySelector('.mobile-drawer-panel')).not.toBeNull()
    expect(document.querySelector('.mobile-drawer-backdrop')).not.toBeNull()

    // Close drawer via backdrop click
    const backdrop = document.querySelector('.mobile-drawer-backdrop') as HTMLElement
    backdrop.click()
    await wrapper.vm.$nextTick()
    expect(document.querySelector('.mobile-drawer-panel')).toBeNull()
  })

  it.each([
    ['pending', true],
    ['flagged', true],
    ['draft', false],
    ['revision_required', false],
  ])(
    'routes a partner applicant with %s status to the matching entry',
    async (status, underReview) => {
      const auth = useAuthStore()
      auth.loading = false
      auth.loaded = true
      auth.user = {
        id: 2,
        name: 'Applicant',
        email: 'applicant@example.com',
        role: 'customer',
        email_verified_at: '2026-01-01',
        capabilities: { has_partner_application: true, partner_application_status: status },
      }
      vi.spyOn(useBookingStore(), 'fetchWalletBalance').mockResolvedValue(undefined as never)
      const href = underReview ? '/partner/application-status' : '/partner-registration'
      const label = underReview ? 'Hồ sơ đối tác' : 'Thiết lập cụm sân'
      const badge = underReview ? 'CHỜ DUYỆT' : 'ĐỐI TÁC'

      const wrapper = createWrapper()
      const desktopLink = wrapper.find(`.nav-pill-container a[href="${href}"]`)
      expect(desktopLink.text()).toBe(label)
      expect(wrapper.find('.user-badge').text()).toBe(badge)

      await wrapper.find('.user-dropdown-trigger').trigger('click')
      const dropdownItem = wrapper.find('.dropdown-item.admin-item')
      expect(dropdownItem.attributes('href')).toBe(href)
      expect(dropdownItem.text()).toContain(label)

      await wrapper.find('.mobile-menu-btn').trigger('click')
      expect(document.querySelector('.drawer-badge')?.textContent).toBe(badge)
      const drawerRow = document.querySelector('.drawer-nav-row.admin-row')
      expect(drawerRow?.getAttribute('href')).toBe(href)
      expect(drawerRow?.querySelector('.row-label')?.textContent).toBe(label)
      expect(drawerRow?.querySelector('.drawer-admin-tag')?.textContent).toBe(badge)

      wrapper.unmount()
    },
  )

  it.each([true, false])(
    'shows the Aura wallet entry points only when wallet_enabled is %s',
    async (walletEnabled) => {
      const auth = useAuthStore()
      auth.loading = false
      auth.loaded = true
      auth.user = {
        id: 3,
        name: 'Wallet User',
        email: 'wallet@example.com',
        role: 'customer',
        email_verified_at: '2026-01-01',
        capabilities: { wallet_enabled: walletEnabled },
      }
      const fetchBalance = vi
        .spyOn(useBookingStore(), 'fetchWalletBalance')
        .mockResolvedValue(undefined as never)

      const wrapper = createWrapper()
      expect(wrapper.find('.nav-wallet-pill').exists()).toBe(walletEnabled)

      await wrapper.find('.user-dropdown-trigger').trigger('click')
      expect(wrapper.find('.dropdown-wallet-card').exists()).toBe(walletEnabled)
      expect(fetchBalance.mock.calls.length > 0).toBe(walletEnabled)

      await wrapper.find('.mobile-menu-btn').trigger('click')
      expect(document.querySelector('.mobile-drawer-panel a[href="/wallet"]') !== null).toBe(
        walletEnabled,
      )

      wrapper.unmount()
    },
  )
})
