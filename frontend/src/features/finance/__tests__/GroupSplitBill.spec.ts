import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { financeApi } from '../api/financeApi'
import GroupSplitBillView from '../views/GroupSplitBillView.vue'
import { useGroupSplitBill } from '../composables/useGroupSplitBill'
import type { SplitGroup } from '../types/split'
import { i18n, setAppLocale } from '@/i18n'

vi.mock('../api/financeApi', () => ({
  financeApi: { getSplitGroup: vi.fn() },
}))

const persistedGroup: SplitGroup = {
  id: 71,
  reservation_id: 204,
  expected_amount: 175000,
  paid_amount: 85000,
  status: 'pending',
  expires_at: '2026-09-27T12:00:00Z',
  reservation: { id: 204, code: 'RSV-204', total_amount: 175000, status: 'held' },
  shares: [
    {
      id: 501,
      share_number: 1,
      amount: 85000,
      paid_amount: 85000,
      status: 'paid',
      paid_at: '2026-09-26T10:00:00Z',
      payment_reference: 'CLIENT-REF',
    },
    {
      id: 502,
      share_number: 2,
      amount: 90000,
      paid_amount: 0,
      status: 'pending',
      paid_at: null,
      payment_reference: null,
    },
  ],
}

async function renderSplit(path: string) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/split-bill/:code?', component: GroupSplitBillView },
      { path: '/booking', component: { template: '<div>Bookings</div>' } },
    ],
  })
  await router.push(path)
  await router.isReady()
  const wrapper = mount(GroupSplitBillView, { global: { plugins: [i18n, router] } })
  await flushPromises()
  return wrapper
}

describe('GroupSplitBillView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setAppLocale('vi')
  })

  it('shows a real group and shares but never treats client-reference paid rows as verified receipts', async () => {
    vi.mocked(financeApi.getSplitGroup).mockResolvedValue({ split: persistedGroup })
    const wrapper = await renderSplit('/split-bill/71')

    expect(financeApi.getSplitGroup).toHaveBeenCalledWith(71)
    expect(wrapper.get('[data-testid="split-group-id"]').text()).toBe('71')
    expect(wrapper.text()).toContain('RSV-204')
    expect(wrapper.text()).toContain('175.000')
    expect(wrapper.findAll('[data-testid="split-share"]')).toHaveLength(2)
    expect(wrapper.get('[data-testid="split-evidence-warning"]').text()).toContain(
      'Chưa có biên nhận',
    )
    expect(wrapper.get('[data-testid="split-share"]').text()).toContain(
      'Đang chờ biên nhận xác minh',
    )
    expect(wrapper.text()).not.toContain('Đã thanh toán')
    expect(wrapper.find('img[src*="vietqr"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('keeps the last server snapshot marked stale when refresh fails and reloads the actual new state', async () => {
    vi.mocked(financeApi.getSplitGroup)
      .mockResolvedValueOnce({ split: persistedGroup })
      .mockRejectedValueOnce(new Error('Service unavailable'))
      .mockResolvedValueOnce({
        split: {
          ...persistedGroup,
          status: 'expired',
          shares: [{ ...persistedGroup.shares[0]!, status: 'expired' }, persistedGroup.shares[1]!],
        },
      })
    const wrapper = await renderSplit('/split-bill/71')

    await wrapper.get('[data-testid="split-refresh"]').trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-testid="split-stale"]').text()).toContain('có thể đã thay đổi')
    expect(wrapper.get('[data-testid="split-group-id"]').text()).toBe('71')
    expect(wrapper.text()).not.toContain('Đã thanh toán')

    await wrapper.get('[data-testid="split-refresh"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-testid="split-stale"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="split-server-state"]').text()).toBe('expired')
    expect(wrapper.get('[data-testid="split-share"]').text()).toContain('đã hết hạn')
    wrapper.unmount()
  })

  it('shows a truthful error and no sample members when the scoped API fails or returns no group', async () => {
    vi.mocked(financeApi.getSplitGroup)
      .mockRejectedValueOnce(new Error('Not found'))
      .mockResolvedValueOnce({ split: undefined } as unknown as { split: SplitGroup })
    const wrapper = await renderSplit('/split-bill/71')

    expect(wrapper.get('[data-testid="split-error"]').text()).toContain('Not found')
    expect(wrapper.findAll('[data-testid="split-share"]')).toHaveLength(0)
    expect(wrapper.find('[data-testid="split-group"]').exists()).toBe(false)
    await wrapper.vm.$router?.push('/split-bill/72')
    await flushPromises()
    expect(wrapper.get('[data-testid="split-error"]').text()).toContain('không trả về nhóm')
    expect(wrapper.find('[data-testid="split-group"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('does not reinterpret a booking code as a group ID or invent a payment QR', async () => {
    const wrapper = await renderSplit('/split-bill/RSV-204')
    expect(financeApi.getSplitGroup).not.toHaveBeenCalled()
    expect(wrapper.get('[data-testid="split-error"]').text()).toContain('mã đặt sân')
    expect(wrapper.find('[data-testid="split-group"]').exists()).toBe(false)
    expect(wrapper.find('img').exists()).toBe(false)
    wrapper.unmount()
  })

  it('does not show a previous group after the route changes during a request', async () => {
    vi.mocked(financeApi.getSplitGroup)
      .mockImplementationOnce(() => Promise.resolve().then(() => ({ split: persistedGroup })))
      .mockResolvedValueOnce({ split: { ...persistedGroup, id: 72 } })

    const split = useGroupSplitBill()
    const previous = split.loadSplitGroup('71')
    const current = split.loadSplitGroup('72')
    await Promise.all([previous, current])

    expect(split.group.value?.id).toBe(72)
    expect(split.error.value).toBe('')
  })
})
