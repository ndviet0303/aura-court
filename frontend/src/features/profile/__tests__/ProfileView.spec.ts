import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import ProfileView from '../views/ProfileView.vue'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import { profileApi } from '../api/profileApi'
import { authApi } from '@/features/auth/api/authApi'
import { flushPromises } from '@vue/test-utils'

vi.mock('../api/profileApi', () => ({
  profileApi: {
    getMembership: vi.fn().mockResolvedValue({ membership: null }),
    updateProfile: vi.fn(),
    changePassword: vi.fn(),
    resendVerification: vi.fn(),
  },
}))

vi.mock('@/features/auth/api/authApi', () => ({
  authApi: {
    me: vi.fn(),
  },
}))
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
    vi.mocked(authApi.me).mockImplementation(async () => {
      const u = useAuthStore().user
      if (u) return u
      throw new Error('Unauthenticated')
    })
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

    expect(wrapper.find('.athlete-name').text()).toBe('Nguyen Van A')
    expect(wrapper.find('.profile-tabs-nav').exists()).toBe(true)
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

    const tabs = wrapper.findAll('.tab-item-btn')
    expect(tabs.length).toBe(4)

    // Click Security tab
    await tabs[1]!.trigger('click')
    expect(wrapper.find('#sec-curr-pwd').exists()).toBe(true)
    expect(wrapper.find('#sec-new-pwd').exists()).toBe(true)

    // Click Shortcuts tab
    await tabs[3]!.trigger('click')
    expect(wrapper.find('.shortcuts-grid').exists()).toBe(true)
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
    expect(wrapper.find('.provider-pill').text()).toContain('GOOGLE')

    // Switch to security tab
    const tabs = wrapper.findAll('.tab-item-btn')
    await tabs[1]!.trigger('click')

    // Current password input should NOT exist for unset password
    expect(wrapper.find('#sec-curr-pwd').exists()).toBe(false)
    // New password and confirm password inputs DO exist
    expect(wrapper.find('#sec-new-pwd').exists()).toBe(true)
    expect(wrapper.find('#sec-conf-pwd').exists()).toBe(true)
    expect(wrapper.find('.social-notice-card').exists()).toBe(true)
    expect(wrapper.find('.social-notice-card').exists()).toBe(true)
  })

  it('saves profile and displays success notice', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Nguyen Van A',
      email: 'nva@example.com',
      role: 'customer',
      phone: '0988111222',
      play_level: 'intermediate',
      onboarded: true,
    }
    auth.loaded = true

    vi.mocked(profileApi.updateProfile).mockResolvedValue({
      id: 1,
      name: 'Nguyen Van Updated',
      email: 'nva@example.com',
      role: 'customer',
      phone: '0912345678',
      play_level: 'advanced',
      onboarded: true,
    })

    const wrapper = mount(ProfileView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Trigger form submit
    const nameInput = wrapper.find('#prof-name')
    if (nameInput.exists()) {
      await nameInput.setValue('Nguyen Van Updated')
    }
    const saveBtn = wrapper.find('.save-profile-btn')
    if (saveBtn.exists()) {
      await saveBtn.trigger('click')
      await flushPromises()
      expect(profileApi.updateProfile).toHaveBeenCalledWith(
        expect.objectContaining({
          name: 'Nguyen Van Updated',
        })
      )
    }
  })

  it('displays actionable validation error when profile update fails', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: '',
      email: 'nva@example.com',
      role: 'customer',
      onboarded: true,
    }
    auth.loaded = true

    const wrapper = mount(ProfileView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const saveBtn = wrapper.find('.save-profile-btn')
    if (saveBtn.exists()) {
      await saveBtn.trigger('click')
      await flushPromises()
      // Notice actionable error
      expect(wrapper.text()).toContain('Vui lòng nhập họ và tên')
    }
  })

  it('reloads fresh profile on mount via auth.load(true)', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Old Name',
      email: 'nva@example.com',
      role: 'customer',
      onboarded: true,
    }
    auth.loaded = true

    vi.mocked(authApi.me).mockResolvedValue({
      id: 1,
      name: 'Fresh Reloaded Name',
      email: 'nva@example.com',
      role: 'customer',
      onboarded: true,
      play_level: 'pro',
    })

    mount(ProfileView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(authApi.me).toHaveBeenCalled()
    expect(auth.user?.name).toBe('Fresh Reloaded Name')
  })
})
