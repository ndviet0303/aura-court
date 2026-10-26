import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick, type App } from 'vue'
import { i18n } from '@/i18n'
import OwnerVenueCreditsView from '../OwnerVenueCreditsView.vue'

const { financeApi } = vi.hoisted(() => ({ financeApi: { getOwnerVenueCredits: vi.fn() } }))
vi.mock('@/features/finance/api/financeApi', () => ({ financeApi }))

const entry = {
  id: 4,
  type: 'issue',
  amount: 150000,
  balance_delta: 150000,
  reserved_delta: 0,
  balance_after: 150000,
  reserved_after: 0,
  source_type: 'marketplace_listing',
  reference: 'RS7ABC',
  customer: { id: 12, name: 'Lan' },
  created_at: '2026-10-24T03:00:00Z',
}

let app: App | null = null

async function flush() {
  for (let i = 0; i < 5; i++) await Promise.resolve()
  await nextTick()
}

async function mountView() {
  const el = document.createElement('div')
  document.body.appendChild(el)
  app = createApp(OwnerVenueCreditsView)
  app.use(i18n).mount(el)
  await flush()
  return el
}

afterEach(() => {
  app?.unmount()
  app = null
  vi.clearAllMocks()
})

describe('OwnerVenueCreditsView', () => {
  it('shows the outstanding credit and the entries with their customer', async () => {
    financeApi.getOwnerVenueCredits.mockResolvedValue({
      data: [entry],
      meta: { current_page: 1, last_page: 2, total: 31 },
      outstanding: 420000,
    })
    const el = await mountView()

    expect(financeApi.getOwnerVenueCredits).toHaveBeenCalledWith(1)
    expect(
      el.querySelector('[data-testid="owner-venue-credits-outstanding"]')!.textContent,
    ).toContain('420.000')
    const row = el.querySelector('[data-testid="owner-venue-credits"] tbody tr')!.textContent!
    expect(row).toContain('Lan')
    expect(row).toContain('+150.000')
    expect(row).toContain('RS7ABC')

    const buttons = el.querySelectorAll<HTMLButtonElement>('nav button')
    const next = buttons[buttons.length - 1]!
    next.click()
    await flush()
    expect(financeApi.getOwnerVenueCredits).toHaveBeenLastCalledWith(2)
  })

  it('shows an empty state', async () => {
    financeApi.getOwnerVenueCredits.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, total: 0 },
      outstanding: 0,
    })
    const el = await mountView()

    expect(el.querySelector('[data-testid="owner-venue-credits-empty"]')).not.toBeNull()
  })
})
