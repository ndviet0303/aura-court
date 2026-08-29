import { ref, computed } from 'vue'
import type { InventoryProduct, StockMovement, InventoryAudit, Supplier } from '../types/inventory'
import {
  INITIAL_INVENTORY_PRODUCTS,
  INITIAL_STOCK_MOVEMENTS,
  INITIAL_INVENTORY_AUDITS,
  INITIAL_SUPPLIERS,
} from '../types/inventory'
import { inventoryApi } from '../api/inventoryApi'
import { errorMessage } from '@/core/utils/errorMessage'

export function useAdminInventory() {
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const errorMsg = ref('')

  const activeTab = ref<'catalog' | 'movements'>('catalog')
  const searchQuery = ref('')
  const categoryFilter = ref<string>('all')
  const stockFilter = ref<'all' | 'low' | 'out'>('all')
  const movementTypeFilter = ref<'all' | 'in' | 'out' | 'adjust' | 'return'>('all')

  const products = ref<InventoryProduct[]>([...INITIAL_INVENTORY_PRODUCTS])
  const movements = ref<StockMovement[]>([...INITIAL_STOCK_MOVEMENTS])
  const audits = ref<InventoryAudit[]>([...INITIAL_INVENTORY_AUDITS])

  const showProductModal = ref(false)
  const editingProduct = ref<InventoryProduct | null>(null)
  const showStockActionModal = ref(false)
  const stockActionType = ref<'in' | 'out' | 'adjust'>('in')
  const selectedProductForAction = ref<InventoryProduct | null>(null)

  const categories = computed(() => {
    const cats = new Set(products.value.map((p) => p.category))
    return ['all', ...Array.from(cats)]
  })

  const filteredProducts = computed(() => {
    return products.value.filter((p) => {
      if (!p.is_active) return false
      if (categoryFilter.value !== 'all' && p.category !== categoryFilter.value) return false
      if (stockFilter.value === 'low' && (p.stock_quantity > p.min_stock || p.stock_quantity === 0)) return false
      if (stockFilter.value === 'out' && p.stock_quantity > 0) return false

      if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase()
        const matchName = p.name.toLowerCase().includes(q)
        const matchSku = p.sku.toLowerCase().includes(q)
        return matchName || matchSku
      }
      return true
    })
  })

  const filteredMovements = computed(() => {
    return movements.value.filter((m) => {
      if (movementTypeFilter.value !== 'all' && m.type !== movementTypeFilter.value) return false
      return true
    })
  })

  const kpis = computed(() => {
    const totalItems = products.value.reduce((sum, p) => sum + p.stock_quantity, 0)
    const totalValue = products.value.reduce((sum, p) => sum + p.stock_quantity * p.cost, 0)
    const lowStockCount = products.value.filter((p) => p.stock_quantity <= p.min_stock && p.stock_quantity > 0).length
    const outOfStockCount = products.value.filter((p) => p.stock_quantity === 0).length
    return { totalItems, totalValue, lowStockCount, outOfStockCount }
  })

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  function openStockAction(type: 'in' | 'out' | 'adjust', product?: InventoryProduct) {
    stockActionType.value = type
    selectedProductForAction.value = product || products.value[0] || null
    showStockActionModal.value = true
  }

  function handleSaveProduct(payload: any) {
    if (editingProduct.value) {
      Object.assign(editingProduct.value, payload)
      showNotice(`Đã cập nhật sản phẩm "${payload.name}"`)
    } else {
      products.value.unshift({
        id: Date.now(),
        ...payload,
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      })
      showNotice(`Đã thêm sản phẩm mới "${payload.name}"`)
    }
    showProductModal.value = false
  }

  function handleStockAction(quantity: number, note: string) {
    if (!selectedProductForAction.value) return
    const p = selectedProductForAction.value
    if (stockActionType.value === 'in') {
      p.stock_quantity += quantity
    } else if (stockActionType.value === 'out') {
      p.stock_quantity = Math.max(0, p.stock_quantity - quantity)
    } else {
      p.stock_quantity = quantity
    }

    movements.value.unshift({
      id: Date.now(),
      product_id: p.id,
      product_name: p.name,
      product_sku: p.sku,
      type: stockActionType.value,
      quantity,
      unit: p.unit,
      reference_code: `REF-${Date.now().toString().slice(-4)}`,
      note,
      created_at: new Date().toISOString(),
      created_by: 'Admin',
      balance_after: p.stock_quantity,
    })

    showStockActionModal.value = false
    showNotice(`Đã thực hiện ${stockActionType.value === 'in' ? 'nhập kho' : stockActionType.value === 'out' ? 'xuất kho' : 'điều chỉnh'} thành công!`)
  }

  return {
    loading,
    saving,
    notice,
    errorMsg,
    activeTab,
    searchQuery,
    categoryFilter,
    stockFilter,
    movementTypeFilter,
    products,
    movements,
    categories,
    filteredProducts,
    filteredMovements,
    kpis,
    showProductModal,
    editingProduct,
    showStockActionModal,
    stockActionType,
    selectedProductForAction,
    showNotice,
    openStockAction,
    handleSaveProduct,
    handleStockAction,
  }
}
