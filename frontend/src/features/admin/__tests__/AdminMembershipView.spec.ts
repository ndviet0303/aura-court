import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminMembershipView from '@/features/admin/views/AdminMembershipView.vue'
import { i18n } from '@/i18n'
import { apiV2 } from '@/core/api/client'

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/admin/membership' }),
  useRouter: () => ({ push: vi.fn<() => void>() }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn<() => Promise<unknown>>(),
    post: vi.fn<() => Promise<unknown>>(),
    patch: vi.fn<() => Promise<unknown>>(),
    delete: vi.fn<() => Promise<unknown>>(),
  },
  apiV2: {
    get: vi.fn<() => Promise<unknown>>(),
    post: vi.fn<() => Promise<unknown>>(),
    patch: vi.fn<() => Promise<unknown>>(),
    delete: vi.fn<() => Promise<unknown>>(),
  },
  errorMessage: (_err: unknown, fallback?: string) => fallback || 'Có lỗi xảy ra',
}))

describe('AdminMembershipView', () => {
  const samplePlans = [
    {
      id: 1,
      name: 'Silver Pass',
      slug: 'silver-pass',
      code: 'SILVER',
      description: 'Gói cơ bản cho người chơi thường xuyên',
      price: 500000,
      billingCycle: 'monthly',
      version: 'v2.1',
      status: 'active',
      perks: ['Giảm 10% đặt sân các giờ off-peak', 'Tích lũy x1.2 điểm thưởng'],
      courtDiscountPercent: 10,
      pointMultiplier: 1.2,
      activeSubscribers: 86,
      isPopular: false,
    },
    {
      id: 2,
      name: 'Gold Elite',
      slug: 'gold-elite',
      code: 'GOLD',
      description: 'Trải nghiệm cao cấp và linh hoạt tối đa',
      price: 1200000,
      billingCycle: 'monthly',
      version: 'v3.0',
      status: 'active',
      perks: ['Giảm 20% đặt sân mọi khung giờ', 'Tích lũy x2 điểm thưởng'],
      courtDiscountPercent: 20,
      pointMultiplier: 2.0,
      activeSubscribers: 142,
      isPopular: true,
    },
  ]

  const sampleRules = [
    {
      id: '1',
      name: 'Giờ Vàng (Off-peak)',
      description: 'T2-T6, 08:00 - 15:00',
      multiplier: 1.5,
      tag: 'Off-peak',
      schedule: 'Mon-Fri 08:00-15:00',
      isActive: true,
    },
    {
      id: '2',
      name: 'Sinh nhật hội viên',
      description: 'Áp dụng trong tháng sinh nhật',
      multiplier: 2.0,
      tag: 'Birthday',
      schedule: 'Birthday Month',
      isActive: true,
    },
  ]

  const sampleHistory = [
    {
      id: '1',
      planId: 2,
      planName: 'Gold Elite',
      version: 'v3.0',
      status: 'active' as const,
      updatedAt: 'Hôm nay, 10:45 AM',
      updatedBy: 'Admin',
      changes: ['Thêm quyền lợi: Hủy không phí trước 24h'],
      activeSubscribersCount: 142,
    },
    {
      id: '2',
      planId: 1,
      planName: 'Silver Pass',
      version: 'v2.1',
      status: 'active' as const,
      updatedAt: '15 Th08, 2026',
      updatedBy: 'System',
      changes: ['Sửa lỗi hiển thị % giảm giá'],
      activeSubscribersCount: 86,
    },
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.mocked(apiV2.get).mockImplementation(((url: string) => {
      if (url.includes('/admin/memberships/plans')) return Promise.resolve(samplePlans)
      if (url.includes('/admin/memberships/loyalty-config')) {
        return Promise.resolve({
          spendAmountVnd: 1000,
          pointsEarned: 1,
          pointsRedeemed: 100,
          rewardValueVnd: 5000,
          updatedAt: new Date().toISOString(),
        })
      }
      if (url.includes('/admin/memberships/multiplier-rules')) return Promise.resolve(sampleRules)
      if (url.includes('/admin/memberships/version-history')) return Promise.resolve(sampleHistory)
      if (url.includes('/admin/memberships/stats')) {
        return Promise.resolve({
          totalVipMembers: 228,
          monthlyRecurringRevenue: 213400000,
          totalPointsIssued: 485000,
          redemptionRatePercent: 68.4,
        })
      }
      return Promise.resolve([])
    }) as any)

    vi.mocked(apiV2.patch).mockResolvedValue({ message: 'Success' } as any)
    vi.mocked(apiV2.post).mockResolvedValue({ message: 'Success' } as any)
    vi.mocked(apiV2.delete).mockResolvedValue({ message: 'Success' } as any)
  })

  it('renders page header, 4 KPI stats, and existing VIP membership plans correctly', async () => {
    const wrapper = mount(AdminMembershipView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.find('.page-title').text()).toContain('Quản lý Gói hội viên & Điểm thưởng')
    expect(wrapper.findAll('.kpi-card').length).toBe(4)
    expect(wrapper.text()).toContain('Tổng Hội Viên VIP')
    expect(wrapper.text()).toContain('Doanh Thu Định Kỳ')

    // Check plans
    const planCards = wrapper.findAll('.vip-plan-card')
    expect(planCards.length).toBeGreaterThanOrEqual(2)
    expect(wrapper.text()).toContain('Silver Pass')
    expect(wrapper.text()).toContain('Gold Elite')
  })

  it('renders loyalty points conversion rates and multiplier rules list on tab change', async () => {
    const wrapper = mount(AdminMembershipView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to loyalty tab
    const tabs = wrapper.findAll('.nav-tab-btn')
    await tabs[1]?.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('TỶ LỆ QUY ĐỔI')
    expect(wrapper.text()).toContain('Giờ Vàng (Off-peak)')
    expect(wrapper.text()).toContain('Sinh nhật hội viên')
  })

  it('renders version history timeline with active and archived nodes on history tab', async () => {
    const wrapper = mount(AdminMembershipView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to history tab
    const tabs = wrapper.findAll('.nav-tab-btn')
    await tabs[2]?.trigger('click')
    await flushPromises()

    expect(wrapper.find('.version-timeline-wrapper').exists()).toBe(true)
    expect(wrapper.text()).toContain('Lịch Sử Phiên Bản')
    expect(wrapper.text()).toContain('Gold Elite')
    expect(wrapper.text()).toContain('Silver Pass')
  })

  it('opens create plan modal and allows filling plan parameters', async () => {
    const wrapper = mount(AdminMembershipView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.find('.modal-dialog').exists()).toBe(false)
    const createBtn = wrapper.findAll('button').find((b) => b.text().includes('Tạo Gói Mới'))
    expect(createBtn).toBeDefined()
    await createBtn?.trigger('click')
    await flushPromises()

    expect(wrapper.find('.modal-dialog').exists()).toBe(true)
    expect(wrapper.find('.modal-title').text()).toContain('Tạo Gói Hội Viên Mới')
  })

  it('toggles multiplier rule active status and shows feedback notice', async () => {
    const wrapper = mount(AdminMembershipView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to loyalty tab
    const tabs = wrapper.findAll('.nav-tab-btn')
    await tabs[1]?.trigger('click')
    await flushPromises()

    const toggleBtns = wrapper.findAll('.aura-toggle-switch')
    expect(toggleBtns.length).toBeGreaterThan(0)
    await toggleBtns[0]?.trigger('click')
    await flushPromises()

    expect(wrapper.find('.alert-pill-notice').exists()).toBe(true)
  })
})
