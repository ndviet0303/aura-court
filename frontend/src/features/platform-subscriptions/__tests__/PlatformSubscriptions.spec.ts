import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { usePlatformSubscription } from '../composables/usePlatformSubscription'
import { platformSubscriptionApi } from '../api/platformSubscriptionApi'
import PlanCatalogCard from '../components/PlanCatalogCard.vue'
import SubscriptionStatusCard from '../components/SubscriptionStatusCard.vue'
import PlatformSubscriptionView from '../views/PlatformSubscriptionView.vue'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../api/platformSubscriptionApi', () => ({
  platformSubscriptionApi: {
    getPlans: vi.fn(),
    getCurrentSubscription: vi.fn(),
    selectPlan: vi.fn(),
    activateTrial: vi.fn(),
    initiatePayment: vi.fn(),
    confirmPayment: vi.fn(),
    cancelRenewal: vi.fn(),
    renew: vi.fn(),
  },
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: { organizationId: '2' },
    query: { payment_attempt_id: '99' },
  }),
  useRouter: () => ({
    push: vi.fn(),
  }),
}))

describe('usePlatformSubscription composable', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches plans and current subscription', async () => {
    vi.mocked(platformSubscriptionApi.getPlans).mockResolvedValueOnce({
      data: [
        {
          id: 1,
          code: 'standard',
          name: 'Gói Cơ Bản',
          price: 500000,
          currency: 'VND',
          billing_cycle: 'monthly',
          trial_period_days: 14,
          max_venues: 2,
          max_courts: 10,
          max_staff: 5,
          features: ['booking', 'pricing'],
          is_active: true,
        },
      ],
    })

    vi.mocked(platformSubscriptionApi.getCurrentSubscription).mockResolvedValueOnce({
      data: {
        id: 10,
        organization_id: 2,
        plan_id: 1,
        status: 'active',
        starts_at: '2026-09-01T00:00:00Z',
        ends_at: '2026-10-01T00:00:00Z',
        auto_renew: true,
      },
      trial_eligible: true,
      can_take_bookings: true,
      quotas: {
        venues_count: 1,
        max_venues: 2,
        courts_count: 4,
        max_courts: 10,
        staff_count: 2,
        max_staff: 5,
      },
    })

    const composable = usePlatformSubscription()
    const plans = await composable.fetchPlans()
    expect(plans).toHaveLength(1)
    expect(composable.plans.value[0]?.code).toBe('standard')

    const sub = await composable.fetchCurrent(2)
    expect(sub?.status).toBe('active')
    expect(composable.canTakeBookings.value).toBe(true)
    expect(composable.hasActiveSubscription.value).toBe(true)
  })

  it('confirmReturn verifies payment with backend and refreshes persisted status', async () => {
    vi.mocked(platformSubscriptionApi.confirmPayment).mockResolvedValueOnce({
      data: { id: 10 } as any,
      message: 'Xác nhận thành công',
    })

    vi.mocked(platformSubscriptionApi.getCurrentSubscription).mockResolvedValueOnce({
      data: {
        id: 10,
        organization_id: 2,
        plan_id: 1,
        status: 'active',
        starts_at: '2026-09-01T00:00:00Z',
        ends_at: '2026-10-01T00:00:00Z',
        auto_renew: true,
      },
      trial_eligible: false,
      can_take_bookings: true,
    })

    const composable = usePlatformSubscription()
    const result = await composable.confirmReturn(2, 99)

    expect(platformSubscriptionApi.confirmPayment).toHaveBeenCalledWith({ payment_attempt_id: 99 })
    expect(platformSubscriptionApi.getCurrentSubscription).toHaveBeenCalledWith(2)
    expect(result).toBe(true)
  })
})

describe('PlanCatalogCard.vue', () => {
  const samplePlan = {
    id: 1,
    code: 'pro',
    name: 'Gói Chuyên Nghiệp',
    description: 'Dành cho CLB quy mô lớn',
    price: 1200000,
    currency: 'VND',
    billing_cycle: 'monthly',
    trial_period_days: 14,
    max_venues: 5,
    max_courts: 25,
    max_staff: 15,
    features: ['all'],
    is_active: true,
  }

  it('renders quotas, price in VND and emits events', async () => {
    const wrapper = mount(PlanCatalogCard, {
      props: {
        plan: samplePlan,
        trialEligible: true,
      },
    })

    expect(wrapper.text()).toContain('Gói Chuyên Nghiệp')
    expect(wrapper.text()).toContain('Tối đa 5 cơ sở thể thao')
    expect(wrapper.text()).toContain('Tối đa 25 sân hoạt động')
    expect(wrapper.text()).toContain('Tối đa 15 nhân viên')

    const trialBtn = wrapper.findAll('button').find((b) => b.text().includes('Dùng thử 14 ngày'))
    expect(trialBtn).toBeDefined()
    await trialBtn?.trigger('click')
    expect(wrapper.emitted('selectTrial')).toBeTruthy()

    const paidBtn = wrapper.findAll('button').find((b) => b.text().includes('Đăng ký ngay'))
    expect(paidBtn).toBeDefined()
    await paidBtn?.trigger('click')
    expect(wrapper.emitted('selectPaid')).toBeTruthy()
  })
})

describe('SubscriptionStatusCard.vue', () => {
  it('renders active status and quota usage', async () => {
    const wrapper = mount(SubscriptionStatusCard, {
      props: {
        subscription: {
          id: 10,
          organization_id: 2,
          plan_id: 1,
          plan: { name: 'Gói Cơ Bản' } as any,
          status: 'active',
          starts_at: '2026-09-01T00:00:00Z',
          ends_at: '2026-10-01T00:00:00Z',
          auto_renew: true,
        },
        canTakeBookings: true,
        quotas: {
          venues_count: 1,
          max_venues: 2,
          courts_count: 4,
          max_courts: 10,
          staff_count: 2,
          max_staff: 5,
        },
      },
    })

    expect(wrapper.text()).toContain('Gói Cơ Bản')
    expect(wrapper.text()).toContain('Đang kích hoạt')
    expect(wrapper.text()).toContain('1 / 2')
    expect(wrapper.text()).toContain('4 / 10')
  })
})

describe('PlatformSubscriptionView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('mounts and processes return parameter', async () => {
    vi.mocked(platformSubscriptionApi.getPlans).mockResolvedValueOnce({
      data: [],
    })
    vi.mocked(platformSubscriptionApi.getCurrentSubscription).mockResolvedValue({
      data: {
        id: 10,
        organization_id: 2,
        plan_id: 1,
        status: 'active',
        starts_at: '2026-09-01T00:00:00Z',
        ends_at: '2026-10-01T00:00:00Z',
        auto_renew: true,
      },
      trial_eligible: false,
      can_take_bookings: true,
    })
    vi.mocked(platformSubscriptionApi.confirmPayment).mockResolvedValueOnce({
      data: {} as any,
      message: 'OK',
    })

    const wrapper = mount(PlatformSubscriptionView)
    await wrapper.vm.$nextTick()
    await new Promise((r) => setTimeout(r, 20))

    expect(wrapper.text()).toContain('Gói Dịch vụ Nền tảng')
    expect(platformSubscriptionApi.confirmPayment).toHaveBeenCalledWith({ payment_attempt_id: 99 })
  })
})
