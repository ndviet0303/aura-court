import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import Navbar from '../Navbar.vue'
import { useAuthStore } from '@/stores/auth'

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
    template: '<a><slot /></a>',
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
})
