import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick, type App } from 'vue'
import { i18n } from '@/i18n'
import ProfileVenueCreditTab from '../ProfileVenueCreditTab.vue'
import type { VenueCreditAccount } from '@/core/types/venueCredit'

const { profileApi } = vi.hoisted(() => ({
  profileApi: { getVenueCredits: vi.fn(), createCashOutCode: vi.fn() },
}))
vi.mock('@/features/profile/api/profileApi', () => ({ profileApi }))
// The QR is drawn locally; jsdom has no canvas, so the drawing is stubbed.
vi.mock('qrcode', () => ({ default: { toDataURL: async (value: string) => `data:qr,${value}` } }))

const entry = (id: number, type: string, delta: number, after: number) => ({
  id,
  type,
  amount: Math.abs(delta),
  balance_delta: delta,
  reserved_delta: 0,
  balance_after: after,
  reserved_after: 0,
  source_type: 'marketplace_listing',
  reference: `REF${id}`,
  created_at: '2026-10-24T03:00:00Z',
})

const account: VenueCreditAccount = {
  organization: { id: 5, name: 'Aura Cầu Giấy' },
  balance: 150000,
  reserved: 0,
  entries: [entry(2, 'spend', -50000, 150000), entry(1, 'issue', 200000, 200000)],
} as VenueCreditAccount

let app: App | null = null

async function flush() {
  for (let i = 0; i < 5; i++) await Promise.resolve()
  await nextTick()
}

async function mountTab() {
  const el = document.createElement('div')
  document.body.appendChild(el)
  app = createApp(ProfileVenueCreditTab)
  app.use(i18n).mount(el)
  await flush()
  return el
}

beforeEach(() => {
  vi.useFakeTimers({ now: new Date('2026-10-25T10:00:00Z') })
  profileApi.getVenueCredits.mockResolvedValue({ data: [account] })
})

afterEach(() => {
  app?.unmount()
  app = null
  vi.useRealTimers()
  vi.clearAllMocks()
})

describe('ProfileVenueCreditTab', () => {
  it('shows the balance per organization with its history', async () => {
    const el = await mountTab()

    const card = el.querySelector('[data-testid="venue-credit-account"]')!
    expect(card.textContent).toContain('Aura Cầu Giấy')
    expect(card.querySelector('[data-testid="venue-credit-balance"]')!.textContent).toContain(
      '150.000',
    )
    const rows = [...card.querySelectorAll('li')].map((li) => li.textContent)
    expect(rows[0]).toContain('−50.000')
    expect(rows[0]).toContain(String(i18n.global.t('profile.credit.types.spend')))
    expect(rows[1]).toContain('+200.000')
    expect(rows[1]).toContain('REF1')
  })

  it('shows an empty state without credit', async () => {
    profileApi.getVenueCredits.mockResolvedValue({ data: [] })
    const el = await mountTab()

    expect(el.querySelector('[data-testid="venue-credit-empty"]')).not.toBeNull()
  })

  it('creates a cash-out code with its QR and a 10-minute countdown', async () => {
    profileApi.createCashOutCode.mockResolvedValue({
      code: 'K7P2QX9M',
      amount: 100000,
      expires_at: '2026-10-25T10:10:00Z',
    })
    const el = await mountTab()

    el.querySelector<HTMLButtonElement>('[data-testid="venue-credit-cash-out-btn"]')!.click()
    await nextTick()
    const input = el.querySelector<HTMLInputElement>('[data-testid="venue-credit-amount"]')!
    expect(input.value).toBe('150000')
    input.value = '100000'
    input.dispatchEvent(new Event('input'))
    await nextTick()
    el.querySelector('form')!.dispatchEvent(new Event('submit'))
    await flush()

    expect(profileApi.createCashOutCode).toHaveBeenCalledWith(5, 100000)
    expect(el.querySelector('[data-testid="venue-credit-code"]')!.textContent).toContain('K7P2QX9M')
    expect(el.querySelector<HTMLImageElement>('.code-box img')!.src).toBe('data:qr,K7P2QX9M')
    expect(el.querySelector('[data-testid="venue-credit-countdown"]')!.textContent).toContain(
      '10:00',
    )

    vi.advanceTimersByTime(600_000)
    await nextTick()
    expect(el.querySelector('[data-testid="venue-credit-code"]')).toBeNull()
    expect(el.textContent).toContain(String(i18n.global.t('profile.credit.codeExpired')))
  })

  it('refuses an amount above the balance', async () => {
    const el = await mountTab()
    el.querySelector<HTMLButtonElement>('[data-testid="venue-credit-cash-out-btn"]')!.click()
    await nextTick()
    const input = el.querySelector<HTMLInputElement>('[data-testid="venue-credit-amount"]')!
    input.value = '150001'
    input.dispatchEvent(new Event('input'))
    await nextTick()

    const create = el.querySelector<HTMLButtonElement>('[data-testid="venue-credit-create-code"]')!
    expect(create.disabled).toBe(true)
  })
})
