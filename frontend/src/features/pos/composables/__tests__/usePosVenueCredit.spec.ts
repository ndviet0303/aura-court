import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, effectScope, nextTick, reactive, ref, type App } from 'vue'
import { i18n } from '@/i18n'
import { usePosVenueCredit } from '../usePosVenueCredit'
import PosCreditTender from '../../components/PosCreditTender.vue'
import type { CustomerProfile, PosPaymentMethod } from '../../types/pos'

const { posApi } = vi.hoisted(() => ({
  posApi: { getCustomerCredit: vi.fn(), getQuote: vi.fn() },
}))
vi.mock('@/features/pos/api/posApi', () => ({ posApi }))

const customer: CustomerProfile = {
  id: 12,
  name: 'Lan',
  phone: '0900000000',
  tier: 'Standard',
  discount_rate: 0,
  wallet_balance: 0,
  loyalty_points: 0,
}
const order = { items: [{ product_id: 1, quantity: 2 }], customer_id: 12 }

async function flush() {
  for (let i = 0; i < 5; i++) await Promise.resolve()
  await nextTick()
}

let scope = effectScope()
let app: App | null = null

async function setup(total: number, balance: number) {
  posApi.getCustomerCredit.mockResolvedValue({ user_id: 12, balance })
  const who = ref<CustomerProfile | null>(customer)
  const method = ref<PosPaymentMethod>('cash')
  const grandTotal = ref(total)
  const credit = scope.run(() => reactive(usePosVenueCredit(who, method, grandTotal)))!
  await flush()
  return { who, method, credit }
}

beforeEach(() => {
  scope = effectScope()
})

afterEach(() => {
  scope.stop()
  app?.unmount()
  app = null
  vi.clearAllMocks()
})

describe('POS venue credit tender', () => {
  it('pays the whole order with credit when the balance covers it', async () => {
    const { method, credit } = await setup(90000, 150000)
    expect(posApi.getCustomerCredit).toHaveBeenCalledWith(12)

    method.value = 'credit'
    await nextTick()

    expect(credit.part).toBe(90000)
    expect(await credit.payment(order, 0)).toEqual({ payment_method: 'credit' })
    expect(posApi.getQuote).not.toHaveBeenCalled()
  })

  it('mixes credit with cash for the rest, priced on the server total', async () => {
    const { method, credit } = await setup(90000, 50000)
    posApi.getQuote.mockResolvedValue({ grand_total: 92000 })

    method.value = 'credit'
    await nextTick()

    expect(await credit.payment(order, 0)).toEqual({
      payment_method: 'mixed',
      allow_mixed_tender: true,
      tenders: [
        { method: 'credit', amount: 50000 },
        { method: 'cash', amount: 42000 },
      ],
    })
    expect(posApi.getQuote).toHaveBeenCalledWith(order)
  })

  it('lets the cashier take less credit and collect the rest by QR', async () => {
    const { method, credit } = await setup(90000, 150000)
    posApi.getQuote.mockResolvedValue({ grand_total: 90000 })
    method.value = 'credit'
    await nextTick()

    const el = document.createElement('div')
    document.body.appendChild(el)
    app = createApp(PosCreditTender, { credit, total: 90000 })
    app.use(i18n).mount(el)
    expect(el.querySelector('[data-testid="pos-credit-balance"]')!.textContent).toContain('150.000')
    expect(el.querySelector('[data-testid="pos-credit-rest"]')).toBeNull()

    const input = el.querySelector<HTMLInputElement>('[data-testid="pos-credit-amount"]')!
    input.value = '60000'
    input.dispatchEvent(new Event('change'))
    await nextTick()
    el.querySelector<HTMLButtonElement>('[data-testid="pos-credit-rest-qr"]')!.click()
    await nextTick()

    expect(el.querySelector('[data-testid="pos-credit-rest"]')!.textContent).toContain('30.000')
    expect(await credit.payment(order, 0)).toEqual({
      payment_method: 'mixed',
      allow_mixed_tender: true,
      tenders: [
        { method: 'credit', amount: 60000 },
        { method: 'qr', amount: 30000 },
      ],
    })
  })

  it('drops the credit tender when the customer changes and keeps other tenders as before', async () => {
    const { who, method, credit } = await setup(90000, 150000)
    method.value = 'credit'
    await nextTick()

    who.value = null
    await flush()

    expect(method.value).toBe('cash')
    expect(credit.balance).toBe(0)
    expect(await credit.payment(order, 100000)).toEqual({
      payment_method: 'cash',
      cash_tendered: 100000,
    })
  })
})
