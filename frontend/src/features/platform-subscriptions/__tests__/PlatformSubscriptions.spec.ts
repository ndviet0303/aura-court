import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { usePlatformSubscription } from '../composables/usePlatformSubscription'
import { platformSubscriptionApi } from '../api/platformSubscriptionApi'
import PlanCatalogCard from '../components/PlanCatalogCard.vue'
import SubscriptionStatusCard from '../components/SubscriptionStatusCard.vue'
import PlatformSubscriptionView from '../views/PlatformSubscriptionView.vue'
import type { CurrentSubscriptionResponse, PaymentAttempt, PlatformPlan, PlatformSubscription } from '../types'

vi.mock('../api/platformSubscriptionApi', () => ({
  platformSubscriptionApi: {
    getPlans: vi.fn(),
    getCurrentSubscription: vi.fn(),
    selectPlan: vi.fn(),
    activateTrial: vi.fn(),
    initiatePayment: vi.fn(),
    cancelRenewal: vi.fn(),
    renew: vi.fn(),
  },
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { organizationId: '2' }, query: { payment_return: '1' } }),
  useRouter: () => ({ push: vi.fn() }),
}))

const plan: PlatformPlan = {
  id: 1,
  code: 'pro',
  name: 'Gói Chuyên Nghiệp',
  version: 3,
  price: 1200000,
  price_formatted: '1.200.000₫',
  currency: 'VND',
  billing_cycle: 'monthly',
  trial_days: 14,
  trial_enabled: true,
  quotas: { max_venues: 5, max_courts: 25, max_staff: 15 },
  features: ['all'],
  terms: { version: '2026.3', text: 'Điều khoản đã được phê duyệt.' },
  fee_disclosure: { tax_note: 'Tax', payment_processing_fee: 'Fee', refund_policy: 'Refund' },
  status: 'published',
  is_active: true,
  published_at: '2026-09-01T00:00:00Z',
  retired_at: null,
}

const subscription: PlatformSubscription = {
  id: 10,
  organization_id: 2,
  plan_id: 1,
  plan_name: plan.name,
  plan_code: plan.code,
  plan_version: 3,
  status: 'active',
  is_valid: true,
  is_trial: false,
  price: 1200000,
  currency: 'VND',
  billing_cycle: 'monthly',
  starts_at: '2026-09-01T00:00:00Z',
  ends_at: '2026-10-01T00:00:00Z',
  trial_starts_at: null,
  trial_ends_at: null,
  trial_days_remaining: 0,
  auto_renew: true,
  accepted_terms_version: '2026.3',
  quotas: { max_venues: 5, max_courts: 25, max_staff: 15 },
  features: ['all'],
}

const succeededAttempt: PaymentAttempt = {
  id: 99,
  organization_id: 2,
  plan_id: 1,
  plan_version: 3,
  reference_code: 'SUB-PAY-20260924-1234ABCD',
  status: 'succeeded',
  amount: 1200000,
  currency: 'VND',
  provider: 'payos',
  checkout_url: 'https://pay.example/checkout',
  qr_code: 'data:image/svg+xml;utf8,<svg>qr</svg>',
  created_at: '2026-09-24T00:00:00Z',
}

function currentResponse(overrides: Partial<CurrentSubscriptionResponse> = {}): CurrentSubscriptionResponse {
  return {
    data: subscription,
    has_active_subscription: true,
    has_used_trial: false,
    trial_eligible: false,
    can_take_bookings: true,
    payment_attempt: null,
    quotas: { venues_count: 1, max_venues: 5, courts_count: 4, max_courts: 25, staff_count: 2, max_staff: 15 },
    ...overrides,
  }
}

describe('usePlatformSubscription', () => {
  beforeEach(() => vi.clearAllMocks())

  it('fetches the public plan catalog and persisted subscription status', async () => {
    vi.mocked(platformSubscriptionApi.getPlans).mockResolvedValueOnce({ data: [plan] })
    vi.mocked(platformSubscriptionApi.getCurrentSubscription).mockResolvedValueOnce(currentResponse())

    const composable = usePlatformSubscription()
    expect(await composable.fetchPlans()).toEqual([plan])
    expect(composable.plans.value[0]?.terms.version).toBe('2026.3')
    expect((await composable.fetchCurrent(2))?.status).toBe('active')
    expect(composable.canTakeBookings.value).toBe(true)
    expect(composable.hasActiveSubscription.value).toBe(true)
  })

  it('refreshes persisted status on a payment return without confirming from the browser', async () => {
    vi.mocked(platformSubscriptionApi.getCurrentSubscription).mockResolvedValueOnce(
      currentResponse({ payment_attempt: succeededAttempt })
    )

    const composable = usePlatformSubscription()
    expect(await composable.confirmReturn(2)).toBe(true)
    expect(platformSubscriptionApi.getCurrentSubscription).toHaveBeenCalledWith(2)
    expect(composable.paymentAttempt.value?.status).toBe('succeeded')
    expect(platformSubscriptionApi.selectPlan).not.toHaveBeenCalled()
  })

  it('sends the selected plan id and accepted terms to real payment initiation', async () => {
    const pendingAttempt: PaymentAttempt = { ...succeededAttempt, status: 'pending' }
    vi.mocked(platformSubscriptionApi.selectPlan).mockResolvedValueOnce({
      data: {
        organization_id: 2,
        plan_id: 1,
        plan_name: plan.name,
        plan_version: plan.version,
        price: plan.price,
        currency: plan.currency,
        billing_cycle: plan.billing_cycle,
        accepted_terms_version: plan.terms.version,
        trial_available: false,
        trial_days: 0,
      },
      message: 'Selected',
    })
    vi.mocked(platformSubscriptionApi.initiatePayment).mockResolvedValueOnce({ data: pendingAttempt, message: 'Pending' })
    vi.mocked(platformSubscriptionApi.getCurrentSubscription).mockResolvedValueOnce(currentResponse({
      data: null,
      has_active_subscription: false,
      can_take_bookings: false,
      trial_eligible: true,
      payment_attempt: pendingAttempt,
    }))

    const composable = usePlatformSubscription()
    await composable.initiatePayment(2, plan, 'https://app.example/subscription?payment_return=1', 'https://app.example/subscription?payment_cancelled=1')

    expect(platformSubscriptionApi.selectPlan).toHaveBeenCalledWith(2, {
      plan_id: 1,
      accepted_terms_version: '2026.3',
    })
    expect(platformSubscriptionApi.initiatePayment).toHaveBeenCalledWith(2, {
      plan_id: 1,
      accepted_terms_version: '2026.3',
      return_url: 'https://app.example/subscription?payment_return=1',
      cancel_url: 'https://app.example/subscription?payment_cancelled=1',
    })
    expect(composable.paymentAttempt.value?.status).toBe('pending')
  })
})

describe('PlanCatalogCard.vue', () => {
  it('renders server terms and quotas and emits the selected plan', async () => {
    const wrapper = mount(PlanCatalogCard, { props: { plan, trialEligible: true } })
    expect(wrapper.text()).toContain('Gói Chuyên Nghiệp')
    expect(wrapper.text()).toContain('Tối đa 5 cơ sở thể thao')
    expect(wrapper.text()).toContain('Tối đa 25 sân hoạt động')
    expect(wrapper.text()).toContain('Tối đa 15 nhân viên')
    expect(wrapper.text()).toContain('2026.3')
    expect(wrapper.text()).toContain('Điều khoản đã được phê duyệt.')

    const trialButton = wrapper.findAll('button').find((button) => button.text().includes('Dùng thử 14 ngày'))
    await trialButton?.trigger('click')
    expect(wrapper.emitted('selectTrial')?.[0]?.[0]).toEqual(plan)
  })
})

describe('SubscriptionStatusCard.vue', () => {
  it('renders the server subscription and quota usage', () => {
    const wrapper = mount(SubscriptionStatusCard, {
      props: {
        subscription,
        canTakeBookings: true,
        quotas: currentResponse().quotas,
      },
    })

    expect(wrapper.text()).toContain('Gói Chuyên Nghiệp')
    expect(wrapper.text()).toContain('Đang kích hoạt')
    expect(wrapper.text()).toContain('1 / 5')
    expect(wrapper.text()).toContain('4 / 25')
  })
})

describe('PlatformSubscriptionView.vue', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('shows the server payment QR and status after returning from checkout', async () => {
    vi.mocked(platformSubscriptionApi.getPlans).mockResolvedValueOnce({ data: [plan] })
    const pendingAttempt = { ...succeededAttempt, status: 'pending' }
    vi.mocked(platformSubscriptionApi.getCurrentSubscription).mockResolvedValue(currentResponse({
      data: null,
      has_active_subscription: false,
      can_take_bookings: false,
      trial_eligible: true,
      payment_attempt: pendingAttempt,
    }))

    const wrapper = mount(PlatformSubscriptionView)
    await new Promise((resolve) => setTimeout(resolve, 20))

    expect(wrapper.text()).toContain('SUB-PAY-20260924-1234ABCD')
    expect(wrapper.text()).toContain('Đang chờ xác minh')
    expect(wrapper.find('img[alt="Mã QR thanh toán thuê bao"]').attributes('src')).toBe(pendingAttempt.qr_code)
    expect(platformSubscriptionApi.getCurrentSubscription).toHaveBeenCalledWith(2)
  })
})
