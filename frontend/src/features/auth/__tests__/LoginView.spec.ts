import { describe, it, expect, beforeEach, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import LoginView from '../views/LoginView.vue'
import { i18n } from '@/i18n'
import { api } from '@/core/api/client'

const mockPush = vi.fn<() => void>()
const mockReplace = vi.fn<() => void>()

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: mockPush,
    replace: mockReplace,
  }),
  useRoute: () => ({
    path: '/login',
    query: {},
  }),
  RouterLink: {
    template: '<a><slot /></a>',
  },
}))

describe('LoginView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders login tab by default with email and password inputs', () => {
    const wrapper = mount(LoginView, {
      global: {
        plugins: [i18n],
      },
    })

    expect(wrapper.find('input[name="email"]').exists()).toBe(true)
    expect(wrapper.find('input[name="password"]').exists()).toBe(true)
    expect(wrapper.find('.auth-submit-btn').exists()).toBe(true)
  })

  it('switches to register mode and displays registration inputs', async () => {
    const wrapper = mount(LoginView, {
      global: {
        plugins: [i18n],
      },
    })

    const tabs = wrapper.findAll('.auth-tab-btn')
    expect(tabs.length).toBe(2)
    await tabs[1]!.trigger('click')

    expect(wrapper.find('input[name="name"]').exists()).toBe(true)
    expect(wrapper.find('input[name="email"]').exists()).toBe(true)
    expect(wrapper.find('input[name="password"]').exists()).toBe(true)
    expect(wrapper.find('input[name="password_confirmation"]').exists()).toBe(true)
  })

  it('renders Google and Facebook social login buttons', () => {
    const wrapper = mount(LoginView, {
      global: {
        plugins: [i18n],
      },
    })

    expect(wrapper.find('.google-btn').exists()).toBe(true)
    expect(wrapper.find('.facebook-btn').exists()).toBe(true)
    expect(wrapper.find('.social-divider').exists()).toBe(true)
  })

  it('does not fall back to an unverified profile login when OAuth is unavailable', async () => {
    const getSpy = vi.spyOn(api, 'get').mockResolvedValue({
      url: null,
      message: 'Google OAuth is not configured.',
    })
    const postSpy = vi.spyOn(api, 'post')
    const wrapper = mount(LoginView, {
      global: {
        plugins: [i18n],
      },
    })

    await wrapper.find('.google-btn').trigger('click')
    await flushPromises()

    expect(getSpy).toHaveBeenCalledWith('/auth/google/redirect')
    expect(postSpy).not.toHaveBeenCalled()
    expect(wrapper.find('[role="alert"]').text()).toContain('Google OAuth is not configured.')
  })

  it('renders localized login button and loading text in both VI and EN without unlocalized keys', async () => {
    // 1. Vietnamese locale
    i18n.global.locale.value = 'vi'
    const wrapperVi = mount(LoginView, {
      global: {
        plugins: [i18n],
      },
    })

    const submitBtnVi = wrapperVi.find('.auth-submit-btn')
    expect(submitBtnVi.text()).toContain('Đăng nhập')
    expect(submitBtnVi.text()).not.toContain('auth.')

    // 2. English locale
    i18n.global.locale.value = 'en'
    const wrapperEn = mount(LoginView, {
      global: {
        plugins: [i18n],
      },
    })

    const submitBtnEn = wrapperEn.find('.auth-submit-btn')
    expect(submitBtnEn.text()).toContain('Sign In')
    expect(submitBtnEn.text()).not.toContain('auth.')

    // Restore to vi
    i18n.global.locale.value = 'vi'
  })
})
