import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import LoyaltyPointSelector from '../components/LoyaltyPointSelector.vue'
import { bookingApi } from '../api/bookingApi'
import { i18n, setAppLocale } from '@/i18n'

vi.mock('../api/bookingApi', () => ({
  bookingApi: {
    previewLoyalty: vi.fn(),
  },
}))

describe('LoyaltyPointSelector (Tasks 7.1, 7.2, 7.4)', () => {
  const mockPreviewActive = {
    organization_id: 1,
    balance: 500,
    policy: {
      id: 10,
      version: 2,
      points_redeemed: 100,
      reward_value_vnd: 5000,
    },
    points_selected: 0,
    discount_vnd: 0,
    remaining_payable_vnd: 200000,
    max_usable_points: 400,
  }

  beforeEach(() => {
    vi.clearAllMocks()
    setAppLocale('vi')
  })

  it('displays organization point balance and preview in Vietnamese', async () => {
    vi.mocked(bookingApi.previewLoyalty).mockResolvedValue(mockPreviewActive)

    const wrapper = mount(LoyaltyPointSelector, {
      props: {
        courtId: 1,
        slots: ['2026-10-15T08:00:00.000Z'],
      },
      global: { plugins: [i18n] },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Điểm thưởng & Ưu đãi')
    expect(wrapper.text()).toContain('Số dư khả dụng:')
    expect(wrapper.text()).toContain('500')
    expect(wrapper.text()).toContain('điểm')
    expect(bookingApi.previewLoyalty).toHaveBeenCalledWith({
      court_id: 1,
      slots: ['2026-10-15T08:00:00.000Z'],
      quote_token: undefined,
      coupon_code: undefined,
      points: undefined,
    })
  })

  it('displays organization point balance and preview in English when locale is en', async () => {
    setAppLocale('en')
    vi.mocked(bookingApi.previewLoyalty).mockResolvedValue(mockPreviewActive)

    const wrapper = mount(LoyaltyPointSelector, {
      props: {
        courtId: 1,
        slots: ['2026-10-15T08:00:00.000Z'],
      },
      global: { plugins: [i18n] },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Loyalty Points & Rewards')
    expect(wrapper.text()).toContain('Available balance:')
    expect(wrapper.text()).toContain('500')
    expect(wrapper.text()).toContain('pts')
  })

  it('emits change event with policy version, discount, and idempotency key when points are selected', async () => {
    vi.mocked(bookingApi.previewLoyalty).mockImplementation(async (payload) => {
      if (payload.points === 200) {
        return {
          ...mockPreviewActive,
          points_selected: 200,
          discount_vnd: 10000,
          remaining_payable_vnd: 190000,
        }
      }
      return mockPreviewActive
    })

    const wrapper = mount(LoyaltyPointSelector, {
      props: {
        courtId: 1,
        slots: ['2026-10-15T08:00:00.000Z'],
      },
      global: { plugins: [i18n] },
    })
    await flushPromises()

    const input = wrapper.find('.loyalty-input')
    await input.setValue('200')
    await input.trigger('change')
    await flushPromises()

    // Assert change event payload
    const emitted = wrapper.emitted('change')
    expect(emitted).toBeTruthy()
    const lastPayload = emitted![emitted!.length - 1]![0]
    expect(lastPayload).toEqual({
      loyalty_points: 200,
      loyalty_policy_version: 2,
      idempotency_key: expect.any(String),
      discount_vnd: 10000,
      remaining_payable_vnd: 190000,
    })

    // Breakdown is displayed
    expect(wrapper.text()).toContain('Giảm giá điểm thưởng:')
    expect(wrapper.text()).toContain('-10.000')
    expect(wrapper.text()).toContain('190.000')
  })

  it('quick select buttons (50%, Max, Clear) compute and apply points correctly', async () => {
    vi.mocked(bookingApi.previewLoyalty).mockImplementation(async (payload) => {
      const pts = payload.points || 0
      return {
        ...mockPreviewActive,
        points_selected: pts,
        discount_vnd: (pts / 100) * 5000,
        remaining_payable_vnd: 200000 - (pts / 100) * 5000,
      }
    })

    const wrapper = mount(LoyaltyPointSelector, {
      props: {
        courtId: 1,
        slots: ['2026-10-15T08:00:00.000Z'],
      },
      global: { plugins: [i18n] },
    })
    await flushPromises()

    // 1. Click Max (100% of maxUsablePoints = 400)
    const quickButtons = wrapper.findAll('.btn-quick')
    const maxBtn = quickButtons.find((b) => b.text() === 'Tối đa')
    expect(maxBtn).toBeDefined()
    await maxBtn!.trigger('click')
    await flushPromises()

    expect((wrapper.find('.loyalty-input').element as HTMLInputElement).value).toBe('400')

    // 2. Click Clear
    const clearBtn = wrapper.find('.btn-quick.clear')
    expect(clearBtn.exists()).toBe(true)
    await clearBtn.trigger('click')
    await flushPromises()

    expect((wrapper.find('.loyalty-input').element as HTMLInputElement).value).toBe('')
  })

  it('explains no-policy state without blocking standard checkout', async () => {
    vi.mocked(bookingApi.previewLoyalty).mockResolvedValue({
      organization_id: 2,
      balance: 300,
      policy: null,
      points_selected: 0,
      discount_vnd: 0,
      remaining_payable_vnd: 150000,
      max_usable_points: 0,
    })

    const wrapper = mount(LoyaltyPointSelector, {
      props: {
        courtId: 2,
        slots: ['2026-10-15T08:00:00.000Z'],
      },
      global: { plugins: [i18n] },
    })
    await flushPromises()

    // Friendly notice is displayed
    expect(wrapper.text()).toContain('Cơ sở này hiện chưa áp dụng chính sách đổi điểm thưởng.')
    // Input is NOT shown, but no error or blocking state is raised
    expect(wrapper.find('.loyalty-input').exists()).toBe(false)
  })

  it('explains zero balance state when customer has no points in organization', async () => {
    vi.mocked(bookingApi.previewLoyalty).mockResolvedValue({
      ...mockPreviewActive,
      balance: 0,
      max_usable_points: 0,
    })

    const wrapper = mount(LoyaltyPointSelector, {
      props: {
        courtId: 1,
        slots: ['2026-10-15T08:00:00.000Z'],
      },
      global: { plugins: [i18n] },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Bạn chưa có điểm thưởng tại cơ sở này.')
    expect(wrapper.find('.loyalty-input').exists()).toBe(false)
  })

  it('discards stale preview responses when target court or slots change rapidly', async () => {
    const { promise: slowPromise, resolve: resolveSlow } = (Promise as any).withResolvers()

    vi.mocked(bookingApi.previewLoyalty).mockImplementation(async (payload) => {
      if (payload.court_id === 1) {
        return slowPromise
      }
      return {
        ...mockPreviewActive,
        organization_id: 2,
        balance: 999,
        remaining_payable_vnd: 120000,
      }
    })

    const wrapper = mount(LoyaltyPointSelector, {
      props: {
        courtId: 1,
        slots: ['2026-10-15T08:00:00.000Z'],
      },
      global: { plugins: [i18n] },
    })

    // Rapidly switch court to 2
    await wrapper.setProps({ courtId: 2 })
    await flushPromises()

    expect(wrapper.text()).toContain('999')

    // Late response from court 1 resolves
    resolveSlow({
      ...mockPreviewActive,
      organization_id: 1,
      balance: 100,
      remaining_payable_vnd: 300000,
    })
    await flushPromises()

    // Must NOT overwrite court 2's state with late court 1 response!
    expect(wrapper.text()).toContain('999')
    expect(wrapper.text()).not.toContain('100')
  })
})
