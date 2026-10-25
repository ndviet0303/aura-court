import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, defineComponent, nextTick, type App } from 'vue'
import { i18n } from '@/i18n'
import MarketplaceSellModal from '../MarketplaceSellModal.vue'
import { useResaleMarketplace } from '../../composables/useResaleMarketplace'
import type { UserBookingSlot } from '../../types/marketplace'

const { marketplaceApi } = vi.hoisted(() => ({
  marketplaceApi: {
    getListings: vi.fn(async () => ({ data: [] })),
    getUserReservations: vi.fn(async () => ({ data: [] })),
    listEntitlement: vi.fn(async () => ({ listing: {} })),
  },
}))
vi.mock('@/features/marketplace/api/marketplaceApi', () => ({ marketplaceApi }))

const slot: UserBookingSlot = {
  id: 41,
  reservationId: 9,
  courtName: 'Sân A1',
  venueName: 'Aura Cầu Giấy',
  dateStr: '26/10/2026',
  timeSlot: '18:00 - 19:00',
  maxResalePrice: 200000,
  startsAt: '2026-10-26T11:00:00Z',
  eligible: true,
}

let app: App | null = null

function mount(component: Parameters<typeof createApp>[0], props: Record<string, unknown> = {}) {
  const el = document.createElement('div')
  document.body.appendChild(el)
  app = createApp(component, props)
  app.use(i18n).mount(el)
  return el
}

afterEach(() => {
  app?.unmount()
  app = null
  vi.clearAllMocks()
})

describe('listing a slot for resale', () => {
  it('explains the venue credit and asks for no bank account', async () => {
    const onConfirm = vi.fn()
    const el = mount(MarketplaceSellModal, {
      show: true,
      bookingSlot: slot,
      askingPrice: 150000,
      onConfirm,
    })
    await nextTick()

    const credit = el.querySelector('[data-testid="resale-sell-credit"]')!.textContent!
    expect(credit).toContain('150.000')
    expect(credit).toContain('credit')
    expect(el.textContent).toContain('Aura Cầu Giấy')
    // The seller is paid in venue credit, so there is nothing to fill in about a bank account.
    expect(el.querySelectorAll('input')).toHaveLength(1)

    const submit = [...el.querySelectorAll('button')].find((b) =>
      b.textContent?.includes('Đăng Lên Chợ'),
    )!
    submit.click()
    expect(onConfirm).toHaveBeenCalledOnce()
  })

  it('posts only the asking price', async () => {
    let market!: ReturnType<typeof useResaleMarketplace>
    mount(
      defineComponent({
        setup() {
          market = useResaleMarketplace()
          return () => null
        },
      }),
    )
    await nextTick()

    market.openSellModal(slot)
    market.askingPriceInput.value = 120000
    await market.confirmSell()

    expect(marketplaceApi.listEntitlement).toHaveBeenCalledWith(41, { asking_price: 120000 })
  })
})
