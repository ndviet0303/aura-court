import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import DropInSessionBillModal from '../components/DropInSessionBillModal.vue'
import { i18n } from '@/i18n'
import { api } from '@/core/api/client'
import type { SocialMatch, DropInSessionBill } from '../types/social'

vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn<(url: string) => Promise<unknown>>(),
    post: vi.fn<(url: string, payload?: unknown) => Promise<unknown>>(),
    put: vi.fn<(url: string, payload?: unknown) => Promise<unknown>>(),
  },
  errorMessage: (_err: unknown, fallback: string) => fallback,
}))

describe('DropInSessionBillModal', () => {
  const mockMatchOpen: SocialMatch = {
    id: 101,
    title: 'Giao lưu cầu lông tối thứ 4',
    sport_type: 'badminton',
    sport_name: 'Cầu lông',
    sport_icon: 'sports_tennis',
    venue_name: 'Aura Court Cầu Giấy',
    court_name: 'Sân số 1',
    city: 'Hà Nội',
    starts_at: '18:00 · 16/09/2026',
    joined_count: 6,
    required_participants: 8,
    visibility: 'public',
    is_owner_hosted: true,
    category: 'giao lưu',
    skill_levels: ['TBY', 'TB'],
    status: 'open',
    host_name: 'Chủ sân Aura',
    is_host: true,
    participants: [],
  }

  const mockMatchEnded: SocialMatch = {
    ...mockMatchOpen,
    id: 102,
    status: 'ended',
  }

  const mockFinalizedBill: DropInSessionBill = {
    id: 55,
    match_post_id: 102,
    organization_id: 1,
    actual_headcount: 8,
    court_duration_minutes: 120,
    court_hourly_rate: 150000,
    shuttlecock_count: 8,
    shuttlecock_unit_price: 25000,
    court_subtotal: 300000,
    shuttlecock_subtotal: 200000,
    total_amount: 500000,
    per_person_amount: 62500,
    is_approximate: false,
    status: 'finalized',
    version: 2,
    finalized_by: { id: 1, name: 'Chủ sân Aura' },
    finalized_at: '2026-09-16 20:30:00',
    snapshot: {
      inputs: {
        actual_headcount: 8,
        court_duration_minutes: 120,
        court_hourly_rate: 150000,
        shuttlecock_count: 8,
        shuttlecock_unit_price: 25000,
      },
      breakdown: {
        court_subtotal: 300000,
        shuttlecock_subtotal: 200000,
        total_amount: 500000,
        divisor: 8,
        per_person_amount: 62500,
        is_approximate: false,
      },
      host: { id: 1, name: 'Chủ sân Aura' },
      finalized_at: '2026-09-16 20:30:00',
    },
  }

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    window.confirm = vi.fn<() => boolean>().mockReturnValue(true)

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url.includes('/social/matches/101/bill')) {
        return { bill: null }
      }
      if (url.includes('/social/matches/102/bill')) {
        return { bill: mockFinalizedBill }
      }
      if (url.includes('/social/matches/103/bill')) {
        return { bill: null }
      }
      if (url.includes('/social/matches/103/rates')) {
        return { rates: { court_hourly_rate: 140000, shuttlecock_unit_price: null } }
      }
      return {}
    })
  })

  it('renders active session state and allows host to end session', async () => {
    vi.mocked(api.post).mockResolvedValue({
      match: { ...mockMatchOpen, status: 'ended' },
    })

    const wrapper = mount(DropInSessionBillModal, {
      props: {
        show: true,
        match: mockMatchOpen,
        isHost: true,
      },
      global: { plugins: [i18n] },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Buổi chơi đang diễn ra hoặc chưa kết thúc')
    expect(wrapper.text()).toContain('Kết Thúc Buổi Chơi Ngay')

    const endBtn = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Kết Thúc Buổi Chơi Ngay'))
    expect(endBtn).toBeDefined()
    await endBtn?.trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/social/matches/101/end')
  })

  it('renders draft form for host after session ends and calculates realtime preview', async () => {
    const matchEndedNoBill: SocialMatch = {
      ...mockMatchEnded,
      id: 103,
    }

    vi.mocked(api.post).mockResolvedValue({
      bill: { ...mockFinalizedBill, status: 'draft' },
    })

    const wrapper = mount(DropInSessionBillModal, {
      props: {
        show: true,
        match: matchEndedNoBill,
        isHost: true,
      },
      global: { plugins: [i18n] },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Nhập Chi Phí Thực Tế Sau Buổi Chơi')
    expect(wrapper.text()).toContain('Chỉ áp dụng cho tiền sân và tiền cầu lông')

    // Find inputs
    const inputs = wrapper.findAll('input[type="number"]')
    expect(inputs.length).toBeGreaterThanOrEqual(5)

    // Check save draft button
    const saveDraftBtn = wrapper.findAll('button').find((b) => b.text().includes('Lưu Bản Nháp'))
    expect(saveDraftBtn).toBeDefined()
    await saveDraftBtn?.trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith(
      '/social/matches/103/bill/draft',
      expect.objectContaining({
        actual_headcount: expect.any(Number),
        court_duration_minutes: expect.any(Number),
        court_hourly_rate: expect.any(Number),
      }),
    )
  })

  it('renders finalized bill as read-only with court, shuttlecock, total and per-person breakdown', async () => {
    const wrapper = mount(DropInSessionBillModal, {
      props: {
        show: true,
        match: mockMatchEnded,
        isHost: false, // customer reading finalized bill
      },
      global: { plugins: [i18n] },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('ĐÃ CHỐT CHI PHÍ')
    expect(wrapper.text()).toContain('Tiền Sân Thực Tế')
    expect(wrapper.text()).toContain('Tiền Cầu Thực Tế')
    expect(wrapper.text()).toContain('Tổng Chi Phí Thực Tế (Sân + Cầu):')
    expect(wrapper.text()).toContain('Phần Mỗi Người Cần Đóng:')

    // Strictly exclude auxiliary purchase options and payment buttons (Task 4.4)
    expect(wrapper.text()).not.toContain('Thanh toán ví Aura')
    expect(wrapper.text()).not.toContain('Quét mã QR thanh toán')
    expect(wrapper.text()).not.toContain('Thêm đồ uống / dịch vụ phụ')
    expect(wrapper.text()).not.toContain('Bảo lãnh bù thiếu')
  })

  it('displays real API error without fallback to mock demo bill', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(
      new Error('403 Forbidden: Bạn không có quyền truy cập'),
    )

    const wrapper = mount(DropInSessionBillModal, {
      props: {
        show: true,
        match: mockMatchEnded,
        isHost: false,
      },
      global: { plugins: [i18n] },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Không thể tải bảng chi phí')
    expect(wrapper.text()).toContain('403 Forbidden: Bạn không có quyền truy cập')
    // Ensure no demo numbers leak
    expect(wrapper.text()).not.toContain('Demo Bill')
  })
  const mountEndedNoBill = async (rate: number | null) => {
    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url.includes('/social/matches/103/bill')) return { bill: null }
      if (url.includes('/social/matches/103/rates')) {
        return { rates: { court_hourly_rate: rate, shuttlecock_unit_price: null } }
      }
      return {}
    })
    const wrapper = mount(DropInSessionBillModal, {
      props: { show: true, match: { ...mockMatchEnded, id: 103 }, isHost: true },
      global: { plugins: [i18n] },
    })
    await flushPromises()
    return wrapper
  }
  const rateInput = (wrapper: Awaited<ReturnType<typeof mountEndedNoBill>>) =>
    wrapper.findAll('input[type="number"]')[2]!.element as HTMLInputElement

  it("fills the court rate from the court's price table when no bill is saved", async () => {
    const wrapper = await mountEndedNoBill(140000)

    expect(api.get).toHaveBeenCalledWith('/social/matches/103/rates')
    expect(rateInput(wrapper).value).toBe('140000')
  })

  it('leaves the court rate empty and refuses to finalize when the court has no price', async () => {
    const wrapper = await mountEndedNoBill(null)

    expect(rateInput(wrapper).value).toBe('')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(api.post).not.toHaveBeenCalledWith(
      '/social/matches/103/bill/finalize',
      expect.anything(),
    )
    expect(wrapper.text()).toContain('Nhập giá sân mỗi giờ trước khi chốt bảng chi phí.')
  })

  it('drops the fraction of the court amount like the server (1 min × 100.000 → 1.666)', async () => {
    const wrapper = await mountEndedNoBill(100000)
    const inputs = wrapper.findAll('input[type="number"]')
    await inputs[1]!.setValue(1)

    expect(wrapper.findAll('.preview-row')[0]!.text()).toMatch(/1\.666\s*₫/)
  })
})
