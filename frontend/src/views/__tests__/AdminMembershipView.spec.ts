import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminMembershipView from '@/features/admin/views/AdminMembershipView.vue'
import { i18n } from '@/i18n'
import { apiV2 } from '@/core/api/client'

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/admin/membership' }),
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
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  errorMessage: (_err: unknown, fallback?: string) => fallback || 'Có lỗi xảy ra',
}))

describe('AdminMembershipView', () => {
  const samplePlans = [
    {
      id: 'silver-pass',
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
      id: 'gold-elite',
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
      id: 'mult-offpeak',
      name: 'Giờ Vàng (Off-peak)',
      description: 'T2-T6, 08:00 - 15:00',
      multiplier: 1.5,
      tag: 'Off-peak',
      schedule: 'Mon-Fri 08:00-15:00',
      isActive: true,
    },
    {
      id: 'mult-birthday',
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
      id: 'aud-1',
      planId: 'gold-elite',
      planName: 'Gold Elite',
      version: 'v3.0',
      status: 'active',
      updatedAt: 'Hôm nay, 10:45 AM',
      updatedBy: 'Admin',
      changes: ['Thêm quyền lợi: Hủy không phí trước 24h'],
      activeSubscribersCount: 142,
    },
    {
      id: 'aud-2',
      planId: 'silver-pass',
      planName: 'Silver Pass',
      version: 'v2.1',
      status: 'active',
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

    vi.mocked(apiV2.patch).mockResolvedValue({ message: 'Success' })
    vi.mocked(apiV2.post).mockResolvedValue({ message: 'Success' })
  })

  it('renders page header, 4 KPI stats, and existing membership plans correctly', async () => {
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
    const planCards = wrapper.findAll('.plan-card')
    expect(planCards.length).toBeGreaterThanOrEqual(2)
    expect(wrapper.text()).toContain('Silver Pass')
    expect(wrapper.text()).toContain('Gold Elite')
    expect(wrapper.text()).toContain('500.000đ')
    expect(wrapper.text()).toContain('1.200.000đ')
  })

  it('renders loyalty points conversion rates and multiplier rules list', async () => {
    const wrapper = mount(AdminMembershipView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Cấu hình Tích Điểm & Đổi Thưởng')
    expect(wrapper.text()).toContain('TỶ LỆ QUY ĐỔI')
    expect(wrapper.text()).toContain('HỆ SỐ NHÂN (MULTIPLIER)')
    expect(wrapper.text()).toContain('Giờ Vàng (Off-peak)')
    expect(wrapper.text()).toContain('Sinh nhật hội viên')
  })

  it('renders version history timeline with active and archived nodes', async () => {
    const wrapper = mount(AdminMembershipView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.find('.version-timeline-card').exists()).toBe(true)
    expect(wrapper.text()).toContain('Lịch sử phiên bản')
    expect(wrapper.text()).toContain('Gold Elite v3.0')
    expect(wrapper.text()).toContain('Silver Pass v2.1')
  })

  it('opens create plan modal and allows filling plan parameters', async () => {
    const wrapper = mount(AdminMembershipView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.find('.modal-box').exists()).toBe(false)
    await wrapper.find('.btn-create-plan').trigger('click')
    await flushPromises()

    expect(wrapper.find('.modal-box').exists()).toBe(true)
    expect(wrapper.find('.modal-title').text()).toContain('Tạo Gói Hội Viên Mới')
  })

  it('opens edit plan modal when clicking edit button on a plan card', async () => {
    const wrapper = mount(AdminMembershipView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const editBtns = wrapper.findAll('.btn-edit-plan')
    expect(editBtns.length).toBeGreaterThan(0)
    await editBtns[0]?.trigger('click')
    await flushPromises()

    expect(wrapper.find('.modal-box').exists()).toBe(true)
    expect(wrapper.find('.modal-title').text()).toContain('Chỉnh sửa Silver Pass')
  })

  it('toggles multiplier rule active status and shows feedback notice', async () => {
    const wrapper = mount(AdminMembershipView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const toggleBtns = wrapper.findAll('.btn-toggle-rule')
    expect(toggleBtns.length).toBeGreaterThan(0)
    await toggleBtns[0]?.trigger('click')
    await flushPromises()

    expect(wrapper.find('.alert-pill-notice').exists()).toBe(true)
  })

  it('calculates points and voucher value in interactive simulator', async () => {
    const wrapper = mount(AdminMembershipView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const simInput = wrapper.find('.sim-input')
    expect(simInput.exists()).toBe(true)
    await simInput.setValue(1000000)
    await flushPromises()

    expect(wrapper.find('.sim-res-pts').text()).toContain('+')
  })
})
