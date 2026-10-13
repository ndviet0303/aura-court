import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { i18n, setAppLocale } from '@/i18n'
import { buyerPhase, useResaleLock } from '../composables/useResaleLock'
import { marketplaceApi } from '../api/marketplaceApi'
import type { ResaleState } from '../types/marketplace'

vi.mock('../api/marketplaceApi', () => ({
  marketplaceApi: {
    getListingState: vi.fn(),
    lockListing: vi.fn(),
    reportPaymentSent: vi.fn(),
    releaseLock: vi.fn(),
    openPaymentDispute: vi.fn(),
  },
}))

const state = (overrides: Partial<ResaleState> = {}): ResaleState =>
  ({
    listing: { id: 7, asking_price: 150000 },
    role: 'buyer',
    status: 'checkout',
    lock: {
      locked_until: new Date(Date.now() + 15 * 60_000).toISOString(),
      payment_reported_at: null,
      reference: 'RSABCDEFGHIJ',
      held_by_me: true,
    },
    receiver: null,
    payment: null,
    transfer: null,
    last_release: null,
    ...overrides,
  }) as ResaleState

function mountLock(onChanged = vi.fn()) {
  let lock!: ReturnType<typeof useResaleLock>
  const wrapper = mount(
    defineComponent({
      setup() {
        lock = useResaleLock(onChanged)
        return () => null
      },
    }),
    { global: { plugins: [i18n] } },
  )
  return { lock, wrapper }
}

describe('buyerPhase', () => {
  it('never treats a payment report as a completed payment', () => {
    const reported = state({
      lock: { ...state().lock!, payment_reported_at: '2026-10-14T10:00:00Z' },
    })
    expect(buyerPhase(reported)).toBe('reported')
    expect(buyerPhase(state({ status: 'sold', lock: null }))).toBe('idle')
    expect(
      buyerPhase(state({ status: 'sold', lock: null, transfer: {} as ResaleState['transfer'] })),
    ).toBe('sold')
  })

  it('offers a support case only when money may have moved', () => {
    const release = (by: 'buyer' | 'seller' | 'expiry', paymentReported: boolean) =>
      buyerPhase(
        state({
          status: 'listed',
          lock: null,
          last_release: { by, at: '', payment_reported: paymentReported, reason: null },
        }),
      )
    expect(release('seller', false)).toBe('unresolved')
    expect(release('expiry', true)).toBe('unresolved')
    expect(release('expiry', false)).toBe('expired')
    expect(release('buyer', false)).toBe('idle')
  })
})

describe('useResaleLock', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.clearAllMocks()
    setAppLocale('vi')
  })
  afterEach(() => vi.useRealTimers())

  it('polls the listing every 5 s while holding and stops on close and unmount', async () => {
    vi.mocked(marketplaceApi.getListingState).mockResolvedValue(state())
    const { lock, wrapper } = mountLock()

    await lock.open(7)
    expect(lock.phase.value).toBe('holding')
    expect(marketplaceApi.getListingState).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(5000)
    expect(marketplaceApi.getListingState).toHaveBeenCalledTimes(2)

    lock.close()
    await flushPromises()
    await vi.advanceTimersByTimeAsync(20000)
    expect(marketplaceApi.getListingState).toHaveBeenCalledTimes(2)

    await lock.open(7)
    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(20000)
    expect(marketplaceApi.getListingState).toHaveBeenCalledTimes(3)
  })

  it('shows a conflict without any seller account when another buyer holds the lock', async () => {
    vi.mocked(marketplaceApi.getListingState).mockRejectedValue({ status: 404, message: '' })
    vi.mocked(marketplaceApi.lockListing).mockRejectedValue({ status: 409, message: 'Conflict' })
    const { lock } = mountLock()

    await lock.open(7)
    await lock.lock()

    expect(lock.state.value).toBeNull()
    expect(lock.error.value).toBe(i18n.global.t('resale.p2p.conflict'))
  })

  it('locks a free listing only with explicit acceptance', async () => {
    vi.mocked(marketplaceApi.getListingState).mockRejectedValue({ status: 404, message: '' })
    vi.mocked(marketplaceApi.lockListing).mockResolvedValue(
      state({ status: 'sold', lock: null, transfer: {} as ResaleState['transfer'] }),
    )
    const onChanged = vi.fn()
    const { lock } = mountLock(onChanged)

    await lock.open(7)
    await lock.lock(true)

    expect(marketplaceApi.lockListing).toHaveBeenCalledWith(7, true)
    expect(lock.phase.value).toBe('sold')
    expect(onChanged).toHaveBeenCalled()
  })
})
