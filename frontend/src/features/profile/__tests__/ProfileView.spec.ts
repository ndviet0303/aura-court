import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import ProfileView from '../views/ProfileView.vue'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'

const mockPush = vi.fn<() => void>()
const mockReplace = vi.fn<() => void>()

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: mockPush,
    replace: mockReplace,
  }),
  useRoute: () => ({
    path: '/profile',
    query: {},
  }),
  RouterLink: {
    template: '<a><slot /></a>',
  },
}))

describe('ProfileView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders athlete banner with user initials and role badge', () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Nguyen Van A',
      email: 'nva@example.com',
      role: 'customer',
      phone: '0988111222',
      play_level: 'intermediate',
      onboarded: true,
      email_verified_at: '2026-08-23T00:00:00Z',
    }
    auth.loaded = true

    const wrapper = mount(ProfileView, {
      global: {
        plugins: [i18n],
      },
    })

    expect(wrapper.find('.athlete-display-name').text()).toBe('Nguyen Van A')
    expect(wrapper.find('.avatar-initials-badge').text()).toContain('NA')
    expect(wrapper.find('.stitch-tabs-bar').exists()).toBe(true)
  })

  it('switches tabs between general, security, membership, and shortcuts', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Nguyen Van A',
      email: 'nva@example.com',
      role: 'admin',
      phone: '0988111222',
      play_level: 'advanced',
      onboarded: true,
      password_set: true,
    }
    auth.loaded = true

    const wrapper = mount(ProfileView, {
      global: {
        plugins: [i18n],
      },
    })

    const tabs = wrapper.findAll('.tab-btn')
    expect(tabs.length).toBe(4)

    // Click Security tab
    await tabs[1]!.trigger('click')
    expect(wrapper.find('#f-curr-pwd').exists()).toBe(true)
    expect(wrapper.find('#f-new-pwd').exists()).toBe(true)

    // Click Shortcuts tab
    await tabs[3]!.trigger('click')
    expect(wrapper.find('.shortcuts-soft-grid').exists()).toBe(true)
  })

  it('renders set password mode without current password for social users', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 2,
      name: 'Google User',
      email: 'google@gmail.com',
      role: 'customer',
      onboarded: true,
      password_set: false,
      social_provider: 'google',
    }
    auth.loaded = true

    const wrapper = mount(ProfileView, {
      global: {
        plugins: [i18n],
      },
    })

    // Banner shows social badge
    expect(wrapper.find('.badge-social').exists()).toBe(true)

    // Switch to security tab
    const tabs = wrapper.findAll('.tab-btn')
    await tabs[1]!.trigger('click')

    // Current password input should NOT exist
    expect(wrapper.find('#f-curr-pwd').exists()).toBe(false)
    // New password and confirm password inputs DO exist
    expect(wrapper.find('#f-new-pwd').exists()).toBe(true)
    expect(wrapper.find('#f-conf-pwd').exists()).toBe(true)
    expect(wrapper.find('.social-alert-card').exists()).toBe(true)
  })
})
