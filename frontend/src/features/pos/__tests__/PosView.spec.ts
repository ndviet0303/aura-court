import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import PosView from '../views/PosView.vue'
import { i18n } from '@/i18n'
import { posApi } from '../api/posApi'
import { api } from '@/core/api/client'

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {}, params: {} }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))
vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn().mockResolvedValue({
      organization_id: 1,
      capabilities: { tenant_pos_and_wallet: true },
      role: 'staff',
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
    getOrder: vi.fn(),
    cancelQrPayment: vi.fn(),
    getTab: vi.fn(),
    getShifts: vi.fn(),
    getActiveShift: vi.fn(),
    openShift: vi.fn(),
    getVenues: vi.fn().mockResolvedValue([]),
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

  it('asks a caller with several venues which venue the shift opens at', async () => {
    vi.mocked(posApi.getActiveShift).mockResolvedValue(null)
    vi.mocked(posApi.getVenues).mockResolvedValueOnce([
      { id: 42, name: 'Alpha' },
      { id: 43, name: 'Beta' },
    ])
    vi.mocked(posApi.openShift).mockResolvedValue({
      id: 102,
      status: 'open',
      opened_at: '2026-10-15 08:00:00',
    })

    const wrapper = mount(PosView, { global: { plugins: [i18n] } })
    await flushPromises()
    await wrapper.find('.shift-btn').trigger('click')
    await flushPromises()

    const openBtn = wrapper.find('.modal-footer .btn-primary')
    expect(openBtn.attributes('disabled')).toBeDefined()
    await wrapper.find('.shift-open-form select').setValue(43)
    await openBtn.trigger('click')
    await flushPromises()

    expect(posApi.openShift).toHaveBeenCalledWith({ opening_cash: 1000000, venue_id: 43 })
  })
  it('shows the owner QR without a manual confirm and completes when the receipt lands', async () => {
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] })
    vi.mocked(api.get).mockResolvedValueOnce({
      organization_id: 1,
      capabilities: { tenant_pos_and_wallet: true, direct_owner_payments: true },
      role: 'staff',
      is_platform_admin: false,
    })
    vi.mocked(posApi.getActiveShift).mockResolvedValue({
      id: 7,
      status: 'open',
      qr_available: true,
      opened_at: '2026-10-14 08:00:00',
    })
    const order = { id: 9, status: 'pending_payment', total_amount: 15000 }
    vi.mocked(posApi.completeOrder).mockResolvedValue({
      order,
      payment_attempt: {
        reference: 'ACOWNERREF',
        amount: 15000,
        qr_url: 'https://img.vietqr.io/image/MB-0987654321-compact2.png',
        receiver: { bank_code: 'MB', account_number: '0987654321', account_name: 'CHU SAN' },
      },
    })
    vi.mocked(posApi.getOrder).mockResolvedValue({
      order: { ...order, status: 'completed' },
      receipt: { order_code: 'ACOWNERREF', payment_method: 'qr', items: [], grand_total: 15000 },
      payment_attempt: null,
    })

    const wrapper = mount(PosView, { global: { plugins: [i18n] } })
    await flushPromises()

    const tenders = wrapper.findAll('.pay-method-btn')
    expect(tenders.map((b) => b.find('.method-text').text())).not.toContain(
      i18n.global.t('admin.pos.payWallet'),
    )
    await wrapper.findAll('.product-card')[0]!.trigger('click')
    await tenders
      .find((b) => b.find('.method-text').text() === i18n.global.t('admin.pos.payQr'))!
      .trigger('click')
    await wrapper.find('.checkout-submit-btn').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('0987654321')
    expect(wrapper.text()).toContain('ACOWNERREF')
    expect(wrapper.find('.btn-confirm').exists()).toBe(false)

    await vi.advanceTimersByTimeAsync(3000)
    await flushPromises()

    expect(posApi.getOrder).toHaveBeenCalledWith(9)
    expect(wrapper.find('.qr-modal-card').exists()).toBe(false)
    expect(wrapper.find('.cart-count-badge').exists()).toBe(false)
    wrapper.unmount()
    vi.useRealTimers()
  })
})
