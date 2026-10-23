import { beforeEach, describe, expect, it, vi } from 'vitest'

const inventoryApi = vi.hoisted(() => ({
  getProducts: vi.fn(),
  getMovements: vi.fn(),
  adjustStock: vi.fn(),
  createProduct: vi.fn(),
  createTransfer: vi.fn(),
}))
const adminApi = vi.hoisted(() => ({ getVenues: vi.fn() }))

vi.mock('vue', async (importOriginal) => ({
  ...(await importOriginal<typeof import('vue')>()),
  onMounted: vi.fn(),
}))
vi.mock('@/features/admin/api/inventoryApi', () => ({ inventoryApi }))
vi.mock('@/features/admin/api/adminApi', () => ({ adminApi }))
vi.mock('@/features/admin/composables/useLifecycleCommands', () => ({
  useOwnerCheck: () => () => true,
}))

import { useAdminInventory } from '../useAdminInventory'

const water = { id: 5, sku: 'NS', name: 'Nước suối', stock_quantity: 8, is_active: true }

describe('useAdminInventory venue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    adminApi.getVenues.mockResolvedValue([
      { id: 1, name: { vi: 'Cầu Giấy', en: 'Cau Giay' }, organization_id: 9 },
      { id: 2, name: 'Đống Đa', organization_id: 9 },
    ])
    inventoryApi.getProducts.mockResolvedValue([water])
    inventoryApi.getMovements.mockResolvedValue([])
    inventoryApi.adjustStock.mockResolvedValue({ ...water, stock_quantity: 12 })
    inventoryApi.createProduct.mockResolvedValue({ ...water, id: 6 })
    inventoryApi.createTransfer.mockResolvedValue({ transfer: { id: 1 } })
  })

  it('reads and changes the stock of the venue chosen', async () => {
    const page = useAdminInventory()
    await page.loadInventoryData()

    expect(page.venueOptions.value).toEqual([
      { value: 1, label: 'Cầu Giấy' },
      { value: 2, label: 'Đống Đa' },
    ])
    expect(inventoryApi.getProducts).toHaveBeenLastCalledWith({ venue_id: 1 })

    await page.selectVenue(2)
    expect(inventoryApi.getProducts).toHaveBeenLastCalledWith({ venue_id: 2 })
    expect(inventoryApi.getMovements).toHaveBeenLastCalledWith({ venue_id: 2 })

    page.openStockAction('in', page.products.value[0])
    await page.handleStockAction({ quantity: 4 })
    expect(inventoryApi.adjustStock).toHaveBeenCalledWith(
      expect.objectContaining({ productId: 5, venueId: 2, type: 'in', quantity: 4 }),
    )

    await page.handleSaveProduct({
      name: 'Pocari',
      sku: 'PC',
      category: 'drinks',
      price: 15000,
      cost: 9000,
      stock_quantity: 12,
      min_stock: 5,
      unit: 'chai',
    })
    expect(inventoryApi.createProduct).toHaveBeenCalledWith(
      expect.objectContaining({ stock_quantity: 12, venue_id: 2 }),
    )
  })

  it('transfers from the venue chosen and keeps field errors for the modal', async () => {
    const page = useAdminInventory()
    await page.loadInventoryData()
    expect(page.canTransfer.value).toBe(true)

    inventoryApi.createTransfer.mockRejectedValueOnce({
      message: 'The given data was invalid.',
      status: 409,
      errors: { quantity: ['Cơ sở chuyển chỉ còn 3 Nước suối bán được.'] },
    })
    await page.handleTransfer({ product_id: 5, to_venue_id: 2, quantity: 4 })
    expect(page.transferErrors.value.quantity).toEqual([
      'Cơ sở chuyển chỉ còn 3 Nước suối bán được.',
    ])

    await page.handleTransfer({ product_id: 5, to_venue_id: 2, quantity: 3 })
    expect(inventoryApi.createTransfer).toHaveBeenLastCalledWith({
      product_id: 5,
      to_venue_id: 2,
      quantity: 3,
      from_venue_id: 1,
    })
    expect(page.showTransferModal.value).toBe(false)
  })
})
