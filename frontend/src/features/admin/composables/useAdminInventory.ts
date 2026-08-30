import { ref, computed, onMounted } from 'vue'
import type { InventoryProduct, StockMovement, InventoryAudit } from '../types/inventory'
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

  const products = ref<InventoryProduct[]>([])
  const movements = ref<StockMovement[]>([])
  const audits = ref<InventoryAudit[]>([])

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

  async function loadInventoryData() {
    loading.value = true
    errorMsg.value = ''
    try {
      const [prodsRes, movsRes, auditsRes] = await Promise.allSettled([
        inventoryApi.getProducts(),
        inventoryApi.getMovements(),
        inventoryApi.getAudits(),
      ])

      if (prodsRes.status === 'fulfilled' && Array.isArray(prodsRes.value)) {
        products.value = prodsRes.value
      }
      if (movsRes.status === 'fulfilled' && Array.isArray(movsRes.value)) {
        movements.value = movsRes.value
      }
      if (auditsRes.status === 'fulfilled' && Array.isArray(auditsRes.value)) {
        audits.value = auditsRes.value
      }
    } catch (e: unknown) {
      errorMsg.value = errorMessage(e, 'Không thể tải dữ liệu kho hàng.')
    } finally {
      loading.value = false
    }
  }

  function openStockAction(type: 'in' | 'out' | 'adjust', product?: InventoryProduct) {
    stockActionType.value = type
    selectedProductForAction.value = product || products.value[0] || null
    showStockActionModal.value = true
  }

  async function handleSaveProduct(payload: any) {
    saving.value = true
    try {
      if (editingProduct.value) {
        const updated = await inventoryApi.updateProduct(editingProduct.value.id, payload)
        const idx = products.value.findIndex((p) => p.id === editingProduct.value?.id)
        if (idx !== -1) {
          products.value[idx] = { ...products.value[idx], ...(updated || payload) }
        }
        showNotice(`Đã cập nhật sản phẩm "${payload.name}"`)
      } else {
        const created = await inventoryApi.createProduct(payload)
        products.value.unshift(created || {
          id: Date.now(),
          ...payload,
          is_active: true,
        })
        showNotice(`Đã thêm sản phẩm mới "${payload.name}"`)
      }
      showProductModal.value = false
    } catch (e: unknown) {
      errorMsg.value = errorMessage(e, 'Không thể lưu sản phẩm.')
    } finally {
      saving.value = false
    }
  }

  async function handleStockAction(quantity: number, note: string) {
    if (!selectedProductForAction.value) return
    const p = selectedProductForAction.value
    saving.value = true
    try {
      await inventoryApi.adjustStock(p.id, {
        quantity,
        type: stockActionType.value,
        note,
      })

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
    } catch (e: unknown) {
      errorMsg.value = errorMessage(e, 'Không thể thực hiện thao tác kho.')
    } finally {
      saving.value = false
    }
  }

  onMounted(() => {
    loadInventoryData()
  })

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
    loadInventoryData,
  }
}
