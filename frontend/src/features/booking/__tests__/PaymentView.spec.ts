import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import { setActivePinia, createPinia } from 'pinia'
import PaymentView from '../views/PaymentView.vue'
import { useBookingStore } from '@/stores/booking'
import viMessages from '@/i18n/locales/vi'

const i18n = createI18n({
  legacy: false,
  locale: 'vi',
  messages: {
    vi: viMessages,
  },
})

let mockRoute = {
  path: '/payment/BK-2026-TEST',
  params: { code: 'BK-2026-TEST' },
  query: {},
}

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: vi.fn(),
  }),
  useRoute: () => mockRoute,
  RouterLink: {
    template: '<a><slot /></a>',
  },
}))

vi.mock('@/features/tournament/api/tournamentApi', () => ({
  tournamentApi: {
    getById: vi.fn(() =>
      Promise.resolve({
        id: 1,
        uuid: '01a05cdc-4ec8-7159-b493-d3744b36c2a1',
        name: 'Saigon Open Tennis Masters 2026',
        entry_fee: '500.000 ₫ / VĐV',
        venue: 'Aura Center Court A',
      })
    ),
    getTournament: vi.fn(() =>
      Promise.resolve({
        id: 1,
        uuid: '01a05cdc-4ec8-7159-b493-d3744b36c2a1',
        name: 'Saigon Open Tennis Masters 2026',
        entry_fee: '500.000 ₫ / VĐV',
        venue: 'Aura Center Court A',
      })
    ),
  },
}))

vi.mock('@/core/services/api', () => ({
  apiClient: {
    get: vi.fn(() => Promise.resolve({ data: {} })),
    post: vi.fn(() => Promise.resolve({ data: { success: true } })),
  },
}))

describe('PaymentView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockRoute = {
      path: '/payment/BK-2026-TEST',
      params: { code: 'BK-2026-TEST' },
      query: {},
    }
  })

  it('renders payment checkout layout with wallet payment and summary when payment session exists', () => {
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
    expect(wrapper.find('.wallet-pay-card').exists()).toBe(true)
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

  it('renders wallet pay action and allows paying when balance is sufficient', async () => {
    const store = useBookingStore()
    store.payment = {
      code: 'BK-2026-TEST',
      amount: 150000,
      bank: 'MB Bank',
      accountNumber: '0987654321',
      qrUrl: 'https://img.vietqr.io/image/MB-0987654321-compact.png',
      expiresAt: new Date(Date.now() + 900000).toISOString(),
    }
    store.fetchWalletBalance = vi.fn().mockResolvedValue(500000)

    const wrapper = mount(PaymentView, {
      global: {
        plugins: [i18n],
      },
    })

    await new Promise((resolve) => setTimeout(resolve, 50))

    const walletBtn = wrapper.find('.btn-wallet-pay')
    expect(walletBtn.exists()).toBe(true)
    expect(walletBtn.text()).toContain('Xác Nhận Thanh Toán')
  })

  it('shows insufficient balance warning and topup button when balance is low', async () => {
    const store = useBookingStore()
    store.payment = {
      code: 'BK-2026-TEST',
      amount: 150000,
      bank: 'Ví Aura',
      accountNumber: 'WALLET',
      qrUrl: '',
      expiresAt: '',
    }
    store.fetchWalletBalance = vi.fn().mockResolvedValue(50000)

    const wrapper = mount(PaymentView, {
      global: {
        plugins: [i18n],
      },
    })

    await new Promise((resolve) => setTimeout(resolve, 50))

    expect(wrapper.find('.insufficient-box').exists()).toBe(true)
    expect(wrapper.find('.btn-topup-wallet').exists()).toBe(true)
    expect(wrapper.text()).toContain('Số dư ví chưa đủ')
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
    expect(wrapper.text()).toContain('Chưa Có Phiên Thanh Toán Nào')
  })
})
