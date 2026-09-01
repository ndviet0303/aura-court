import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import PaymentView from '../views/PaymentView.vue'
import { i18n } from '@/i18n'
import { useBookingStore } from '@/stores/booking'

const mockPush = vi.fn<() => void>()
let mockRoute = {
  path: '/payment',
  params: {} as Record<string, any>,
  query: {} as Record<string, any>,
}

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: mockPush,
  }),
  useRoute: () => mockRoute,
  RouterLink: {
    template: '<a><slot /></a>',
  },
}))

vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
  apiV2: {
    get: vi.fn().mockResolvedValue({
      tournament: {
        id: 10,
        uuid: '01a05cdc-4ec8-7159-b493-d3744b36c2a1',
        name: 'Saigon Open Tennis Masters 2026',
        sport: 'tennis',
        format: 'single_elimination',
        status: 'open',
        prize_pool: '50.000.000 ₫',
        dates: '15/09/2026 - 18/09/2026',
        venue: 'Aura Tennis Center',
        city: 'TP. Hồ Chí Minh',
        entry_fee: '500.000 ₫ / VĐV',
        image_url: 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0',
        current_slots: 8,
        max_slots: 32,
      },
    }),
    post: vi.fn(),
  },
  errorMessage: (err: unknown, fallback: string) => fallback,
}))

describe('PaymentView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    mockRoute = {
      path: '/payment',
      params: {},
      query: {},
    }
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

  it('renders tournament payment information when tournament_uuid is provided in query', async () => {
    mockRoute = {
      path: '/payment/TRN-01a05cdc',
      params: { code: 'TRN-01a05cdc' },
      query: {
        tournament_uuid: '01a05cdc-4ec8-7159-b493-d3744b36c2a1',
        name: 'Nguyễn Văn VĐV',
        phone: '0988776655',
        partner: 'Trần Đồng Đội',
        type: 'tournament',
      },
    }

    const wrapper = mount(PaymentView, {
      global: {
        plugins: [i18n],
      },
    })

    await new Promise((resolve) => setTimeout(resolve, 50))

    expect(wrapper.find('.checkout-stepper').exists()).toBe(true)
    expect(wrapper.text()).toContain('Lệ Phí Giải')
    expect(wrapper.text()).toContain('Thông Tin Vận Động Viên Đăng Ký')
    expect(wrapper.text()).toContain('Nguyễn Văn VĐV')
    expect(wrapper.text()).toContain('Trần Đồng Đội')
    expect(wrapper.text()).toContain('Saigon Open Tennis Masters 2026')
    expect(wrapper.text()).toContain('500.000')
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
  })
})
