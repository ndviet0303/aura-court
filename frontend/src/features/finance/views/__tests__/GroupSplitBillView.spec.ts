import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, type App } from 'vue'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { financeApi } from '../../api/financeApi'
import GroupSplitBillView from '../GroupSplitBillView.vue'
import { splitEvenly, useGroupSplitBill } from '../../composables/useGroupSplitBill'
import type { SplitGroup, SplitReservation } from '../../types/split'
import { i18n, setAppLocale } from '@/i18n'

vi.mock('../../api/financeApi', () => ({
  financeApi: { getReservationSplit: vi.fn(), createReservationSplit: vi.fn() },
}))

const heldReservation: SplitReservation = {
  id: 204,
  code: 'RSV-204',
  status: 'held',
  total_amount: 175000,
  hold_expires_at: '2026-09-27T12:00:00Z',
}

const attempt = {
  id: 9,
  provider: 'sepay',
  reference: 'AC123',
  transfer_content: 'AC123',
  amount: 90000,
  currency: 'VND',
  status: 'pending',
  expires_at: '2026-09-27T12:00:00Z',
  paid_at: null,
  receiver: { bank_code: 'MBBank', account_name: 'Owner', account_number: '0987654321' },
  qr_url: 'https://img.vietqr.io/image/test.png',
}

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
      payment_reference: 'AC100',
      payment_attempt: { ...attempt, status: 'paid', amount: 85000 },
    },
    {
      id: 502,
      share_number: 2,
      amount: 90000,
      paid_amount: 0,
      status: 'pending',
      paid_at: null,
      payment_reference: 'AC123',
      payment_attempt: attempt,
    },
  ],
}

const flushPromises = () => new Promise((resolve) => setTimeout(resolve))
let app: App | null = null
let router: Router

async function renderSplit(path: string) {
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/split-bill/:code', component: GroupSplitBillView },
      { path: '/booking', component: { template: '<div>Bookings</div>' } },
    ],
  })
  await router.push(path)
  await router.isReady()
  const el = document.createElement('div')
  document.body.appendChild(el)
  app = createApp(GroupSplitBillView)
  app.use(i18n).use(router).mount(el)
  await flushPromises()
  return el
}

const find = (el: Element, id: string) => el.querySelector<HTMLElement>(`[data-testid="${id}"]`)
const findAll = (el: Element, id: string) => [...el.querySelectorAll(`[data-testid="${id}"]`)]

describe('GroupSplitBillView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setAppLocale('vi')
  })

  afterEach(() => {
    app?.unmount()
    app = null
    document.body.innerHTML = ''
  })

  it('opens the existing group of a booking code with transfer details for unpaid shares only', async () => {
    vi.mocked(financeApi.getReservationSplit).mockResolvedValue({
      reservation: heldReservation,
      split: persistedGroup,
    })
    const el = await renderSplit('/split-bill/RSV-204')

    expect(financeApi.getReservationSplit).toHaveBeenCalledWith('RSV-204')
    expect(el.textContent).toContain('RSV-204')
    expect(el.textContent).toContain('175.000')
    const shares = findAll(el, 'split-share')
    expect(shares).toHaveLength(2)
    expect(shares[0]!.textContent).toContain('Đã nhận tiền')
    expect(find(shares[0]!, 'split-share-qr')).toBeNull()
    expect(find(shares[1]!, 'split-share-reference')!.textContent!.trim()).toBe('AC123')
    expect(find(shares[1]!, 'split-share-qr')!.getAttribute('src')).toBe(attempt.qr_url)
    expect(find(el, 'split-create')).toBeNull()
  })

  it('creates an even split for a held booking without a group and shows the server group', async () => {
    vi.mocked(financeApi.getReservationSplit).mockResolvedValue({
      reservation: heldReservation,
      split: null,
    })
    vi.mocked(financeApi.createReservationSplit).mockResolvedValue({ split: persistedGroup })
    const el = await renderSplit('/split-bill/RSV-204')

    const people = find(el, 'split-people') as HTMLSelectElement
    people.value = '3'
    people.dispatchEvent(new Event('change'))
    await flushPromises()
    expect(findAll(el, 'split-preview-share')).toHaveLength(3)
    find(el, 'split-create')!.dispatchEvent(new Event('submit'))
    await flushPromises()

    expect(financeApi.createReservationSplit).toHaveBeenCalledWith(
      'RSV-204',
      splitEvenly(175000, 3),
    )
    expect(find(el, 'split-group')).not.toBeNull()
    expect(find(el, 'split-create')).toBeNull()
  })

  it('shows the group created elsewhere when creating conflicts, and the error otherwise', async () => {
    vi.mocked(financeApi.getReservationSplit)
      .mockResolvedValueOnce({ reservation: heldReservation, split: null })
      .mockResolvedValueOnce({ reservation: heldReservation, split: persistedGroup })
      .mockResolvedValueOnce({ reservation: heldReservation, split: null })
    vi.mocked(financeApi.createReservationSplit).mockRejectedValue(new Error('Conflict'))

    const split = useGroupSplitBill()
    await split.loadSplit('RSV-204')
    await split.createSplit(2)
    expect(split.group.value?.id).toBe(71)
    expect(split.error.value).toBe('')

    split.group.value = null
    await split.createSplit(2)
    expect(split.group.value).toBeNull()
    expect(split.error.value).toBe('Conflict')
  })

  it('does not offer a split for a booking that is no longer awaiting payment', async () => {
    vi.mocked(financeApi.getReservationSplit).mockResolvedValue({
      reservation: { ...heldReservation, status: 'expired' },
      split: null,
    })
    const el = await renderSplit('/split-bill/RSV-204')

    expect(find(el, 'split-create')).toBeNull()
    expect(find(el, 'split-not-splittable')!.textContent).toContain('chờ thanh toán')
  })

  it('keeps the last server snapshot marked stale when refresh fails and reloads the actual new state', async () => {
    vi.mocked(financeApi.getReservationSplit)
      .mockResolvedValueOnce({ reservation: heldReservation, split: persistedGroup })
      .mockRejectedValueOnce(new Error('Service unavailable'))
      .mockResolvedValueOnce({
        reservation: { ...heldReservation, status: 'expired' },
        split: {
          ...persistedGroup,
          status: 'expired',
          shares: [persistedGroup.shares[0]!, { ...persistedGroup.shares[1]!, status: 'expired' }],
        },
      })
    const el = await renderSplit('/split-bill/RSV-204')

    find(el, 'split-refresh')!.click()
    await flushPromises()
    expect(find(el, 'split-stale')!.textContent).toContain('có thể đã thay đổi')
    expect(find(el, 'split-group')).not.toBeNull()

    find(el, 'split-refresh')!.click()
    await flushPromises()
    expect(find(el, 'split-stale')).toBeNull()
    expect(find(el, 'split-server-state')!.textContent!.trim()).toBe('Đã hết hạn')
    expect(findAll(el, 'split-share')[1]!.textContent).toContain('đã hết hạn')
    expect(find(el, 'split-share-qr')).toBeNull()
  })

  it('shows a truthful error and no group when the API fails or returns an invalid group', async () => {
    vi.mocked(financeApi.getReservationSplit)
      .mockRejectedValueOnce(new Error('Not found'))
      .mockResolvedValueOnce({
        reservation: { ...heldReservation, code: 'RSV-205' },
        split: { ...persistedGroup, shares: [] },
      })
    const el = await renderSplit('/split-bill/RSV-204')

    expect(find(el, 'split-error')!.textContent).toContain('Not found')
    expect(find(el, 'split-group')).toBeNull()
    await router.push('/split-bill/RSV-205')
    await flushPromises()
    expect(find(el, 'split-error')!.textContent).toContain('không trả về nhóm')
    expect(find(el, 'split-group')).toBeNull()
  })

  it('does not show a previous booking group after the route changes during a request', async () => {
    vi.mocked(financeApi.getReservationSplit)
      .mockImplementationOnce(() =>
        Promise.resolve().then(() => ({ reservation: heldReservation, split: persistedGroup })),
      )
      .mockResolvedValueOnce({ reservation: { ...heldReservation, code: 'RSV-205' }, split: null })

    const split = useGroupSplitBill()
    await Promise.all([split.loadSplit('RSV-204'), split.loadSplit('RSV-205')])

    expect(split.reservation.value?.code).toBe('RSV-205')
    expect(split.group.value).toBeNull()
    expect(split.error.value).toBe('')
  })
})

describe('splitEvenly', () => {
  it('splits whole VND exactly, the first share taking the remainder', () => {
    expect(splitEvenly(175000, 3)).toEqual([58334, 58333, 58333])
    expect(splitEvenly(200000, 8)).toEqual(Array(8).fill(25000))
    for (const count of [2, 3, 7]) {
      expect(splitEvenly(100001, count).reduce((a, b) => a + b, 0)).toBe(100001)
    }
  })
})
