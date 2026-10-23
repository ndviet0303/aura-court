import { ref, computed, onMounted } from 'vue'
import type {
  InventoryProduct,
  StockMovement,
  ProductFormPayload,
  StockTransferPayload,
} from '../types/inventory'
import { inventoryApi } from '../api/inventoryApi'
import { adminApi } from '../api/adminApi'
import { useOwnerCheck } from './useLifecycleCommands'
import { resolveLocalizedText } from '@/i18n'
import type { Venue } from '@/features/court/types/court'
import type { ApiError } from '@/core/types/api'
import { errorMessage } from '@/core/utils/errorMessage'

export function useAdminInventory() {
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const errorMsg = ref('')

  const activeTab = ref<'catalog' | 'movements'>('catalog')

  // Catalog Filters
  const searchQuery = ref('')
  const categoryFilter = ref<string>('all')
  const stockFilter = ref<'all' | 'low' | 'out'>('all')

  // Movement Filters
  const movementTypeFilter = ref<'all' | 'in' | 'out' | 'adjust' | 'sale' | 'return'>('all')
  const movementProductFilter = ref<number | 'all'>('all')
  const movementDateFrom = ref('')
  const movementDateTo = ref('')
  const movementSearch = ref('')

  // State Data
  const products = ref<InventoryProduct[]>([])
  const movements = ref<StockMovement[]>([])

  // Modals state
  const showProductModal = ref(false)
  const editingProduct = ref<InventoryProduct | null>(null)

  const showStockActionModal = ref(false)
  const stockActionType = ref<'in' | 'out' | 'adjust'>('in')
  const selectedProductForAction = ref<InventoryProduct | null>(null)

  const showDeleteModal = ref(false)
  const productToDelete = ref<InventoryProduct | null>(null)

  // Stock is kept per venue (pos-venue-inventory D9): the page reads and changes the chosen venue.
  const venues = ref<Venue[]>([])
  const venueId = ref<number | null>(null)
  const isOwnerOf = useOwnerCheck()
  const venueOptions = computed(() =>
    venues.value.map((v) => ({ value: v.id, label: resolveLocalizedText(v.name) })),
  )
  const canTransfer = computed(
    () => venues.value.length > 1 && isOwnerOf(venues.value[0]?.organization_id),
  )
  const showTransferModal = ref(false)
  const transferErrors = ref<Record<string, string[]>>({})

  // Derived Categories
  const categories = computed(() => {
    const defaultCats = ['drinks', 'equipment', 'accessories', 'snacks']
    const existingCats = products.value.map((p) => p.category).filter(Boolean)
    const set = new Set([...defaultCats, ...existingCats])
    return ['all', ...Array.from(set)]
  })

  // Filtered Products Catalog
  const filteredProducts = computed(() => {
    return products.value.filter((p) => {
      if (!p.is_active) return false
      if (categoryFilter.value !== 'all' && p.category !== categoryFilter.value) return false
      if (stockFilter.value === 'low' && (p.stock_quantity > p.min_stock || p.stock_quantity === 0))
        return false
      if (stockFilter.value === 'out' && p.stock_quantity > 0) return false

      if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase().trim()
        const matchName = (p.name || '').toLowerCase().includes(q)
        const matchSku = (p.sku || '').toLowerCase().includes(q)
        const matchBarcode = (p.barcode || '').toLowerCase().includes(q)
        return matchName || matchSku || matchBarcode
      }
      return true
    })
  })

  // Filtered Movements Log
  const filteredMovements = computed(() => {
    return movements.value.filter((m) => {
      if (movementTypeFilter.value !== 'all') {
        const type = movementTypeFilter.value
        if (type === 'in' && !['in', 'receipt', 'transfer_in'].includes(m.type)) return false
        if (type === 'out' && !['out', 'sale', 'disposal', 'transfer_out'].includes(m.type))
          return false
        if (type === 'adjust' && !['adjust', 'adjustment'].includes(m.type)) return false
        if (!['in', 'out', 'adjust'].includes(type) && m.type !== type) return false
      }

      if (
        movementProductFilter.value !== 'all' &&
        m.product_id !== Number(movementProductFilter.value)
      ) {
        return false
      }

      if (movementDateFrom.value) {
        const moveDate = (m.created_at || '').slice(0, 10)
        if (moveDate < movementDateFrom.value) return false
      }

      if (movementDateTo.value) {
        const moveDate = (m.created_at || '').slice(0, 10)
        if (moveDate > movementDateTo.value) return false
      }

      if (movementSearch.value) {
        const q = movementSearch.value.toLowerCase().trim()
        const matchName = (m.product_name || '').toLowerCase().includes(q)
        const matchSku = (m.product_sku || '').toLowerCase().includes(q)
        const matchNote = (m.note || '').toLowerCase().includes(q)
        const matchRef = (m.reference_code || '').toLowerCase().includes(q)
        return matchName || matchSku || matchNote || matchRef
      }

      return true
    })
  })

  // Live Real KPIs
  const kpis = computed(() => {
    const activeProducts = products.value.filter((p) => p.is_active)
    const totalItems = activeProducts.reduce((sum, p) => sum + (p.stock_quantity || 0), 0)
    const totalValue = activeProducts.reduce(
      (sum, p) => sum + (p.stock_quantity || 0) * (p.cost || 0),
      0,
    )
    const lowStockCount = activeProducts.filter(
      (p) => (p.stock_quantity || 0) <= (p.min_stock || 10) && (p.stock_quantity || 0) > 0,
    ).length
    const outOfStockCount = activeProducts.filter((p) => (p.stock_quantity || 0) === 0).length
    return { totalItems, totalValue, lowStockCount, outOfStockCount }
  })

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4500)
  }

  function showError(msg: string) {
    errorMsg.value = msg
    setTimeout(() => {
      if (errorMsg.value === msg) errorMsg.value = ''
    }, 6000)
  }

  const venueParams = () => (venueId.value ? { venue_id: venueId.value } : {})

  async function loadMovements() {
    const movs = await inventoryApi.getMovements(venueParams())
    if (Array.isArray(movs)) {
      movements.value = movs.map((m) => ({
        ...m,
        quantity: m.quantity !== undefined ? m.quantity : Math.abs(m.quantity_delta || 0),
      }))
    }
  }

  async function loadInventoryData() {
    loading.value = true
    errorMsg.value = ''
    try {
      if (!venues.value.length) {
        const list = await adminApi.getVenues()
        venues.value = Array.isArray(list) ? list : []
        venueId.value ??= venues.value[0]?.id ?? null
      }
      const [prodsRes] = await Promise.allSettled([
        inventoryApi.getProducts(venueParams()),
        loadMovements(),
      ])

      if (prodsRes.status === 'fulfilled' && Array.isArray(prodsRes.value)) {
        products.value = prodsRes.value
      }
    } catch (e: unknown) {
      showError(errorMessage(e, 'Không thể tải dữ liệu kho hàng.'))
    } finally {
      loading.value = false
    }
  }

  function selectVenue(id: number) {
    venueId.value = id
    return loadInventoryData()
  }

  function openTransfer() {
    transferErrors.value = {}
    showTransferModal.value = true
  }

  async function handleTransfer(payload: Omit<StockTransferPayload, 'from_venue_id'>) {
    if (!venueId.value) return
    saving.value = true
    transferErrors.value = {}
    try {
      await inventoryApi.createTransfer({ ...payload, from_venue_id: venueId.value })
      showTransferModal.value = false
      showNotice('Đã điều chuyển hàng sang cơ sở khác.')
      await loadInventoryData()
    } catch (e: unknown) {
      // 409 (not enough to move) and 422 come back by field, shown beside it in the modal.
      transferErrors.value = (e as ApiError)?.errors ?? {}
      if (!Object.keys(transferErrors.value).length) {
        showError(errorMessage(e, 'Không thể điều chuyển hàng.'))
      }
    } finally {
      saving.value = false
    }
  }

  function openAddProduct() {
    editingProduct.value = null
    showProductModal.value = true
  }

  function openEditProduct(p: InventoryProduct) {
    editingProduct.value = { ...p }
    showProductModal.value = true
  }

  function openStockAction(type: 'in' | 'out' | 'adjust', product?: InventoryProduct) {
    stockActionType.value = type
    selectedProductForAction.value = product || products.value[0] || null
    showStockActionModal.value = true
  }

  function openDeleteModal(product: InventoryProduct) {
    productToDelete.value = product
    showDeleteModal.value = true
  }

  async function handleSaveProduct(payload: ProductFormPayload) {
    saving.value = true
    errorMsg.value = ''
    try {
      if (editingProduct.value) {
        const updated = await inventoryApi.updateProduct(editingProduct.value.id, payload)
        const idx = products.value.findIndex((p) => p.id === editingProduct.value?.id)
        if (idx !== -1) {
          products.value[idx] = { ...products.value[idx], ...updated }
        }
        showNotice(`Đã cập nhật sản phẩm "${payload.name}" thành công!`)
      } else {
        // Opening stock lands at the venue shown.
        const created = await inventoryApi.createProduct({
          ...payload,
          venue_id: venueId.value ?? undefined,
        })
        products.value.unshift(created)
        showNotice(`Đã thêm mặt hàng mới "${payload.name}" vào kho!`)
        if ((payload.stock_quantity || 0) > 0) loadMovements()
      }
      showProductModal.value = false
    } catch (e: unknown) {
      showError(errorMessage(e, 'Không thể lưu thông tin sản phẩm.'))
    } finally {
      saving.value = false
    }
  }

  async function handleStockAction(payload: {
    quantity: number
    note?: string
    unitCost?: number
  }) {
    const p = selectedProductForAction.value
    const venue = venueId.value
    if (!p || !venue) return
    saving.value = true
    errorMsg.value = ''
    try {
      const updatedProduct = await inventoryApi.adjustStock({
        productId: p.id,
        venueId: venue,
        type: stockActionType.value,
        quantity: payload.quantity,
        unitCost: payload.unitCost,
        note: payload.note,
      })

      const idx = products.value.findIndex((item) => item.id === p.id)
      if (idx !== -1) {
        products.value[idx] = { ...products.value[idx], ...updatedProduct }
      }

      // Re-fetch movements to get exact server state with relations
      await loadMovements()

      showStockActionModal.value = false
      const actionName =
        stockActionType.value === 'in'
          ? 'nhập kho'
          : stockActionType.value === 'out'
            ? 'xuất kho'
            : 'kiểm kê điều chỉnh'
      showNotice(`Đã thực hiện ${actionName} cho "${p.name}" thành công!`)
    } catch (e: unknown) {
      showError(errorMessage(e, 'Không thể thực hiện thao tác kho hàng.'))
    } finally {
      saving.value = false
    }
  }

  async function handleDeleteProduct() {
    if (!productToDelete.value) return
    const target = productToDelete.value
    saving.value = true
    errorMsg.value = ''
    try {
      await inventoryApi.deleteProduct(target.id)
      products.value = products.value.filter((p) => p.id !== target.id)
      showDeleteModal.value = false
      showNotice(`Đã ngừng kinh doanh / xóa sản phẩm "${target.name}".`)
    } catch (e: unknown) {
      showError(errorMessage(e, 'Không thể xóa sản phẩm.'))
    } finally {
      saving.value = false
      productToDelete.value = null
    }
  }

  function resetCatalogFilters() {
    searchQuery.value = ''
    categoryFilter.value = 'all'
    stockFilter.value = 'all'
  }

  function resetMovementFilters() {
    movementTypeFilter.value = 'all'
    movementProductFilter.value = 'all'
    movementDateFrom.value = ''
    movementDateTo.value = ''
    movementSearch.value = ''
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
    movementProductFilter,
    movementDateFrom,
    movementDateTo,
    movementSearch,
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
    showDeleteModal,
    productToDelete,
    showNotice,
    showError,
    openAddProduct,
    openEditProduct,
    openStockAction,
    openDeleteModal,
    handleSaveProduct,
    handleStockAction,
    handleDeleteProduct,
    resetCatalogFilters,
    resetMovementFilters,
    loadInventoryData,
    venueOptions,
    venueId,
    canTransfer,
    showTransferModal,
    transferErrors,
    selectVenue,
    openTransfer,
    handleTransfer,
  }
}
