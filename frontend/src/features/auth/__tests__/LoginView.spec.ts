import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import LoginView from '../LoginView.vue'
import { i18n } from '@/i18n'

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
})
