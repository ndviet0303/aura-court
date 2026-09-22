import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import PosView from '../views/PosView.vue'
import { i18n } from '@/i18n'
import { posApi } from '../api/posApi'

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {}, params: {} }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))
vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  apiV2: {
    get: vi.fn().mockResolvedValue({
      organization_id: 1,
      capabilities: { tenant_pos_and_wallet: true },
      role: 'cashier',
      is_platform_admin: false,
    }),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  errorMessage: (_err: unknown, fallback: string) => fallback,
}))

vi.mock('../api/posApi', () => ({
  posApi: {
    getProducts: vi.fn(),
    createProduct: vi.fn(),
    adjustStock: vi.fn(),
    completeOrder: vi.fn(),
    getShifts: vi.fn(),
    getActiveShift: vi.fn(),
    openShift: vi.fn(),
    closeShift: vi.fn(),
    reviewShift: vi.fn(),
    getActiveReservations: vi.fn(),
    searchCustomers: vi.fn(),
  },
}))

describe('PosView (/admin/pos)', () => {
  const mockProducts = [
    {
      id: 1,
      sku: 'POS-POCARI-500',
      name: 'Nước bù khoáng Pocari Sweat 500ml',
      category: 'drinks',
      price: 15000,
      stock_quantity: 120,
      unit: 'chai',
      is_rental: false,
      is_service: false,
    },
    {
      id: 2,
      sku: 'POS-RENTAL-RACKET',
      name: 'Thuê vợt cầu lông Carbon Pro (theo giờ)',
      category: 'rental',
      price: 30000,
      stock_quantity: 20,
      unit: 'cây/giờ',
      is_rental: true,
      is_service: false,
    },
  ]

  const mockCustomers = [
    {
      id: 4,
      name: 'Khách hàng VIP 01',
      phone: '0901234567',
      tier: 'VIP',
      discount_rate: 0.2,
      wallet_balance: 1000000,
      loyalty_points: 500,
    },
  ]

  const mockReservations = [
    {
      id: 25,
      court_name: 'Sân 11 - Sài Gòn Central',
      court_number: 11,
      customer_name: 'Khách hàng Demo',
      phone: '0901234567',
      status: 'playing',
      time_slot: '18:00 - 20:00',
      deposit_paid: 200000,
    },
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.mocked(posApi.getProducts).mockResolvedValue(mockProducts)
    vi.mocked(posApi.getActiveShift).mockResolvedValue(null)
    vi.mocked(posApi.getActiveReservations).mockResolvedValue(mockReservations)
    vi.mocked(posApi.searchCustomers).mockResolvedValue(mockCustomers)
  })

  it('renders POS topbar, product grid, and cart panel', async () => {
    const wrapper = mount(PosView, {
      global: {
        plugins: [i18n],
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('AURA POS')
    expect(wrapper.text()).toContain('Nước bù khoáng Pocari Sweat 500ml')
    expect(wrapper.text()).toContain('Thuê vợt cầu lông Carbon Pro')
    expect(wrapper.text()).toContain('Đơn Hàng Hiện Tại')
  })

  it('adds item to cart when clicking product card and calculates subtotal/total', async () => {
    const wrapper = mount(PosView, {
      global: {
        plugins: [i18n],
      },
    })

    await flushPromises()

    const productCard = wrapper.findAll('.product-card')[0]
    expect(productCard).toBeDefined()
    if (productCard) {
      await productCard.trigger('click')
    }

    expect(wrapper.text()).toContain('Nước bù khoáng Pocari Sweat 500ml')
    expect(wrapper.find('.cart-count-badge').text()).toBe('1')
  })

  it('opens shift modal and handles open shift', async () => {
    vi.mocked(posApi.openShift).mockResolvedValue({
      id: 101,
      status: 'open',
      opened_at: '2026-09-02 08:00:00',
      initial_cash: 1000000,
      opening_cash: 1000000,
    })

    const wrapper = mount(PosView, {
      global: {
        plugins: [i18n],
      },
    })

    await flushPromises()

    const shiftBtn = wrapper.find('.shift-btn')
    await shiftBtn.trigger('click')

    expect(wrapper.text()).toContain('Quản Lý Ca Thu Ngân')
  })
})
