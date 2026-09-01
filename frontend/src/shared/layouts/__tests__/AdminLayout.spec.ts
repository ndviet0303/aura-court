import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminLayout from '../admin/AdminLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { i18n, setAppLocale } from '@/i18n'

const mockRoute = {
  path: '/admin',
  query: {},
}

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
    setAppLocale('vi')
    vi.clearAllMocks()
  })

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

  it('renders standalone dashboard and collapsible nav groups', () => {
    const wrapper = createWrapper()
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
})
