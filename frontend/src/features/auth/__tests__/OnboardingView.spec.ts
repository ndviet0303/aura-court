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
    localStorage.clear()
    vi.clearAllMocks()
  })
  it('renders role selector cards and defaults to customer', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      role: 'customer',
      onboarded: false,
    }
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
    auth.user = {
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      role: 'customer',
      onboarded: false,
    }
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
    expect(localStorage.getItem('aura_onboarding_account_type')).toBe('court_owner')
  })

  it('restores court owner role and draft on reload without falling back to customer', async () => {
    localStorage.setItem('aura_onboarding_account_type', 'court_owner')
    localStorage.setItem(
      'aura_onboarding_draft',
      JSON.stringify({
        accountType: 'court_owner',
        organizationName: 'CLB Cầu Lông Thủ Đức',
        phone: '0901234567',
        venueAddress: '123 Kha Vạn Cân, TP. Thủ Đức',
        courtsCount: 6,
      }),
    )

    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      role: 'customer',
      onboarded: false,
    }
    auth.loaded = true

    const wrapper = mount(OnboardingView, {
      global: {
        plugins: [i18n],
      },
    })

    // Court owner fields should be immediately visible, not fallen back to customer
    expect(wrapper.find('#o-name').exists()).toBe(true)
    expect((wrapper.find('#o-name').element as HTMLInputElement).value).toBe('CLB Cầu Lông Thủ Đức')
    expect((wrapper.find('#o-phone').element as HTMLInputElement).value).toBe('0901234567')
    expect((wrapper.find('#o-address').element as HTMLInputElement).value).toBe(
      '123 Kha Vạn Cân, TP. Thủ Đức',
    )
  })

  it('stores partner intent when completing onboarding as court owner', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      role: 'customer',
      onboarded: false,
    }
    auth.loaded = true
    auth.completeOnboarding = vi.fn<() => Promise<any>>().mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      role: 'customer',
      onboarded: true,
    })

    localStorage.setItem('aura_onboarding_account_type', 'court_owner')
    localStorage.setItem(
      'aura_onboarding_draft',
      JSON.stringify({
        accountType: 'court_owner',
        organizationName: 'Aura Arena',
        phone: '0987654321',
      }),
    )

    const wrapper = mount(OnboardingView, {
      global: {
        plugins: [i18n],
      },
    })

    await wrapper.find('form').trigger('submit.prevent')

    expect(localStorage.getItem('aura_partner_intent')).toBe('court_owner')
    expect(mockPush).toHaveBeenCalledWith('/partner-registration')
  })
})
