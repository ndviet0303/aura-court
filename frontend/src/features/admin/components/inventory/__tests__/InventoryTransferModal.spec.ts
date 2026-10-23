import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick, type App } from 'vue'
import { i18n } from '@/i18n'
import InventoryTransferModal from '../InventoryTransferModal.vue'
import type { InventoryProduct } from '../../../types/inventory'

const product = (id: number, stock: number, extra: Partial<InventoryProduct> = {}) =>
  ({
    id,
    sku: `SKU-${id}`,
    name: `Nước ${id}`,
    category: 'drinks',
    price: 10000,
    cost: 6000,
    stock_quantity: stock,
    min_stock: 5,
    unit: 'chai',
    is_active: true,
    ...extra,
  }) as InventoryProduct

let app: App | null = null

function mountModal() {
  const onSubmit = vi.fn()
  const el = document.createElement('div')
  document.body.appendChild(el)
  app = createApp(InventoryTransferModal, {
    show: true,
    // Only product 3 can move: 1 has no stock here, 2 is a service.
    products: [product(1, 0), product(2, 5, { is_service: true }), product(3, 6)],
    venueOptions: [
      { value: 10, label: 'Cầu Giấy' },
      { value: 20, label: 'Đống Đa' },
    ],
    fromVenueId: 10,
    onSubmit,
  })
  app.use(i18n).mount(el)
  return { el, onSubmit }
}

async function type(el: HTMLElement, selector: string, value: string) {
  const input = el.querySelector<HTMLInputElement>(selector)!
  input.value = value
  input.dispatchEvent(new Event('input'))
  await nextTick()
}

afterEach(() => {
  app?.unmount()
  app = null
})

describe('InventoryTransferModal', () => {
  it('stops a quantity above what the sending venue holds', async () => {
    const { el, onSubmit } = mountModal()

    await type(el, '#transfer-quantity', '7')
    el.querySelector('form')!.dispatchEvent(new Event('submit'))

    expect(el.querySelector('.field-error')?.textContent).toContain('6')
    expect(el.querySelector<HTMLButtonElement>('button[type="submit"]')!.disabled).toBe(true)
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('sends the product, receiving venue, quantity and note', async () => {
    const { el, onSubmit } = mountModal()

    await type(el, '#transfer-quantity', '6')
    await type(el, '#transfer-note', ' Bù hàng cuối tuần ')
    el.querySelector('form')!.dispatchEvent(new Event('submit'))

    expect(onSubmit).toHaveBeenCalledWith({
      product_id: 3,
      to_venue_id: 20,
      quantity: 6,
      note: 'Bù hàng cuối tuần',
    })
  })
})
