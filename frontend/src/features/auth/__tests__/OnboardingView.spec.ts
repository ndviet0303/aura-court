import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import OnboardingView from '../views/OnboardingView.vue'
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
    path: '/onboarding',
    query: {},
  }),
  RouterLink: {
    template: '<a><slot /></a>',
  },
}))

describe('OnboardingView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders role selector cards and defaults to customer', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'Test User', email: 'test@example.com', role: 'customer', onboarded: false }
    auth.loaded = true

    const wrapper = mount(OnboardingView, {
      global: {
        plugins: [i18n],
      },
    })

    const roleCards = wrapper.findAll('.role-card')
    expect(roleCards.length).toBe(2)
    expect(wrapper.find('#c-phone').exists()).toBe(true)
    expect(wrapper.find('.play-level-grid').exists()).toBe(true)
  })

  it('shows court owner fields when clicking court owner card', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'Test User', email: 'test@example.com', role: 'customer', onboarded: false }
    auth.loaded = true

    const wrapper = mount(OnboardingView, {
      global: {
        plugins: [i18n],
      },
    })

    const roleCards = wrapper.findAll('.role-card')
    await roleCards[1]!.trigger('click')

    expect(wrapper.find('#o-name').exists()).toBe(true)
    expect(wrapper.find('#o-phone').exists()).toBe(true)
    expect(wrapper.find('#o-address').exists()).toBe(true)
  })
})
