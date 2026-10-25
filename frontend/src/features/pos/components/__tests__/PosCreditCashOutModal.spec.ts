import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick, type App } from 'vue'
import { i18n } from '@/i18n'
import PosCreditCashOutModal from '../PosCreditCashOutModal.vue'

const { posApi } = vi.hoisted(() => ({
  posApi: { lookupCashOut: vi.fn(), confirmCashOut: vi.fn() },
}))
vi.mock('@/features/pos/api/posApi', () => ({ posApi }))

let app: App | null = null

async function flush() {
  for (let i = 0; i < 5; i++) await Promise.resolve()
  await nextTick()
}

function mountModal() {
  const onDone = vi.fn()
  const el = document.createElement('div')
  document.body.appendChild(el)
  app = createApp(PosCreditCashOutModal, { shiftId: 3, onDone })
  app.use(i18n).mount(el)
  return { el, onDone }
}

async function enterCode(el: HTMLElement, code: string) {
  const input = el.querySelector<HTMLInputElement>('[data-testid="pos-cash-out-code"]')!
  input.value = code
  input.dispatchEvent(new Event('input'))
  await nextTick()
  el.querySelector('form')!.dispatchEvent(new Event('submit'))
  await flush()
}

afterEach(() => {
  app?.unmount()
  app = null
  vi.clearAllMocks()
})

describe('PosCreditCashOutModal', () => {
  it('looks the code up, shows who and how much, then confirms once with one idempotency key', async () => {
    posApi.lookupCashOut.mockResolvedValue({
      customer_name: 'Lan',
      amount: 100000,
      expires_at: '2026-10-25T10:10:00Z',
    })
    posApi.confirmCashOut.mockResolvedValue({
      customer_name: 'Lan',
      amount: 100000,
      balance: 50000,
    })
    const { el, onDone } = mountModal()

    await enterCode(el, ' k7p2qx9m ')

    expect(posApi.lookupCashOut).toHaveBeenCalledWith(3, 'K7P2QX9M')
    const found = el.querySelector('[data-testid="pos-cash-out-found"]')!.textContent!
    expect(found).toContain('Lan')
    expect(found).toContain('100.000')

    el.querySelector<HTMLButtonElement>('[data-testid="pos-cash-out-confirm"]')!.click()
    await flush()

    const [shift, code, key] = posApi.confirmCashOut.mock.calls[0]!
    expect([shift, code]).toEqual([3, 'K7P2QX9M'])
    expect(key).toMatch(/^pos-cash-out-3-K7P2QX9M-/)
    expect(el.querySelector('[data-testid="pos-cash-out-done"]')!.textContent).toContain('50.000')
    expect(el.querySelector('[data-testid="pos-cash-out-confirm"]')).toBeNull()
    expect(onDone).toHaveBeenCalledOnce()
  })

  it('shows why an invalid or expired code is refused', async () => {
    posApi.lookupCashOut.mockRejectedValue({
      status: 422,
      message: 'Mã đổi tiền không hợp lệ hoặc đã hết hạn.',
    })
    const { el } = mountModal()

    await enterCode(el, 'BADCODE1')

    expect(el.querySelector('[role="alert"]')!.textContent).toContain('không hợp lệ')
    expect(el.querySelector('[data-testid="pos-cash-out-confirm"]')).toBeNull()
  })
})
