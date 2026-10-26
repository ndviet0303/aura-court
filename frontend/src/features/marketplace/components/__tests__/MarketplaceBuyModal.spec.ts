import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick, type App } from 'vue'
import { i18n } from '@/i18n'
import MarketplaceBuyModal from '../MarketplaceBuyModal.vue'
import type { ResaleListing, ResaleState } from '../../types/marketplace'

const { marketplaceApi } = vi.hoisted(() => ({
  marketplaceApi: { getListingState: vi.fn(), lockListing: vi.fn(), releaseLock: vi.fn() },
}))
vi.mock('@/features/marketplace/api/marketplaceApi', () => ({ marketplaceApi }))

const NOW = new Date('2026-10-25T10:00:00Z')

const listing: ResaleListing = {
  id: 7,
  courtName: 'Sân A1',
  venueName: 'Aura Cầu Giấy',
  dateStr: '26/10/2026',
  timeSlot: '18:00 - 19:00',
  timePeriod: 'evening',
  originalPrice: 200000,
  resalePrice: 150000,
  sellerName: 'Minh',
  status: 'listed',
  heldByMe: false,
}

const state = (extra: Partial<ResaleState> = {}): ResaleState =>
  ({
    listing: { id: 7 },
    role: 'buyer',
    status: 'listed',
    lock: null,
    payment: null,
    transfer: null,
    credit_issued: null,
    last_release: null,
    ...extra,
  }) as ResaleState

const holding = state({
  status: 'checkout',
  lock: { locked_until: '2026-10-25T10:10:00Z', reference: 'RS7ABC', held_by_me: true },
  payment: {
    id: 1,
    provider: 'sepay',
    reference: 'RS7ABC',
    transfer_content: 'RS7ABC',
    amount: 150000,
    currency: 'VND',
    status: 'pending',
    expires_at: '2026-10-25T10:10:00Z',
    paid_at: null,
    receiver: { bank_code: 'VCB', account_name: 'CHU SAN AURA', account_number: '0123456789' },
    qr_url: 'https://qr.example/owner.png',
  },
})

let app: App | null = null

async function flush() {
  for (let i = 0; i < 5; i++) await Promise.resolve()
  await nextTick()
}

async function mountModal() {
  const onChanged = vi.fn()
  const el = document.createElement('div')
  document.body.appendChild(el)
  app = createApp(MarketplaceBuyModal, { listing, onChanged })
  app.use(i18n).mount(el)
  await flush()
  return { el, onChanged }
}

beforeEach(() => {
  vi.useFakeTimers({ now: NOW })
  marketplaceApi.getListingState.mockResolvedValue(state())
  marketplaceApi.lockListing.mockResolvedValue(holding)
})

afterEach(() => {
  app?.unmount()
  app = null
  vi.useRealTimers()
  vi.clearAllMocks()
})

describe('MarketplaceBuyModal (owner-collected resale)', () => {
  it('holds the slot and shows the venue owner QR with a countdown, and no "I paid" button', async () => {
    const { el } = await mountModal()

    el.querySelector<HTMLButtonElement>('[data-testid="resale-lock-btn"]')!.click()
    await flush()

    expect(marketplaceApi.lockListing).toHaveBeenCalledWith(7, false)
    expect(el.querySelector('[data-testid="resale-countdown"]')!.textContent).toContain('10:00')
    expect(el.querySelector<HTMLImageElement>('[data-testid="owner-payment-qr"]')!.src).toBe(
      'https://qr.example/owner.png',
    )
    expect(el.textContent).toContain('CHU SAN AURA')
    expect(el.querySelector('[data-testid="resale-cancel-hold-btn"]')).not.toBeNull()
    expect(el.querySelector('[data-testid="resale-lock-btn"]')).toBeNull()
    expect(el.querySelector('[data-testid="resale-sold"]')).toBeNull()
  })

  it('polls while holding and turns sold once the bank receipt lands', async () => {
    const { el, onChanged } = await mountModal()
    el.querySelector<HTMLButtonElement>('[data-testid="resale-lock-btn"]')!.click()
    await flush()
    marketplaceApi.getListingState.mockResolvedValue(
      state({ status: 'sold', transfer: { id: 3 } as ResaleState['transfer'] }),
    )

    vi.advanceTimersByTime(5000)
    await flush()

    expect(marketplaceApi.getListingState).toHaveBeenCalledTimes(2)
    expect(el.querySelector('[data-testid="resale-sold"]')).not.toBeNull()
    expect(el.querySelector('[data-testid="resale-countdown"]')).toBeNull()
    expect(el.querySelector('[data-testid="owner-payment-qr"]')).toBeNull()
    expect(onChanged).toHaveBeenCalled()
  })

  it('offers the slot again once an unpaid hold runs out', async () => {
    const { el } = await mountModal()
    el.querySelector<HTMLButtonElement>('[data-testid="resale-lock-btn"]')!.click()
    await flush()
    marketplaceApi.getListingState.mockResolvedValue(
      state({ last_release: { by: 'expiry', at: '2026-10-25T10:10:00Z', reason: null } }),
    )

    vi.advanceTimersByTime(600_000)
    await flush()

    expect(el.textContent).toContain(String(i18n.global.t('resale.p2p.expired')))
    expect(el.querySelector('[data-testid="resale-lock-btn"]')).not.toBeNull()
    expect(el.querySelector('[data-testid="owner-payment-qr"]')).toBeNull()
  })
})
