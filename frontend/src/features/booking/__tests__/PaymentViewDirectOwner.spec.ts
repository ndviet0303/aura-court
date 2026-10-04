import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { createI18n } from 'vue-i18n'
import PaymentView from '../views/PaymentView.vue'
import viMessages from '@/i18n/locales/vi'

const mocks = vi.hoisted(() => ({ state: null as any, push: vi.fn() }))

vi.mock('../composables/usePaymentProcessing', () => ({
  usePaymentProcessing: () => mocks.state,
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mocks.push }),
  useRoute: () => ({ params: { code: 'BK-DIRECT-1' }, query: {}, fullPath: '/payment/BK-DIRECT-1' }),
  RouterLink: { template: '<a><slot /></a>' },
}))

const i18n = createI18n({ legacy: false, locale: 'vi', messages: { vi: viMessages } })

describe('PaymentView direct owner checkout', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mocks.state = {
      loading: ref(false),
      isPaying: ref(false),
      isTournament: ref(false),
      tournament: ref(null),
      payment: ref(null),
      booking: ref({
        id: 1,
        code: 'BK-DIRECT-1',
        status: 'pending_payment',
        payment_model: 'direct_owner_v1',
        total_amount: 100000,
        court: null,
        slots: [],
      }),
      court: ref(null),
      playerDetails: ref({ name: 'Player', phone: '0900000000', email: 'player@example.test', partnerName: '' }),
      walletBalance: ref(0),
      paymentAmount: ref(100000),
      directPaymentAttempt: ref({
        id: 31,
        provider: 'sepay',
        reference: 'ACDIRECT31',
        transfer_content: 'ACDIRECT31',
        amount: 100000,
        currency: 'VND',
        status: 'pending',
        expires_at: '2026-09-25T12:30:00Z',
        paid_at: null,
        receiver: { bank_code: 'MBBank', account_name: 'Venue Owner', account_number: '0123456789' },
        qr_url: 'https://img.vietqr.io/image/MB-0123456789-compact2.png?amount=100000&addInfo=ACDIRECT31',
      }),
      directPaymentError: ref(''),
      directPaymentLoading: ref(false),
      directPaymentRefreshing: ref(false),
      isDirectOwnerPayment: ref(true),
      status: ref('pending_payment'),
      error: ref(''),
      showSuccessModal: ref(false),
      couponCode: ref(''),
      couponLoading: ref(false),
      couponError: ref(''),
      couponSuccess: ref(''),
      applyCoupon: vi.fn(),
      handleWalletPay: vi.fn(),
      startTimers: vi.fn(),
      stopTimers: vi.fn(),
      refreshDirectPaymentStatus: vi.fn(),
      createDirectPaymentAttempt: vi.fn(),
    }
  })

  it('shows receiver-bound QR and never renders wallet payment for a direct reservation', async () => {
    const wrapper = mount(PaymentView, { global: { plugins: [i18n] } })
    await flushPromises()

    expect(wrapper.find('[data-testid="owner-payment-instructions"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="owner-payment-qr"]').attributes('src')).toContain('ACDIRECT31')
    expect(wrapper.find('.wallet-pay-card').exists()).toBe(false)
    expect(wrapper.find('.summary-coupon-block').exists()).toBe(false)
    expect(wrapper.text()).toContain('0123456789')
    expect(mocks.state.startTimers).toHaveBeenCalledOnce()
    wrapper.unmount()
  })
})
