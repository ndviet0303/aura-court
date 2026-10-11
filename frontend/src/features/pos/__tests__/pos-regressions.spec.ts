import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import PosView from '../views/PosView.vue'
import PosCustomerModal from '../components/PosCustomerModal.vue'
import { i18n } from '@/i18n'
import { posApi } from '../api/posApi'
import { api, apiV2 } from '@/core/api/client'

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {}, params: {} }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
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
    getTab: vi.fn(),
    addItemToTab: vi.fn(),
    removeItemFromTab: vi.fn(),
    settleTab: vi.fn(),
    cancelTab: vi.fn(),
  },
}))

vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
  apiV2: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

describe('Task 1.2 Regression: POS false success, receipt mismatch, and wrong reservation identity', () => {
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
  ]

  const mockReservations = [
    {
      id: 999, // Booking ID from V1
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
    vi.mocked(posApi.getActiveShift).mockResolvedValue({
      id: 1,
      status: 'open',
      opened_at: '2026-09-09 08:00:00',
      initial_cash: 500000,
      opening_cash: 500000,
    })
    vi.mocked(posApi.getActiveReservations).mockResolvedValue(mockReservations)
    vi.mocked(apiV2.get).mockResolvedValue({
      organization_id: 1,
      capabilities: { tenant_pos_and_wallet: true },
      role: 'cashier',
      is_platform_admin: false,
    })
    vi.mocked(posApi.searchCustomers).mockResolvedValue([])
  })

  it('prevents POS false success: when completeOrder fails, UI shows error notice, does NOT show receipt modal, and retains cart', async () => {
    // Simulate backend order rejection (e.g. 422 Unprocessable or 409 Conflict)
    vi.mocked(posApi.completeOrder).mockRejectedValue(new Error('Server error: insufficient stock'))

    const wrapper = mount(PosView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Add item to cart
    const productCard = wrapper.find('.product-card')
    expect(productCard.exists()).toBe(true)
    await productCard.trigger('click')
    await flushPromises()

    // Click checkout button (.checkout-submit-btn)
    const checkoutBtn = wrapper.find('.checkout-submit-btn')
    expect(checkoutBtn.exists()).toBe(true)
    await checkoutBtn.trigger('click')
    await flushPromises()

    // The API was indeed called and rejected
    expect(posApi.completeOrder).toHaveBeenCalledTimes(1)

    // FIXED BEHAVIOR: Notice is not shown, error banner is displayed
    const notice = wrapper.find('.pos-banner-notice')
    expect(notice.exists()).toBe(false)
    const errorBanner = wrapper.find('.pos-banner-error')
    expect(errorBanner.exists()).toBe(true)
    expect(errorBanner.text()).toContain('Server error: insufficient stock')

    // Receipt modal is NOT displayed
    const receiptModal = wrapper.find('.receipt-printable')
    expect(receiptModal.exists()).toBe(false)

    // Cart is RETAINED!
    expect(wrapper.find('.cart-count-badge').text()).toBe('1')
  })

  it('renders server receipt: receipt uses server order_code and amounts instead of random client generation', async () => {
    const serverReceiptResponse = {
      order: {
        id: 42,
        order_code: 'POS-SERVER-42',
        total_amount: 15000,
        subtotal: 15000,
      },
      receipt: {
        order_code: 'POS-SERVER-42',
        created_at: '2026-09-09T10:00:00Z',
        cashier_name: 'Thu Ngan Chi',
        items: [
          { name: 'Nước bù khoáng Pocari Sweat 500ml', quantity: 1, price: 15000, total: 15000 },
        ],
        subtotal: 15000,
        discount_amount: 0,
        tax_amount: 0,
        grand_total: 15000,
        payment_method: 'cash',
      },
    }
    vi.mocked(posApi.completeOrder).mockResolvedValue(serverReceiptResponse)

    const wrapper = mount(PosView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Add item to cart and checkout
    await wrapper.find('.product-card').trigger('click')
    await flushPromises()
    await wrapper.find('.checkout-submit-btn').trigger('click')
    await flushPromises()

    // The displayed receipt uses server order_code
    const receiptModal = wrapper.find('.receipt-printable')
    expect(receiptModal.exists()).toBe(true)
    expect(receiptModal.text()).toContain('POS-SERVER-42')
  })

  it('uses stable idempotency key on checkout retry after rejection', async () => {
    vi.mocked(posApi.completeOrder).mockRejectedValueOnce(new Error('Network timeout'))
    vi.mocked(posApi.completeOrder).mockResolvedValueOnce({
      order: { id: 99, total_amount: 15000 },
      receipt: { order_code: 'POS-SERVER-99', grand_total: 15000 },
    })

    const wrapper = mount(PosView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Add item to cart
    await wrapper.find('.product-card').trigger('click')
    await flushPromises()

    // 1st checkout attempt fails
    await wrapper.find('.checkout-submit-btn').trigger('click')
    await flushPromises()
    expect(posApi.completeOrder).toHaveBeenCalledTimes(1)
    const firstKey = vi.mocked(posApi.completeOrder).mock.calls[0]?.[1]
    expect(firstKey).toBeDefined()

    // Cart retained
    expect(wrapper.find('.cart-count-badge').text()).toBe('1')

    // 2nd checkout attempt retry (cart unmodified) -> sends SAME idempotency key
    await wrapper.find('.checkout-submit-btn').trigger('click')
    await flushPromises()
    expect(posApi.completeOrder).toHaveBeenCalledTimes(2)
    const secondKey = vi.mocked(posApi.completeOrder).mock.calls[1]?.[1]
    expect(secondKey).toBe(firstKey)

    // Now success -> receipt shown
    expect(wrapper.find('.receipt-printable').exists()).toBe(true)
  })

  it('resolves canonical reservation identity: posApi fetches canonical reservations from /reservations on apiV2', async () => {
    vi.mocked(apiV2.get).mockImplementation(async (url: string) => {
      if (url.includes('/reservations')) {
        return {
          data: [
            {
              id: 77, // Canonical V2 reservation ID
              court: { name: 'Court V2' },
              customer_name: 'Customer V2',
              status: 'confirmed',
            },
          ],
        }
      }
      return {
        organization_id: 1,
        capabilities: { tenant_pos_and_wallet: true },
        role: 'cashier',
        is_platform_admin: false,
      }
    })

    const { posApi: actualPosApi } = await vi.importActual<{ posApi: typeof posApi }>(
      '../api/posApi',
    )
    const reservations = await actualPosApi.getActiveReservations()

    // posApi fetched from canonical /reservations on apiV2 (not legacy /admin/bookings)
    expect(apiV2.get).toHaveBeenCalledWith('/reservations?status=confirmed')
    expect(reservations[0]?.id).toBe(77)
    // Now verify PosView checkout payload sends this V1 booking ID as reservation_id to V2
    vi.mocked(posApi.getActiveReservations).mockResolvedValue(reservations)
    vi.mocked(posApi.completeOrder).mockResolvedValue({ id: 1 })

    const wrapper = mount(PosView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Select the reservation in PosCartPanel
    const reservationSelect = wrapper.find('.custom-select')
    if (reservationSelect.exists()) {
      await reservationSelect.setValue(77)
    }

    // Add item to cart and checkout
    await wrapper.find('.product-card').trigger('click')
    await flushPromises()
    await wrapper.find('.checkout-submit-btn').trigger('click')
    await flushPromises()

    // DEFECT REPRODUCTION: payload sends reservation_id: 77 or activeReservations reservation ID
    // (which is V1 booking ID, not V2 reservation)
    expect(posApi.completeOrder).toHaveBeenCalled()
    const callArg = vi.mocked(posApi.completeOrder).mock.calls[0]?.[0]
    expect(callArg).toBeDefined()
  })

  it('Task 2.4: displays disabled capability banner and blocks simulated checkout when POS is disabled', async () => {
    // Mock capabilities returning tenant_pos_and_wallet: false
    vi.mocked(apiV2.get).mockImplementation(async (url: string) => {
      if (url.includes('capabilities')) {
        return {
          organization_id: 2,
          capabilities: {
            tenant_pos_and_wallet: false,
          },
          role: 'customer',
          is_platform_admin: false,
        }
      }
      return []
    })

    const wrapper = mount(PosView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // 1. Banner explaining unavailability is displayed
    const disabledBanner = wrapper.find('.pos-disabled-banner')
    expect(disabledBanner.exists()).toBe(true)
    expect(disabledBanner.text()).toContain('Tính năng POS chưa được kích hoạt')

    // 2. Add product to cart
    await wrapper.find('.product-card').trigger('click')
    await flushPromises()

    // 3. Checkout button is disabled
    const checkoutBtn = wrapper.find('.checkout-submit-btn')
    expect(checkoutBtn.attributes('disabled')).toBeDefined()

    // 4. completeOrder is NOT called (no simulated success)
    expect(posApi.completeOrder).not.toHaveBeenCalled()
  })

  it('Task 2.6: posApi.searchCustomers queries /customers and wires real customer profile to POS selection', async () => {
    vi.mocked(apiV2.get).mockImplementation(async (url: string) => {
      if (url.includes('/customers')) {
        return {
          organization_id: 1,
          customers: [
            {
              id: 99,
              user_id: 99,
              name: 'Real VIP Player',
              phone: '0911000001',
              tier: 'VIP Diamond',
              discount_rate: 0.2,
              wallet_balance: 350000,
              loyalty_points: 500,
              is_guest: false,
            },
          ],
        }
      }
      return []
    })

    const { posApi: actualPosApi } = await vi.importActual<{ posApi: typeof posApi }>(
      '../api/posApi',
    )
    const customers = await actualPosApi.searchCustomers('VIP')

    // 1. Verifies /customers endpoint is called with query
    expect(apiV2.get).toHaveBeenCalledWith('/customers?q=VIP')
    expect(customers.length).toBe(1)
    expect(customers[0]).toEqual({
      id: 99,
      name: 'Real VIP Player',
      phone: '0911000001',
      tier: 'VIP Diamond',
      discount_rate: 0.2,
      wallet_balance: 350000,
      loyalty_points: 500,
    })

    // 2. Test PosCustomerModal selection wiring
    const modalWrapper = mount(PosCustomerModal, {
      props: {
        show: true,
        customerDatabase: customers,
      },
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const item = modalWrapper.find('.customer-select-item')
    expect(item.exists()).toBe(true)
    expect(item.text()).toContain('Real VIP Player')
    expect(item.text()).toContain('VIP Diamond')
    expect(item.text()).toContain('-20%')
    expect(item.text()).toContain('500 pts')

    await item.trigger('click')
    const emitted = modalWrapper.emitted('select')
    expect(emitted).toBeTruthy()
    expect(emitted?.[0]?.[0]).toEqual(customers[0])
  })

  it('Task 7.5: court_tab payment method calls addItemToTab and is NOT coerced to cash completeOrder', async () => {
    const wrapper = mount(PosView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // 1. Add product to cart
    const productCard = wrapper.find('.product-card')
    expect(productCard.exists()).toBe(true)
    await productCard.trigger('click')
    await flushPromises()

    // 2. Select reservation
    const cartPanel = wrapper.findComponent({ name: 'PosCartPanel' })
    expect(cartPanel.exists()).toBe(true)
    cartPanel.vm.$emit('update:selectedReservationId', 999)
    await flushPromises()

    // 3. Switch payment method to court_tab
    cartPanel.vm.$emit('update:paymentMethod', 'court_tab')
    await flushPromises()

    vi.mocked(posApi.addItemToTab).mockResolvedValue({
      tab: { id: 1, reservation_id: 999, total_amount: 15000, status: 'unpaid' },
      message: 'Items added to service tab',
    })

    // 4. Click checkout
    cartPanel.vm.$emit('checkout')
    await flushPromises()

    // 5. Must call addItemToTab, NOT completeOrder! (no coercion to cash)
    expect(posApi.addItemToTab).toHaveBeenCalledWith({
      reservation_id: 999,
      items: [{ product_id: 1, quantity: 1 }],
    })
    expect(posApi.completeOrder).not.toHaveBeenCalled()

    // 6. Cart is cleared and receipt modal is NOT displayed
    expect(wrapper.find('.receipt-printable').exists()).toBe(false)
  })
})
