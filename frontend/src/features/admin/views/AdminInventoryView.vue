<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

import type { InventoryProduct, StockMovement, InventoryAudit, Supplier } from '../types/inventory'
import {
  INITIAL_INVENTORY_PRODUCTS,
  INITIAL_STOCK_MOVEMENTS,
  INITIAL_INVENTORY_AUDITS,
  INITIAL_SUPPLIERS
} from '../types/inventory'
import { inventoryApi } from '../api/inventoryApi'

// ─── State ─────────────────────────────────────────────────────────
const loading = ref(false)
const saving = ref(false)
const notice = ref('')
const errorMsg = ref('')
const liveClock = ref(new Date().toLocaleTimeString('vi-VN'))
let clockInterval: ReturnType<typeof setInterval> | null = null

// Tabs & Filters
const activeTab = ref<'catalog' | 'movements'>('catalog')
const searchQuery = ref('')
const categoryFilter = ref<string>('all')
const stockFilter = ref<'all' | 'low' | 'out'>('all')
const movementTypeFilter = ref<'all' | 'in' | 'out' | 'adjust' | 'return'>('all')
const sortField = ref<'name' | 'stock_quantity' | 'price' | 'category'>('name')
const sortOrder = ref<'asc' | 'desc'>('asc')

// Modals
const showAddProductModal = ref(false)
const showStockInModal = ref(false)
const showStockOutModal = ref(false)
const showAuditModal = ref(false)
const showProductDetailModal = ref(false)
const showEditProductModal = ref(false)
const selectedProduct = ref<InventoryProduct | null>(null)

// Form State
const newProduct = ref({
  name: '',
  sku: '',
  barcode: '',
  category: 'drinks' as InventoryProduct['category'],
  price: 0,
  cost: 0,
  stock_quantity: 0,
  min_stock: 10,
  unit: 'cái',
  description: '',
  supplier: ''
})

const stockForm = ref({
  product_id: 0,
  quantity: 1,
  note: '',
  reference: ''
})

// Data State
const products = ref<InventoryProduct[]>([...INITIAL_INVENTORY_PRODUCTS])
const movements = ref<StockMovement[]>([...INITIAL_STOCK_MOVEMENTS])
const audits = ref<InventoryAudit[]>([...INITIAL_INVENTORY_AUDITS])

// ─── Computed ──────────────────────────────────────────────────────
const categories = computed(() => {
  const cats = new Set(products.value.map(p => p.category))
  return ['all', ...Array.from(cats)]
})

const categoryLabel = (cat: string) => {
  const map: Record<string, string> = {
    all: 'Tất cả', drinks: 'Đồ Uống', equipment: 'Thiết Bị',
    accessories: 'Phụ Kiện', rental: 'Cho Thuê', services: 'Dịch Vụ', snacks: 'Đồ Ăn Nhẹ'
  }
  return map[cat] || cat
}

const categoryColor = (cat: string) => {
  const map: Record<string, string> = {
    drinks: 'bg-tertiary-container/50 text-on-tertiary-container border-tertiary-container',
    equipment: 'bg-secondary-container/50 text-on-secondary-container border-secondary-container',
    accessories: 'bg-primary-container/50 text-on-primary-container border-primary-container',
    rental: 'bg-inverse-primary/30 text-primary border-inverse-primary',
    services: 'bg-surface-container-high text-on-surface-variant border-outline-variant',
    snacks: 'bg-primary-fixed/50 text-on-primary-fixed-variant border-primary-fixed-dim'
  }
  return map[cat] || 'bg-surface-container-high text-on-surface-variant border-outline-variant'
}

const filteredProducts = computed(() => {
  let list = products.value.filter(p => p.is_active)

  if (categoryFilter.value !== 'all') {
    list = list.filter(p => p.category === categoryFilter.value)
  }

  if (stockFilter.value === 'low') {
    list = list.filter(p => p.stock_quantity > 0 && p.stock_quantity <= p.min_stock)
  } else if (stockFilter.value === 'out') {
    list = list.filter(p => p.stock_quantity === 0)
  }

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(p =>
      p.name.toLowerCase().includes(q) ||
      p.sku.toLowerCase().includes(q) ||
      (p.barcode && p.barcode.includes(q))
    )
  }

  list.sort((a, b) => {
    let cmp = 0
    if (sortField.value === 'name') cmp = a.name.localeCompare(b.name)
    else if (sortField.value === 'stock_quantity') cmp = a.stock_quantity - b.stock_quantity
    else if (sortField.value === 'price') cmp = a.price - b.price
    else if (sortField.value === 'category') cmp = a.category.localeCompare(b.category)
    return sortOrder.value === 'asc' ? cmp : -cmp
  })

  return list
})

const filteredMovements = computed(() => {
  let list = [...movements.value]

  if (movementTypeFilter.value !== 'all') {
    list = list.filter(m => m.type === movementTypeFilter.value)
  }

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(m =>
      m.product_name.toLowerCase().includes(q) ||
      m.product_sku.toLowerCase().includes(q) ||
      m.reference_code.toLowerCase().includes(q)
    )
  }

  list.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
  return list
})

const totalSku = computed(() => products.value.filter(p => p.is_active).length)

const lowStockProducts = computed(() =>
  products.value.filter(p => p.is_active && p.stock_quantity > 0 && p.stock_quantity <= p.min_stock)
)

const outOfStockProducts = computed(() =>
  products.value.filter(p => p.is_active && p.stock_quantity === 0)
)

const lowStockCount = computed(() => lowStockProducts.value.length + outOfStockProducts.value.length)

const totalInventoryValue = computed(() =>
  products.value.reduce((sum, p) => {
    if (p.category === 'services') return sum
    return sum + (p.cost * p.stock_quantity)
  }, 0)
)

const totalRetailValue = computed(() =>
  products.value.reduce((sum, p) => {
    if (p.category === 'services') return sum
    return sum + (p.price * p.stock_quantity)
  }, 0)
)

const stockPercentage = (p: InventoryProduct) => {
  if (p.min_stock === 0) return 100
  return Math.min(100, Math.round((p.stock_quantity / (p.min_stock * 2)) * 100))
}

const stockBarColor = (p: InventoryProduct) => {
  if (p.stock_quantity === 0) return 'bg-on-surface-variant/30'
  if (p.stock_quantity <= p.min_stock) return 'bg-error'
  if (p.stock_quantity <= p.min_stock * 1.5) return 'bg-error/60'
  return 'bg-primary/60'
}

const formatCurrency = (val: number) => {
  if (val >= 1000000000) return `${(val / 1000000000).toFixed(1)}B ₫`
  if (val >= 1000000) return `${(val / 1000000).toFixed(1)}M ₫`
  return val.toLocaleString('vi-VN') + ' ₫'
}

const formatDate = (dateStr: string) => {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const formatDateTime = (dateStr: string) => {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

const movementTypeLabel = (type: StockMovement['type']) => {
  const map: Record<string, { label: string; icon: string; color: string }> = {
    in: { label: 'Nhập hàng', icon: 'arrow_downward', color: 'text-green-600 bg-green-50' },
    out: { label: 'Xuất hàng', icon: 'arrow_upward', color: 'text-error bg-error-container/30' },
    adjust: { label: 'Điều chỉnh', icon: 'sync_alt', color: 'text-on-surface-variant bg-surface-container-high' },
    return: { label: 'Hoàn trả', icon: 'assignment_return', color: 'text-primary bg-primary-container/30' }
  }
  return map[type] || { label: type, icon: 'circle', color: '' }
}

// ─── Actions ───────────────────────────────────────────────────────
const toggleSort = (field: typeof sortField.value) => {
  if (sortField.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortOrder.value = 'asc'
  }
}

const openStockIn = (product: InventoryProduct) => {
  selectedProduct.value = product
  stockForm.value = { product_id: product.id, quantity: 1, note: '', reference: '' }
  showStockInModal.value = true
}

const openStockOut = (product: InventoryProduct) => {
  selectedProduct.value = product
  stockForm.value = { product_id: product.id, quantity: 1, note: '', reference: '' }
  showStockOutModal.value = true
}

const openProductDetail = (product: InventoryProduct) => {
  selectedProduct.value = product
  showProductDetailModal.value = true
}

const confirmStockIn = () => {
  if (!selectedProduct.value) return
  const p = products.value.find(x => x.id === selectedProduct.value!.id)
  if (p) {
    const qty = stockForm.value.quantity
    p.stock_quantity += qty
    p.last_restocked = new Date().toISOString().slice(0, 10)
    movements.value.unshift({
      id: movements.value.length + 1,
      product_id: p.id, product_name: p.name, product_sku: p.sku,
      type: 'in', quantity: qty, unit: p.unit,
      reference_code: `GRN-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}-${String(movements.value.length + 1).padStart(3, '0')}`,
      note: stockForm.value.note || 'Nhập hàng', created_by: auth.user?.name || 'Admin',
      created_at: new Date().toISOString().replace('T', ' ').slice(0, 19),
      balance_after: p.stock_quantity
    })
    notice.value = `Đã nhập ${qty} ${p.unit} ${p.name}`
    setTimeout(() => notice.value = '', 3000)
  }
  showStockInModal.value = false
}

const confirmStockOut = () => {
  if (!selectedProduct.value) return
  const p = products.value.find(x => x.id === selectedProduct.value!.id)
  if (p) {
    const qty = Math.min(stockForm.value.quantity, p.stock_quantity)
    if (qty <= 0) { errorMsg.value = 'Số lượng xuất không hợp lệ'; setTimeout(() => errorMsg.value = '', 3000); return }
    p.stock_quantity -= qty
    movements.value.unshift({
      id: movements.value.length + 1,
      product_id: p.id, product_name: p.name, product_sku: p.sku,
      type: 'out', quantity: qty, unit: p.unit,
      reference_code: `EXP-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}-${String(movements.value.length + 1).padStart(3, '0')}`,
      note: stockForm.value.note || 'Xuất hàng', created_by: auth.user?.name || 'Admin',
      created_at: new Date().toISOString().replace('T', ' ').slice(0, 19),
      balance_after: p.stock_quantity
    })
    notice.value = `Đã xuất ${qty} ${p.unit} ${p.name}`
    setTimeout(() => notice.value = '', 3000)
  }
  showStockOutModal.value = false
}

const confirmAddProduct = () => {
  const p = newProduct.value
  if (!p.name || !p.sku) { errorMsg.value = 'Vui lòng nhập tên và SKU'; setTimeout(() => errorMsg.value = '', 3000); return }
  products.value.push({
    id: products.value.length + 1,
    ...p,
    is_active: true
  })
  notice.value = `Đã thêm sản phẩm: ${p.name}`
  setTimeout(() => notice.value = '', 3000)
  showAddProductModal.value = false
  newProduct.value = { name: '', sku: '', barcode: '', category: 'drinks', price: 0, cost: 0, stock_quantity: 0, min_stock: 10, unit: 'cái', description: '', supplier: '' }
}

const createAudit = () => {
  const newAudit: InventoryAudit = {
    id: audits.value.length + 1,
    code: `KK-2026-${String(audits.value.length + 8).padStart(3, '0')}`,
    status: 'draft',
    created_by: auth.user?.name || 'Admin',
    created_at: new Date().toISOString().replace('T', ' ').slice(0, 19),
    total_items: totalSku.value,
    matched_items: 0,
    variance_items: 0
  }
  audits.value.unshift(newAudit)
  notice.value = `Đã tạo phiếu kiểm kê ${newAudit.code}`
  setTimeout(() => notice.value = '', 3000)
  showAuditModal.value = false
}

// ─── Lifecycle ─────────────────────────────────────────────────────
onMounted(() => {
  clockInterval = setInterval(() => {
    liveClock.value = new Date().toLocaleTimeString('vi-VN')
  }, 1000)
})

onUnmounted(() => {
  if (clockInterval) clearInterval(clockInterval)
})
</script>

<template>
  <div class="inventory-view">
    <!-- Toast Notifications -->
    <Transition name="toast">
      <div v-if="notice" class="fixed top-20 right-6 z-[200] bg-green-600 text-white px-6 py-3 rounded-xl shadow-lg flex items-center gap-3 text-sm font-medium">
        <span class="material-symbols-outlined text-[20px]">check_circle</span>
        {{ notice }}
      </div>
    </Transition>
    <Transition name="toast">
      <div v-if="errorMsg" class="fixed top-20 right-6 z-[200] bg-red-600 text-white px-6 py-3 rounded-xl shadow-lg flex items-center gap-3 text-sm font-medium">
        <span class="material-symbols-outlined text-[20px]">error</span>
        {{ errorMsg }}
      </div>
    </Transition>

    <!-- ── Page Content ── -->
    <div class="space-y-8">
      <!-- Header -->
      <div class="flex items-end justify-between">
        <div class="space-y-1">
          <h1 class="text-3xl font-semibold text-[var(--c-on-surface)] tracking-tight">Quản lý Kho</h1>
          <p class="text-lg text-[var(--c-on-surface-variant)]">Theo dõi tồn kho, nhập xuất và kiểm kê hàng hóa tại Aura Court.</p>
        </div>
        <button
          class="admin-btn-primary flex items-center gap-2"
          @click="showAuditModal = true"
        >
          <span class="material-symbols-outlined text-[20px]">assignment_add</span>
          Tạo Phiếu Kiểm Kê
        </button>
      </div>

      <!-- ── Stats Cards ── -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total SKU -->
        <div class="stat-card group">
          <div class="flex justify-between items-start z-10 relative">
            <span class="stat-label">TỔNG SKU</span>
            <div class="stat-icon-wrap">
              <span class="material-symbols-outlined text-[var(--c-primary)]">inventory_2</span>
            </div>
          </div>
          <div class="z-10 relative">
            <span class="stat-value">{{ totalSku }}</span>
            <span class="stat-sub">+{{ products.filter(p => p.is_active && p.last_restocked && new Date(p.last_restocked) > new Date(Date.now() - 30 * 86400000)).length }} tháng này</span>
          </div>
          <div class="stat-glow"></div>
        </div>

        <!-- Low Stock Warning -->
        <div class="stat-card stat-card--warning group">
          <div class="flex justify-between items-start z-10 relative">
            <span class="stat-label stat-label--error">CẢNH BÁO SẮP HẾT</span>
            <div class="stat-icon-wrap stat-icon-wrap--error">
              <span class="material-symbols-outlined text-[var(--c-error)]">warning</span>
            </div>
          </div>
          <div class="z-10 relative">
            <span class="stat-value stat-value--error">{{ lowStockCount }}</span>
            <span class="stat-sub stat-sub--error">Cần nhập hàng ngay</span>
          </div>
          <div class="stat-glow stat-glow--error"></div>
        </div>

        <!-- Total Inventory Value -->
        <div class="stat-card group">
          <div class="flex justify-between items-start z-10 relative">
            <span class="stat-label">TỔNG GIÁ TRỊ TỒN</span>
            <div class="stat-icon-wrap">
              <span class="material-symbols-outlined text-[var(--c-primary)]">payments</span>
            </div>
          </div>
          <div class="z-10 relative">
            <span class="stat-value stat-value--lg">{{ formatCurrency(totalInventoryValue) }}</span>
            <span class="stat-sub">Giá trị quy đổi (giá vốn)</span>
          </div>
          <div class="stat-glow"></div>
        </div>
      </div>

      <!-- ── Main Content Grid ── -->
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start">
        <!-- Left: Table Area -->
        <div class="lg:col-span-3 space-y-4">
          <!-- Tabs & Filters -->
          <div class="flex items-center justify-between border-b border-[var(--c-outline-variant)]/30 pb-4">
            <div class="flex gap-6">
              <button
                :class="['tab-btn', activeTab === 'catalog' && 'tab-btn--active']"
                @click="activeTab = 'catalog'"
              >
                Danh Mục Hàng Hóa
              </button>
              <button
                :class="['tab-btn', activeTab === 'movements' && 'tab-btn--active']"
                @click="activeTab = 'movements'"
              >
                Lịch Sử Nhập/Xuất
              </button>
            </div>
            <div class="flex gap-3">
              <!-- Category Filter (catalog only) -->
              <select
                v-if="activeTab === 'catalog'"
                v-model="categoryFilter"
                class="inv-select"
              >
                <option v-for="cat in categories" :key="cat" :value="cat">{{ categoryLabel(cat) }}</option>
              </select>
              <!-- Stock filter -->
              <select
                v-if="activeTab === 'catalog'"
                v-model="stockFilter"
                class="inv-select"
              >
                <option value="all">Tất cả tồn kho</option>
                <option value="low">Sắp hết hàng</option>
                <option value="out">Hết hàng</option>
              </select>
              <!-- Movement type filter -->
              <select
                v-if="activeTab === 'movements'"
                v-model="movementTypeFilter"
                class="inv-select"
              >
                <option value="all">Tất cả loại</option>
                <option value="in">Nhập hàng</option>
                <option value="out">Xuất hàng</option>
                <option value="adjust">Điều chỉnh</option>
                <option value="return">Hoàn trả</option>
              </select>
              <!-- Search -->
              <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[var(--c-on-surface-variant)] text-[18px]">search</span>
                <input
                  v-model="searchQuery"
                  type="text"
                  :placeholder="activeTab === 'catalog' ? 'Tìm kiếm SKU, tên...' : 'Tìm mã phiếu, sản phẩm...'"
                  class="inv-search-input"
                />
              </div>
              <button
                v-if="activeTab === 'catalog'"
                class="inv-icon-btn"
                title="Thêm sản phẩm"
                @click="showAddProductModal = true"
              >
                <span class="material-symbols-outlined text-[20px]">add</span>
              </button>
            </div>
          </div>

          <!-- ── Catalog Table ── -->
          <div v-if="activeTab === 'catalog'" class="inv-table-wrap">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="inv-thead-row">
                  <th class="inv-th cursor-pointer" @click="toggleSort('name')">
                    Sản Phẩm
                    <span v-if="sortField === 'name'" class="material-symbols-outlined text-[14px] align-middle">
                      {{ sortOrder === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
                    </span>
                  </th>
                  <th class="inv-th cursor-pointer" @click="toggleSort('category')">
                    Danh Mục
                    <span v-if="sortField === 'category'" class="material-symbols-outlined text-[14px] align-middle">
                      {{ sortOrder === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
                    </span>
                  </th>
                  <th class="inv-th cursor-pointer" @click="toggleSort('stock_quantity')">
                    Tồn Kho
                    <span v-if="sortField === 'stock_quantity'" class="material-symbols-outlined text-[14px] align-middle">
                      {{ sortOrder === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
                    </span>
                  </th>
                  <th class="inv-th cursor-pointer" @click="toggleSort('price')">
                    Giá Bán
                    <span v-if="sortField === 'price'" class="material-symbols-outlined text-[14px] align-middle">
                      {{ sortOrder === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
                    </span>
                  </th>
                  <th class="inv-th text-right">Thao Tác</th>
                </tr>
              </thead>
              <tbody class="text-sm">
                <tr v-if="filteredProducts.length === 0">
                  <td colspan="5" class="p-8 text-center text-[var(--c-on-surface-variant)]">
                    <span class="material-symbols-outlined text-[48px] block mb-2 opacity-30">inventory_2</span>
                    Không tìm thấy sản phẩm nào
                  </td>
                </tr>
                <tr
                  v-for="product in filteredProducts"
                  :key="product.id"
                  class="inv-row group"
                  @click="openProductDetail(product)"
                >
                  <td class="p-4">
                    <div class="flex items-center gap-3">
                      <div class="w-12 h-12 rounded-lg bg-[var(--c-surface-container-high)] overflow-hidden shrink-0 shadow-sm border border-[var(--c-outline-variant)]/10">
                        <img
                          v-if="product.image_url"
                          :src="product.image_url"
                          :alt="product.name"
                          class="w-full h-full object-cover"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center">
                          <span class="material-symbols-outlined text-[var(--c-on-surface-variant)]/40 text-[24px]">
                            {{ product.category === 'drinks' ? 'local_drink' : product.category === 'equipment' ? 'sports_tennis' : product.category === 'rental' ? 'handshake' : product.category === 'services' ? 'build' : product.category === 'snacks' ? 'fastfood' : 'inventory_2' }}
                          </span>
                        </div>
                      </div>
                      <div class="min-w-0">
                        <p class="text-[var(--c-on-surface)] font-medium truncate">{{ product.name }}</p>
                        <p class="text-xs text-[var(--c-on-surface-variant)]/70 font-mono">SKU: {{ product.sku }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="p-4">
                    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-[12px] font-medium border', categoryColor(product.category)]">
                      {{ categoryLabel(product.category) }}
                    </span>
                  </td>
                  <td class="p-4">
                    <div v-if="product.category !== 'services'" class="flex flex-col gap-1 w-32">
                      <div class="flex justify-between items-center text-[13px]">
                        <span :class="[product.stock_quantity <= product.min_stock ? 'text-[var(--c-error)] font-semibold' : 'text-[var(--c-on-surface)] font-semibold']">
                          {{ product.stock_quantity }} {{ product.unit }}
                        </span>
                        <span class="text-[var(--c-on-surface-variant)]/50" v-if="product.min_stock > 0">/ Min {{ product.min_stock }}</span>
                      </div>
                      <div class="w-full h-1.5 bg-[var(--c-surface-variant)] rounded-full overflow-hidden">
                        <div :class="['h-full rounded-full transition-all', stockBarColor(product)]" :style="{ width: `${stockPercentage(product)}%` }"></div>
                      </div>
                    </div>
                    <span v-else class="text-[var(--c-on-surface-variant)] text-xs italic">Dịch vụ</span>
                  </td>
                  <td class="p-4 text-[var(--c-on-surface)] font-mono">{{ product.price.toLocaleString('vi-VN') }} ₫</td>
                  <td class="p-4 text-right" @click.stop>
                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                      <button class="inv-action-btn" title="Chỉnh sửa" @click.stop="openProductDetail(product)">
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                      </button>
                      <button
                        v-if="product.category !== 'services'"
                        class="inv-action-btn inv-action-btn--primary"
                        title="Nhập hàng"
                        @click.stop="openStockIn(product)"
                      >
                        <span class="material-symbols-outlined text-[18px]">add_circle</span>
                      </button>
                      <button
                        v-if="product.category !== 'services' && product.stock_quantity > 0"
                        class="inv-action-btn inv-action-btn--danger"
                        title="Xuất hàng"
                        @click.stop="openStockOut(product)"
                      >
                        <span class="material-symbols-outlined text-[18px]">remove_circle</span>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
            <div class="p-3 flex justify-center border-t border-[var(--c-outline-variant)]/10">
              <span class="text-xs text-[var(--c-on-surface-variant)]">
                Hiển thị {{ filteredProducts.length }} / {{ totalSku }} sản phẩm
              </span>
            </div>
          </div>

          <!-- ── Movements Table ── -->
          <div v-if="activeTab === 'movements'" class="inv-table-wrap">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="inv-thead-row">
                  <th class="inv-th">Thời Gian</th>
                  <th class="inv-th">Loại</th>
                  <th class="inv-th">Sản Phẩm</th>
                  <th class="inv-th">Số Lượng</th>
                  <th class="inv-th">Mã Phiếu</th>
                  <th class="inv-th">Người Thực Hiện</th>
                  <th class="inv-th">Tồn Sau</th>
                </tr>
              </thead>
              <tbody class="text-sm">
                <tr v-if="filteredMovements.length === 0">
                  <td colspan="7" class="p-8 text-center text-[var(--c-on-surface-variant)]">
                    <span class="material-symbols-outlined text-[48px] block mb-2 opacity-30">swap_vert</span>
                    Không có lịch sử nhập/xuất
                  </td>
                </tr>
                <tr
                  v-for="mv in filteredMovements"
                  :key="mv.id"
                  class="inv-row"
                >
                  <td class="p-4 text-[var(--c-on-surface-variant)] text-xs font-mono whitespace-nowrap">
                    {{ formatDateTime(mv.created_at) }}
                  </td>
                  <td class="p-4">
                    <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[12px] font-medium', movementTypeLabel(mv.type).color]">
                      <span class="material-symbols-outlined text-[14px]">{{ movementTypeLabel(mv.type).icon }}</span>
                      {{ movementTypeLabel(mv.type).label }}
                    </span>
                  </td>
                  <td class="p-4">
                    <div class="min-w-0">
                      <p class="text-[var(--c-on-surface)] font-medium truncate text-sm">{{ mv.product_name }}</p>
                      <p class="text-[11px] text-[var(--c-on-surface-variant)]/60 font-mono">{{ mv.product_sku }}</p>
                    </div>
                  </td>
                  <td class="p-4 font-mono font-semibold" :class="mv.type === 'in' || mv.type === 'return' ? 'text-green-600' : mv.type === 'out' ? 'text-[var(--c-error)]' : 'text-[var(--c-on-surface)]'">
                    {{ mv.type === 'in' || mv.type === 'return' ? '+' : '-' }}{{ Math.abs(mv.quantity) }} {{ mv.unit }}
                  </td>
                  <td class="p-4 text-xs font-mono text-[var(--c-on-surface-variant)]">{{ mv.reference_code }}</td>
                  <td class="p-4 text-xs text-[var(--c-on-surface-variant)]">{{ mv.created_by }}</td>
                  <td class="p-4 font-mono text-sm text-[var(--c-on-surface)]">{{ mv.balance_after }} {{ mv.unit }}</td>
                </tr>
              </tbody>
            </table>
            <div class="p-3 flex justify-center border-t border-[var(--c-outline-variant)]/10">
              <span class="text-xs text-[var(--c-on-surface-variant)]">
                Hiển thị {{ filteredMovements.length }} bản ghi
              </span>
            </div>
          </div>
        </div>

        <!-- Right Sidebar -->
        <div class="lg:col-span-1 space-y-6">
          <!-- Urgent Restocking -->
          <div>
            <h3 class="text-lg font-semibold text-[var(--c-on-surface)] flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-[var(--c-error)]">warning</span>
              Cần Nhập Gấp
            </h3>
            <div class="flex flex-col gap-3">
              <!-- Out of stock items -->
              <div
                v-for="p in outOfStockProducts"
                :key="'out-'+p.id"
                class="inv-urgent-card inv-urgent-card--critical"
                @click="openStockIn(p)"
              >
                <div class="w-10 h-10 rounded-lg bg-[var(--c-surface-container-high)] overflow-hidden shrink-0">
                  <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="w-full h-full object-cover" />
                  <div v-else class="w-full h-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-[var(--c-on-surface-variant)]/40">inventory_2</span>
                  </div>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-semibold text-[var(--c-on-surface)] truncate">{{ p.name }}</p>
                  <p class="text-[11px] text-[var(--c-error)] font-medium">HẾT HÀNG!</p>
                </div>
                <button class="w-8 h-8 flex items-center justify-center rounded-full bg-[var(--c-primary)]/10 text-[var(--c-primary)] hover:bg-[var(--c-primary)]/20 transition-colors shrink-0">
                  <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span>
                </button>
              </div>

              <!-- Low stock items -->
              <div
                v-for="p in lowStockProducts"
                :key="'low-'+p.id"
                class="inv-urgent-card"
                @click="openStockIn(p)"
              >
                <div class="w-10 h-10 rounded-lg bg-[var(--c-surface-container-high)] overflow-hidden shrink-0">
                  <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="w-full h-full object-cover" />
                  <div v-else class="w-full h-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-[var(--c-on-surface-variant)]/40">inventory_2</span>
                  </div>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-semibold text-[var(--c-on-surface)] truncate">{{ p.name }}</p>
                  <p class="text-[11px] text-[var(--c-error)]/80 font-medium">Còn {{ p.stock_quantity }} {{ p.unit }} (Dưới min {{ p.min_stock }})</p>
                </div>
                <button class="w-8 h-8 flex items-center justify-center rounded-full bg-[var(--c-primary)]/10 text-[var(--c-primary)] hover:bg-[var(--c-primary)]/20 transition-colors shrink-0">
                  <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span>
                </button>
              </div>

              <div v-if="lowStockCount === 0" class="text-center py-4 text-[var(--c-on-surface-variant)] text-sm">
                <span class="material-symbols-outlined text-[32px] block mb-1 opacity-30">check_circle</span>
                Tồn kho ổn định
              </div>
            </div>
          </div>

          <!-- Audit Schedule -->
          <div class="inv-audit-card">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-[var(--c-primary)]/10 rounded-full blur-xl"></div>
            <span class="material-symbols-outlined text-[var(--c-primary)] text-[32px] mb-2 relative z-10">inventory</span>
            <h4 class="text-sm font-semibold text-[var(--c-on-surface)] mb-1 relative z-10">Lịch Kiểm Kê Tới</h4>
            <p class="text-[13px] text-[var(--c-on-surface-variant)] mb-3 relative z-10">Dự kiến: Cuối tháng (30/09)</p>
            <button
              class="w-full py-2 rounded-lg border border-[var(--c-primary)] text-[var(--c-primary)] text-sm font-semibold hover:bg-[var(--c-primary)]/5 transition-colors relative z-10"
              @click="showAuditModal = true"
            >
              Lên Lịch Ngay
            </button>
          </div>

          <!-- Quick Stats -->
          <div class="inv-quick-stats">
            <h4 class="text-sm font-semibold text-[var(--c-on-surface)] mb-3">Thống Kê Nhanh</h4>
            <div class="space-y-2 text-xs">
              <div class="flex justify-between">
                <span class="text-[var(--c-on-surface-variant)]">Giá trị bán lẻ</span>
                <span class="font-mono font-semibold text-[var(--c-on-surface)]">{{ formatCurrency(totalRetailValue) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-[var(--c-on-surface-variant)]">Giá trị vốn</span>
                <span class="font-mono font-semibold text-[var(--c-on-surface)]">{{ formatCurrency(totalInventoryValue) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-[var(--c-on-surface-variant)]">Biên lợi nhuận ước tính</span>
                <span class="font-mono font-semibold text-green-600">
                  {{ totalInventoryValue > 0 ? Math.round(((totalRetailValue - totalInventoryValue) / totalRetailValue) * 100) : 0 }}%
                </span>
              </div>
              <hr class="border-[var(--c-outline-variant)]/20 my-1" />
              <div class="flex justify-between">
                <span class="text-[var(--c-on-surface-variant)]">Nhập/xuất hôm nay</span>
                <span class="font-mono font-semibold text-[var(--c-on-surface)]">{{ movements.filter(m => m.created_at.startsWith('2026-08-29')).length }} phiếu</span>
              </div>
              <div class="flex justify-between">
                <span class="text-[var(--c-on-surface-variant)]">Cập nhật lúc</span>
                <span class="font-mono text-[var(--c-on-surface-variant)]">{{ liveClock }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════ MODALS ═══════════════ -->

    <!-- Stock In Modal -->
    <Transition name="modal">
      <div v-if="showStockInModal" class="modal-overlay" @click.self="showStockInModal = false">
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h3 class="modal-title">
              <span class="material-symbols-outlined text-green-600">add_circle</span>
              Nhập Hàng
            </h3>
            <button class="modal-close" @click="showStockInModal = false">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
          <div class="modal-body" v-if="selectedProduct">
            <div class="flex items-center gap-3 mb-4 p-3 bg-[var(--c-surface-container)] rounded-xl">
              <div class="w-12 h-12 rounded-lg bg-[var(--c-surface-container-high)] overflow-hidden shrink-0">
                <img v-if="selectedProduct.image_url" :src="selectedProduct.image_url" :alt="selectedProduct.name" class="w-full h-full object-cover" />
              </div>
              <div>
                <p class="font-medium text-[var(--c-on-surface)]">{{ selectedProduct.name }}</p>
                <p class="text-xs text-[var(--c-on-surface-variant)] font-mono">{{ selectedProduct.sku }} · Tồn: {{ selectedProduct.stock_quantity }} {{ selectedProduct.unit }}</p>
              </div>
            </div>
            <div class="space-y-4">
              <div>
                <label class="inv-label">Số lượng nhập ({{ selectedProduct.unit }})</label>
                <input v-model.number="stockForm.quantity" type="number" min="1" class="inv-input" />
              </div>
              <div>
                <label class="inv-label">Ghi chú</label>
                <input v-model="stockForm.note" type="text" placeholder="VD: Nhập hàng từ NCC..." class="inv-input" />
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="inv-btn-secondary" @click="showStockInModal = false">Hủy</button>
            <button class="inv-btn-success" @click="confirmStockIn">
              <span class="material-symbols-outlined text-[18px]">check</span>
              Xác Nhận Nhập
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Stock Out Modal -->
    <Transition name="modal">
      <div v-if="showStockOutModal" class="modal-overlay" @click.self="showStockOutModal = false">
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h3 class="modal-title">
              <span class="material-symbols-outlined text-[var(--c-error)]">remove_circle</span>
              Xuất Hàng
            </h3>
            <button class="modal-close" @click="showStockOutModal = false">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
          <div class="modal-body" v-if="selectedProduct">
            <div class="flex items-center gap-3 mb-4 p-3 bg-[var(--c-surface-container)] rounded-xl">
              <div class="w-12 h-12 rounded-lg bg-[var(--c-surface-container-high)] overflow-hidden shrink-0">
                <img v-if="selectedProduct.image_url" :src="selectedProduct.image_url" :alt="selectedProduct.name" class="w-full h-full object-cover" />
              </div>
              <div>
                <p class="font-medium text-[var(--c-on-surface)]">{{ selectedProduct.name }}</p>
                <p class="text-xs text-[var(--c-on-surface-variant)] font-mono">{{ selectedProduct.sku }} · Tồn: {{ selectedProduct.stock_quantity }} {{ selectedProduct.unit }}</p>
              </div>
            </div>
            <div class="space-y-4">
              <div>
                <label class="inv-label">Số lượng xuất ({{ selectedProduct.unit }}) — Tối đa: {{ selectedProduct.stock_quantity }}</label>
                <input v-model.number="stockForm.quantity" type="number" min="1" :max="selectedProduct.stock_quantity" class="inv-input" />
              </div>
              <div>
                <label class="inv-label">Lý do xuất</label>
                <input v-model="stockForm.note" type="text" placeholder="VD: Hỏng, mất, điều chuyển..." class="inv-input" />
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="inv-btn-secondary" @click="showStockOutModal = false">Hủy</button>
            <button class="inv-btn-danger" @click="confirmStockOut">
              <span class="material-symbols-outlined text-[18px]">check</span>
              Xác Nhận Xuất
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Add Product Modal -->
    <Transition name="modal">
      <div v-if="showAddProductModal" class="modal-overlay" @click.self="showAddProductModal = false">
        <div class="modal-content modal-md">
          <div class="modal-header">
            <h3 class="modal-title">
              <span class="material-symbols-outlined text-[var(--c-primary)]">add_box</span>
              Thêm Sản Phẩm Mới
            </h3>
            <button class="modal-close" @click="showAddProductModal = false">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="inv-label">Tên sản phẩm *</label>
                <input v-model="newProduct.name" type="text" placeholder="VD: Ống Cầu Yonex AS-30" class="inv-input" />
              </div>
              <div>
                <label class="inv-label">Mã SKU *</label>
                <input v-model="newProduct.sku" type="text" placeholder="VD: YNX-AS30" class="inv-input" />
              </div>
              <div>
                <label class="inv-label">Barcode</label>
                <input v-model="newProduct.barcode" type="text" placeholder="Quét mã vạch..." class="inv-input" />
              </div>
              <div>
                <label class="inv-label">Danh mục</label>
                <select v-model="newProduct.category" class="inv-input">
                  <option value="drinks">Đồ Uống</option>
                  <option value="equipment">Thiết Bị</option>
                  <option value="accessories">Phụ Kiện</option>
                  <option value="rental">Cho Thuê</option>
                  <option value="services">Dịch Vụ</option>
                  <option value="snacks">Đồ Ăn Nhẹ</option>
                </select>
              </div>
              <div>
                <label class="inv-label">Đơn vị tính</label>
                <input v-model="newProduct.unit" type="text" placeholder="cái, chai, ống..." class="inv-input" />
              </div>
              <div>
                <label class="inv-label">Giá bán (₫)</label>
                <input v-model.number="newProduct.price" type="number" min="0" class="inv-input" />
              </div>
              <div>
                <label class="inv-label">Giá vốn (₫)</label>
                <input v-model.number="newProduct.cost" type="number" min="0" class="inv-input" />
              </div>
              <div>
                <label class="inv-label">Số lượng ban đầu</label>
                <input v-model.number="newProduct.stock_quantity" type="number" min="0" class="inv-input" />
              </div>
              <div>
                <label class="inv-label">Tồn kho tối thiểu</label>
                <input v-model.number="newProduct.min_stock" type="number" min="0" class="inv-input" />
              </div>
              <div class="col-span-2">
                <label class="inv-label">Nhà cung cấp</label>
                <input v-model="newProduct.supplier" type="text" placeholder="VD: Yonex Vietnam" class="inv-input" />
              </div>
              <div class="col-span-2">
                <label class="inv-label">Mô tả</label>
                <textarea v-model="newProduct.description" rows="2" placeholder="Ghi chú..." class="inv-input"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="inv-btn-secondary" @click="showAddProductModal = false">Hủy</button>
            <button class="admin-btn-primary" @click="confirmAddProduct">
              <span class="material-symbols-outlined text-[18px]">add</span>
              Thêm Sản Phẩm
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Audit Modal -->
    <Transition name="modal">
      <div v-if="showAuditModal" class="modal-overlay" @click.self="showAuditModal = false">
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h3 class="modal-title">
              <span class="material-symbols-outlined text-[var(--c-primary)]">assignment_add</span>
              Tạo Phiếu Kiểm Kê
            </h3>
            <button class="modal-close" @click="showAuditModal = false">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
          <div class="modal-body">
            <p class="text-sm text-[var(--c-on-surface-variant)] mb-4">
              Phiếu kiểm kê sẽ được tạo với danh sách {{ totalSku }} SKU hiện có. Bạn có thể bắt đầu kiểm đếm ngay hoặc lên lịch sau.
            </p>
            <div class="p-4 bg-[var(--c-surface-container)] rounded-xl space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-[var(--c-on-surface-variant)]">Tổng SKU</span>
                <span class="font-semibold text-[var(--c-on-surface)]">{{ totalSku }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-[var(--c-on-surface-variant)]">Cần chú ý (sắp hết)</span>
                <span class="font-semibold text-[var(--c-error)]">{{ lowStockCount }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-[var(--c-on-surface-variant)]">Người tạo</span>
                <span class="font-semibold text-[var(--c-on-surface)]">{{ auth.user?.name || 'Admin' }}</span>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="inv-btn-secondary" @click="showAuditModal = false">Hủy</button>
            <button class="admin-btn-primary" @click="createAudit">
              <span class="material-symbols-outlined text-[18px]">check</span>
              Tạo Phiếu
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Product Detail Modal -->
    <Transition name="modal">
      <div v-if="showProductDetailModal && selectedProduct" class="modal-overlay" @click.self="showProductDetailModal = false">
        <div class="modal-content modal-md">
          <div class="modal-header">
            <h3 class="modal-title">
              <span class="material-symbols-outlined text-[var(--c-primary)]">inventory_2</span>
              Chi Tiết Sản Phẩm
            </h3>
            <button class="modal-close" @click="showProductDetailModal = false">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="flex gap-4 mb-6">
              <div class="w-20 h-20 rounded-xl bg-[var(--c-surface-container-high)] overflow-hidden shrink-0 border border-[var(--c-outline-variant)]/20">
                <img v-if="selectedProduct.image_url" :src="selectedProduct.image_url" :alt="selectedProduct.name" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex items-center justify-center">
                  <span class="material-symbols-outlined text-[var(--c-on-surface-variant)]/30 text-[36px]">inventory_2</span>
                </div>
              </div>
              <div>
                <h4 class="text-lg font-semibold text-[var(--c-on-surface)]">{{ selectedProduct.name }}</h4>
                <p class="text-sm text-[var(--c-on-surface-variant)] font-mono">SKU: {{ selectedProduct.sku }}</p>
                <p v-if="selectedProduct.barcode" class="text-sm text-[var(--c-on-surface-variant)] font-mono">Barcode: {{ selectedProduct.barcode }}</p>
                <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-[12px] font-medium border mt-1', categoryColor(selectedProduct.category)]">
                  {{ categoryLabel(selectedProduct.category) }}
                </span>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div class="detail-item">
                <span class="detail-label">Giá bán</span>
                <span class="detail-value font-mono">{{ selectedProduct.price.toLocaleString('vi-VN') }} ₫</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Giá vốn</span>
                <span class="detail-value font-mono">{{ selectedProduct.cost.toLocaleString('vi-VN') }} ₫</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Tồn kho</span>
                <span class="detail-value" :class="selectedProduct.stock_quantity <= selectedProduct.min_stock ? 'text-[var(--c-error)]' : ''">
                  {{ selectedProduct.stock_quantity }} {{ selectedProduct.unit }}
                </span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Tồn tối thiểu</span>
                <span class="detail-value">{{ selectedProduct.min_stock }} {{ selectedProduct.unit }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Nhà cung cấp</span>
                <span class="detail-value">{{ selectedProduct.supplier || '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Nhập lần cuối</span>
                <span class="detail-value">{{ formatDate(selectedProduct.last_restocked || '') }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Biên lợi nhuận</span>
                <span class="detail-value text-green-600 font-mono">
                  {{ selectedProduct.cost > 0 ? Math.round(((selectedProduct.price - selectedProduct.cost) / selectedProduct.price) * 100) : '—' }}%
                </span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Giá trị tồn</span>
                <span class="detail-value font-mono">{{ (selectedProduct.cost * selectedProduct.stock_quantity).toLocaleString('vi-VN') }} ₫</span>
              </div>
            </div>

            <!-- Recent movements for this product -->
            <div class="mt-6">
              <h5 class="text-sm font-semibold text-[var(--c-on-surface)] mb-2">Lịch sử gần đây</h5>
              <div class="space-y-2">
                <div
                  v-for="mv in movements.filter(m => m.product_id === selectedProduct!.id).slice(0, 5)"
                  :key="mv.id"
                  class="flex items-center gap-3 p-2 rounded-lg bg-[var(--c-surface-container)]/50 text-xs"
                >
                  <span :class="['inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium', movementTypeLabel(mv.type).color]">
                    <span class="material-symbols-outlined text-[12px]">{{ movementTypeLabel(mv.type).icon }}</span>
                    {{ movementTypeLabel(mv.type).label }}
                  </span>
                  <span class="font-mono" :class="mv.type === 'in' || mv.type === 'return' ? 'text-green-600' : 'text-[var(--c-error)]'">
                    {{ mv.type === 'in' || mv.type === 'return' ? '+' : '-' }}{{ Math.abs(mv.quantity) }}
                  </span>
                  <span class="text-[var(--c-on-surface-variant)]">{{ mv.note }}</span>
                  <span class="text-[var(--c-on-surface-variant)]/50 ml-auto font-mono">{{ formatDateTime(mv.created_at) }}</span>
                </div>
                <div v-if="movements.filter(m => m.product_id === selectedProduct!.id).length === 0" class="text-center text-[var(--c-on-surface-variant)] py-2 text-xs">
                  Chưa có lịch sử nhập/xuất
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="inv-btn-secondary" @click="showProductDetailModal = false">Đóng</button>
            <button class="admin-btn-primary" @click="showProductDetailModal = false; openStockIn(selectedProduct!)">
              <span class="material-symbols-outlined text-[18px]">add_circle</span>
              Nhập Hàng
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.inventory-view {
  font-family: 'Inter', sans-serif;
}

/* ── Stat Cards ── */
.stat-card {
  background: var(--c-surface-container, #edeeef);
  border-radius: 1rem;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 10rem;
  position: relative;
  overflow: hidden;
  transition: transform 0.3s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.stat-card:hover { transform: translateY(-4px); }
.stat-card--warning {
  background: color-mix(in srgb, var(--c-error-container, #ffdad6) 30%, transparent);
  border: 1px solid color-mix(in srgb, var(--c-error, #ba1a1a) 20%, transparent);
}
.stat-label {
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  color: var(--c-on-surface-variant, #4f4446);
  text-transform: uppercase;
}
.stat-label--error { color: var(--c-error, #ba1a1a); }
.stat-icon-wrap {
  width: 2.5rem; height: 2.5rem;
  border-radius: 9999px;
  background: var(--c-surface, #f8f9fa);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}
.stat-icon-wrap--error {
  background: color-mix(in srgb, var(--c-error, #ba1a1a) 10%, transparent);
}
.stat-value {
  font-size: 2.5rem; font-weight: 700; line-height: 1.2;
  color: var(--c-on-surface, #191c1d);
  display: block;
}
.stat-value--error { color: var(--c-error, #ba1a1a); }
.stat-value--lg { font-size: 2rem; }
.stat-sub {
  font-size: 0.875rem;
  color: var(--c-on-surface-variant, #4f4446);
}
.stat-sub--error { color: color-mix(in srgb, var(--c-error, #ba1a1a) 80%, transparent); }
.stat-glow {
  position: absolute; bottom: -2rem; right: -2rem;
  width: 8rem; height: 8rem;
  background: color-mix(in srgb, var(--c-primary, #78555e) 5%, transparent);
  border-radius: 9999px; filter: blur(2rem);
  transition: background 0.5s ease;
}
.group:hover .stat-glow { background: color-mix(in srgb, var(--c-primary, #78555e) 10%, transparent); }
.stat-glow--error {
  background: color-mix(in srgb, var(--c-error, #ba1a1a) 5%, transparent);
}
.group:hover .stat-glow--error { background: color-mix(in srgb, var(--c-error, #ba1a1a) 10%, transparent); }

/* ── Tabs ── */
.tab-btn {
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  color: var(--c-on-surface-variant, #4f4446);
  padding-bottom: 0.75rem;
  transition: color 0.2s;
  cursor: pointer;
  background: none; border: none;
}
.tab-btn:hover { color: var(--c-on-surface, #191c1d); }
.tab-btn--active {
  color: var(--c-primary, #78555e);
  border-bottom: 2px solid var(--c-primary, #78555e);
}

/* ── Table ── */
.inv-table-wrap {
  background: var(--c-surface, #f8f9fa);
  border-radius: 1rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  border: 1px solid color-mix(in srgb, var(--c-outline-variant, #d3c3c5) 20%, transparent);
  overflow: hidden;
}
.inv-thead-row {
  background: var(--c-surface-container-low, #f3f4f5);
  color: var(--c-on-surface-variant, #4f4446);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  border-bottom: 1px solid color-mix(in srgb, var(--c-outline-variant, #d3c3c5) 20%, transparent);
}
.inv-th { padding: 0.875rem 1rem; font-weight: 600; user-select: none; }
.inv-row {
  border-bottom: 1px solid color-mix(in srgb, var(--c-outline-variant, #d3c3c5) 10%, transparent);
  transition: background 0.15s;
  cursor: pointer;
}
.inv-row:hover {
  background: color-mix(in srgb, var(--c-surface-container, #edeeef) 30%, transparent);
}
.inv-row:last-child { border-bottom: none; }

/* ── Inputs ── */
.inv-select {
  padding: 0.375rem 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid var(--c-outline-variant, #d3c3c5);
  background: transparent;
  font-size: 0.8125rem;
  color: var(--c-on-surface, #191c1d);
  transition: border-color 0.2s;
  cursor: pointer;
  outline: none;
}
.inv-select:focus { border-color: var(--c-primary, #78555e); }
.inv-search-input {
  padding: 0.375rem 0.75rem 0.375rem 2.25rem;
  border-radius: 0.5rem;
  border: 1px solid var(--c-outline-variant, #d3c3c5);
  background: transparent;
  font-size: 0.875rem;
  color: var(--c-on-surface, #191c1d);
  width: 16rem;
  transition: border-color 0.2s;
  outline: none;
}
.inv-search-input::placeholder { color: color-mix(in srgb, var(--c-on-surface-variant, #4f4446) 50%, transparent); }
.inv-search-input:focus { border-color: var(--c-primary, #78555e); }

.inv-icon-btn {
  width: 2.25rem; height: 2.25rem;
  display: flex; align-items: center; justify-content: center;
  border-radius: 0.5rem;
  border: 1px solid var(--c-outline-variant, #d3c3c5);
  background: transparent;
  color: var(--c-on-surface-variant, #4f4446);
  cursor: pointer;
  transition: all 0.2s;
}
.inv-icon-btn:hover {
  background: var(--c-primary-container, #ffd1dc);
  color: var(--c-on-primary-container, #7a5761);
  border-color: var(--c-primary-container, #ffd1dc);
}

.inv-input {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid var(--c-outline-variant, #d3c3c5);
  background: transparent;
  font-size: 0.875rem;
  color: var(--c-on-surface, #191c1d);
  transition: border-color 0.2s;
  outline: none;
}
.inv-input:focus { border-color: var(--c-primary, #78555e); }
.inv-input::placeholder { color: color-mix(in srgb, var(--c-on-surface-variant, #4f4446) 50%, transparent); }

.inv-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  color: var(--c-on-surface-variant, #4f4446);
  margin-bottom: 0.375rem;
}

/* ── Action Buttons ── */
.inv-action-btn {
  width: 2rem; height: 2rem;
  border-radius: 9999px;
  display: flex; align-items: center; justify-content: center;
  background: none; border: none; cursor: pointer;
  color: var(--c-on-surface-variant, #4f4446);
  transition: all 0.2s;
}
.inv-action-btn:hover {
  background: var(--c-surface-container-high, #e7e8e9);
}
.inv-action-btn--primary {
  color: var(--c-primary, #78555e);
}
.inv-action-btn--primary:hover {
  background: var(--c-primary-container, #ffd1dc);
}
.inv-action-btn--danger {
  color: var(--c-error, #ba1a1a);
}
.inv-action-btn--danger:hover {
  background: var(--c-error-container, #ffdad6);
}

/* ── Urgent Cards ── */
.inv-urgent-card {
  background: var(--c-surface, #f8f9fa);
  border-radius: 0.75rem;
  padding: 0.75rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  border-left: 4px solid color-mix(in srgb, var(--c-error, #ba1a1a) 50%, transparent);
  display: flex; gap: 0.75rem; align-items: center;
  cursor: pointer;
  transition: background 0.2s;
}
.inv-urgent-card:hover { background: var(--c-surface-container, #edeeef); }
.inv-urgent-card--critical {
  border-left-color: var(--c-error, #ba1a1a);
  position: relative; overflow: hidden;
}

/* ── Sidebar Cards ── */
.inv-audit-card {
  padding: 1.5rem;
  border-radius: 1rem;
  background: var(--c-surface-container-high, #e7e8e9);
  border: 1px solid color-mix(in srgb, var(--c-outline-variant, #d3c3c5) 30%, transparent);
  text-align: center;
  position: relative; overflow: hidden;
}

.inv-quick-stats {
  padding: 1.25rem;
  border-radius: 1rem;
  background: var(--c-surface-container, #edeeef);
  border: 1px solid color-mix(in srgb, var(--c-outline-variant, #d3c3c5) 20%, transparent);
}

/* ── Detail Items ── */
.detail-item {
  padding: 0.75rem;
  background: var(--c-surface-container, #edeeef);
  border-radius: 0.5rem;
}
.detail-label {
  display: block;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  color: var(--c-on-surface-variant, #4f4446);
  margin-bottom: 0.25rem;
  text-transform: uppercase;
}
.detail-value {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--c-on-surface, #191c1d);
}

/* ── Buttons ── */
.admin-btn-primary {
  background: var(--c-primary, #78555e);
  color: var(--c-on-primary, #fff);
  padding: 0.625rem 1.5rem;
  border-radius: 0.75rem;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  transition: all 0.3s;
  display: flex; align-items: center; gap: 0.5rem;
  border: none; cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
.admin-btn-primary:hover { opacity: 0.9; }
.inv-btn-secondary {
  padding: 0.5rem 1.25rem;
  border-radius: 0.5rem;
  border: 1px solid var(--c-outline-variant, #d3c3c5);
  background: transparent;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--c-on-surface-variant, #4f4446);
  cursor: pointer;
  transition: all 0.2s;
}
.inv-btn-secondary:hover { background: var(--c-surface-container, #edeeef); }
.inv-btn-success {
  padding: 0.5rem 1.25rem;
  border-radius: 0.5rem;
  border: none;
  background: #16a34a;
  font-size: 0.8125rem;
  font-weight: 600;
  color: white;
  cursor: pointer;
  transition: all 0.2s;
  display: flex; align-items: center; gap: 0.375rem;
}
.inv-btn-success:hover { background: #15803d; }
.inv-btn-danger {
  padding: 0.5rem 1.25rem;
  border-radius: 0.5rem;
  border: none;
  background: var(--c-error, #ba1a1a);
  font-size: 0.8125rem;
  font-weight: 600;
  color: white;
  cursor: pointer;
  transition: all 0.2s;
  display: flex; align-items: center; gap: 0.375rem;
}
.inv-btn-danger:hover { opacity: 0.9; }

/* ── Modal ── */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(4px);
  z-index: 100;
  display: flex; align-items: center; justify-content: center;
  padding: 1rem;
}
.modal-content {
  background: var(--c-surface, #f8f9fa);
  border-radius: 1.25rem;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
  max-height: 85vh;
  overflow-y: auto;
  width: 100%;
}
.modal-sm { max-width: 28rem; }
.modal-md { max-width: 40rem; }
.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid color-mix(in srgb, var(--c-outline-variant, #d3c3c5) 20%, transparent);
  display: flex; align-items: center; justify-content: space-between;
}
.modal-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--c-on-surface, #191c1d);
  display: flex; align-items: center; gap: 0.5rem;
}
.modal-close {
  width: 2rem; height: 2rem;
  border-radius: 0.5rem;
  display: flex; align-items: center; justify-content: center;
  background: none; border: none; cursor: pointer;
  color: var(--c-on-surface-variant, #4f4446);
  transition: background 0.2s;
}
.modal-close:hover { background: var(--c-surface-container-high, #e7e8e9); }
.modal-body { padding: 1.5rem; }
.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid color-mix(in srgb, var(--c-outline-variant, #d3c3c5) 20%, transparent);
  display: flex; justify-content: flex-end; gap: 0.75rem;
}

/* ── Transitions ── */
.toast-enter-active { transition: all 0.3s ease; }
.toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from { transform: translateX(1rem); opacity: 0; }
.toast-leave-to { transform: translateX(1rem); opacity: 0; }

.modal-enter-active { transition: all 0.25s ease; }
.modal-leave-active { transition: all 0.2s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from .modal-content { transform: scale(0.95) translateY(10px); }
.modal-leave-to { opacity: 0; }
.modal-leave-to .modal-content { transform: scale(0.95); }

/* ── Responsive ── */
@media (max-width: 1024px) {
  .grid.lg\:grid-cols-4 { grid-template-columns: 1fr; }
  .stat-value { font-size: 2rem; }
  .inv-search-input { width: 10rem; }
}
</style>
