import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import PaymentView from '../PaymentView.vue'
import { i18n } from '@/i18n'
import { useBookingStore } from '@/stores/booking'

const mockPush = vi.fn<() => void>()

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: mockPush,
  }),
  useRoute: () => ({
    path: '/payment',
    params: {},
    query: {},
  }),
  RouterLink: {
    template: '<a><slot /></a>',
  },
}))

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
  errorMessage: (err: unknown, fallback: string) => fallback,
}))

describe('PaymentView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders payment checkout layout with tabs and summary when payment session exists', async () => {
    const store = useBookingStore()
    store.payment = {
      code: 'BK-2026-TEST',
      amount: 150000,
      bank: 'MB Bank',
      accountNumber: '0987654321',
      qrUrl: 'https://img.vietqr.io/image/MB-0987654321-compact.png',
      expiresAt: new Date(Date.now() + 900000).toISOString(),
    }

    const wrapper = mount(PaymentView, {
      global: {
        plugins: [i18n],
      },
    })

    expect(wrapper.find('.checkout-stepper').exists()).toBe(true)
    expect(wrapper.find('.player-details-panel').exists()).toBe(true)
    expect(wrapper.find('.payment-methods-panel').exists()).toBe(true)
    expect(wrapper.find('.summary-card-dark').exists()).toBe(true)
    expect(wrapper.text()).toContain('BK-2026-TEST')
  })

  it('switches between payment method tabs (SePay VietQR and Stripe Card)', async () => {
    const store = useBookingStore()
    store.payment = {
      code: 'BK-2026-TEST',
      amount: 150000,
      bank: 'MB Bank',
      accountNumber: '0987654321',
      qrUrl: 'https://img.vietqr.io/image/MB-0987654321-compact.png',
      expiresAt: new Date(Date.now() + 900000).toISOString(),
    }

    const wrapper = mount(PaymentView, {
      global: {
        plugins: [i18n],
      },
    })

    const tabs = wrapper.findAll('.method-tab-btn')
    expect(tabs.length).toBe(2)

    // Switch to Stripe Card
    await tabs[1]!.trigger('click')
    expect(wrapper.find('.card-method-box').exists()).toBe(true)
    expect(wrapper.text()).toContain('Stripe')
  })

  it('shows empty session state when no payment exists', () => {
    const store = useBookingStore()
    store.payment = null
    store.current = null

    const wrapper = mount(PaymentView, {
      global: {
        plugins: [i18n],
      },
    })

    expect(wrapper.find('.empty-payment-panel').exists()).toBe(true)
    expect(wrapper.find('.btn-choose-court').exists()).toBe(true)
  })
})
