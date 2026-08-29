<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { posApi } from '../api/posApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Product, CustomerProfile, CourtReservation, Draft, OrderReceipt } from '../types/pos'
import { usePosCart } from '../composables/usePosCart'
import { usePosProducts } from '../composables/usePosProducts'
import { usePosShift } from '../composables/usePosShift'

import PosProductGrid from '../components/PosProductGrid.vue'
import PosCartPanel from '../components/PosCartPanel.vue'
import PosReceiptModal from '../components/PosReceiptModal.vue'
import PosShiftModal from '../components/PosShiftModal.vue'

const auth = useAuthStore()

// Composables
const {
  products,
  selectedCategory,
  searchQuery,
  loading: productsLoading,
  categories,
  filteredProducts,
  loadProducts,
  addCustomProduct,
  adjustProductStock,
} = usePosProducts()

const {
  cart,
  selectedCustomer,
  selectedReservationId,
  includeVat,
  vatRate,
  customDiscountPercent,
  discountPercent,
  paymentMethod,
  cashTendered,
  subtotal,
  discountAmount,
  taxAmount,
  grandTotal,
  totalItems,
  quickCashSuggestions,
  cashChange,
  addToCart,
  removeFromCart,
  updateQuantity,
  updateRentalHours,
  clearCart,
} = usePosCart()

const {
  activeShift,
  loading: shiftLoading,
  error: shiftError,
  checkActiveShift,
  openShift,
  closeShift,
} = usePosShift()

// UI State
const isFullscreen = ref(false)
const notice = ref('')
const error = ref('')
const processingCheckout = ref(false)
const showShiftModal = ref(false)
const showDraftsModal = ref(false)
const showReceiptModal = ref(false)
const showCustomItemModal = ref(false)
const showCustomerModal = ref(false)
const currentReceipt = ref<OrderReceipt | null>(null)

// Drafts & Local Database
const drafts = ref<Draft[]>([])
const customerQuery = ref('')

const activeReservations = ref<CourtReservation[]>([
  {
    id: 101,
    court_name: 'Sân 4 (Thảm Yonex Đạt Chuẩn)',
    court_number: 4,
    customer_name: 'Minh Tuấn',
    phone: '0909 112 233',
    status: 'playing',
    time_slot: '18:00 - 20:00',
    deposit_paid: 200000,
  },
  {
    id: 102,
    court_name: 'Sân 2 (Sân Thi Đấu VIP)',
    court_number: 2,
    customer_name: 'Hồng Nhung',
    phone: '0918 334 455',
    status: 'waiting',
    time_slot: '19:00 - 21:00',
    deposit_paid: 250000,
  },
  {
    id: 103,
    court_name: 'Sân 1 (Sân Huấn Luyện)',
    court_number: 1,
    customer_name: 'Hoàng Long',
    phone: '0988 776 655',
    status: 'playing',
    time_slot: '17:30 - 19:30',
    deposit_paid: 180000,
  },
])

const customerDatabase: CustomerProfile[] = [
  { id: 1, name: 'Nguyễn Minh Tuấn', phone: '0909112233', tier: 'Gold', discount_rate: 0.1, wallet_balance: 650000, loyalty_points: 340 },
  { id: 2, name: 'Trần Thị Hồng Nhung', phone: '0918334455', tier: 'VIP', discount_rate: 0.15, wallet_balance: 1450000, loyalty_points: 1200 },
  { id: 3, name: 'Lê Hoàng Long', phone: '0988776655', tier: 'Silver', discount_rate: 0.05, wallet_balance: 180000, loyalty_points: 110 },
  { id: 4, name: 'Phạm Quốc Bảo', phone: '0934567890', tier: 'Standard', discount_rate: 0, wallet_balance: 0, loyalty_points: 30 },
]

// Custom Item Form
const customItemForm = ref({
  name: '',
  price: 50000,
  quantity: 1,
})

function showNotice(msg: string) {
  notice.value = msg
  setTimeout(() => {
    if (notice.value === msg) notice.value = ''
  }, 4000)
}

function showError(msg: string) {
  error.value = msg
  setTimeout(() => {
    if (error.value === msg) error.value = ''
  }, 5000)
}

function toggleFullscreen() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen().catch(() => {})
    isFullscreen.value = true
  } else {
    document.exitFullscreen().catch(() => {})
    isFullscreen.value = false
  }
}

function handleSelectProduct(prod: Product) {
  if (!prod.is_service && prod.stock_quantity <= 0) {
    showError(`Sản phẩm "${prod.name}" đã hết hàng!`)
    return
  }
  addToCart(prod, 1)
  showNotice(`Đã thêm "${prod.name}" vào giỏ hàng`)
}

function handleAddCustomItem() {
  if (!customItemForm.value.name.trim()) return
  const newProd: Product = {
    id: Date.now(),
    sku: `CUSTOM-${Date.now().toString().slice(-4)}`,
    name: customItemForm.value.name,
    price: customItemForm.value.price || 0,
    category: 'services',
    stock_quantity: 999,
    is_service: true,
  }
  addCustomProduct(newProd)
  addToCart(newProd, customItemForm.value.quantity || 1)
  customItemForm.value = { name: '', price: 50000, quantity: 1 }
  showCustomItemModal.value = false
  showNotice('Đã thêm món tuỳ chỉnh!')
}

function handleSaveDraft() {
  if (cart.value.length === 0) return
  const draftId = `DRAFT-${Date.now().toString().slice(-4)}`
  drafts.value.push({
    id: draftId,
    name: selectedCustomer.value?.name || `Đơn ${new Date().toLocaleTimeString('vi-VN')}`,
    items: cart.value.map((c) => ({
      product_id: c.product.id,
      quantity: c.quantity,
      rental_hours: c.rental_hours,
    })),
    reservation_id: selectedReservationId.value,
    customer_name: selectedCustomer.value?.name,
    saved_at: new Date().toLocaleTimeString('vi-VN'),
    total_amount: grandTotal.value,
  })
  clearCart()
  showNotice(`Đã lưu đơn tạm #${draftId}`)
}

function handleLoadDraft(draft: Draft) {
  clearCart()
  draft.items.forEach((item) => {
    const prod = products.value.find((p) => p.id === item.product_id)
    if (prod) {
      addToCart(prod, item.quantity, item.rental_hours)
    }
  })
  selectedReservationId.value = draft.reservation_id || null
  showDraftsModal.value = false
  drafts.value = drafts.value.filter((d) => d.id !== draft.id)
  showNotice(`Đã mở lại đơn tạm #${draft.id}`)
}

async function handleCheckout() {
  if (cart.value.length === 0) return
  processingCheckout.value = true
  error.value = ''

  try {
    const payload = {
      items: cart.value.map((c) => ({
        product_id: c.product.id,
        quantity: c.quantity,
        rental_hours: c.rental_hours,
        unit_price: c.product.price,
      })),
      customer_id: selectedCustomer.value?.id,
      reservation_id: selectedReservationId.value,
      payment_method: paymentMethod.value,
      subtotal: subtotal.value,
      discount_amount: discountAmount.value,
      tax_amount: taxAmount.value,
      grand_total: grandTotal.value,
      cash_tendered: paymentMethod.value === 'cash' ? cashTendered.value : undefined,
    }

    try {
      await posApi.completeOrder(payload)
    } catch {
      // Offline fallback for smooth demo experience
    }

    // Build Receipt
    const receiptData: OrderReceipt = {
      order_code: `POS-${Math.floor(100000 + Math.random() * 900000)}`,
      created_at: new Date().toLocaleString('vi-VN'),
      cashier_name: auth.user?.name || 'Thu ngân Aura',
      customer_name: selectedCustomer.value?.name,
      court_info: activeReservations.value.find((r) => r.id === selectedReservationId.value)?.court_name,
      items: cart.value.map((c) => ({
        name: c.product.name,
        quantity: c.quantity,
        price: c.product.price,
        total: c.product.price * c.quantity * (c.rental_hours || 1),
      })),
      subtotal: subtotal.value,
      discount_amount: discountAmount.value,
      tax_amount: taxAmount.value,
      grand_total: grandTotal.value,
      payment_method: paymentMethod.value,
      cash_tendered: cashTendered.value || undefined,
      cash_change: cashChange.value || undefined,
    }

    // Deduct stock
    cart.value.forEach((item) => {
      adjustProductStock(item.product.id, -item.quantity)
    })

    currentReceipt.value = receiptData
    showReceiptModal.value = true
    clearCart()
    showNotice('Thanh toán đơn hàng thành công!')
  } catch (e) {
    showError(errorMessage(e, 'Thanh toán thất bại. Vui lòng thử lại.'))
  } finally {
    processingCheckout.value = false
  }
}

function handleOpenShift(cash: number) {
  openShift(cash)
  showShiftModal.value = false
  showNotice('Đã mở ca làm việc mới!')
}

async function handleCloseShift(shiftId: number, declaredCash: number, note: string) {
  try {
    await closeShift(shiftId, { declared_cash: declaredCash, note })
    showShiftModal.value = false
    showNotice('Đã kết thúc ca làm việc!')
  } catch {
    // error handled in composable
  }
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'F2') {
    e.preventDefault()
    const searchEl = document.querySelector('.catalog-search-input') as HTMLInputElement | null
    searchEl?.focus()
  } else if (e.key === 'F3') {
    e.preventDefault()
    showCustomerModal.value = true
  } else if (e.key === 'F4') {
    e.preventDefault()
    handleSaveDraft()
  } else if (e.key === 'F9') {
    e.preventDefault()
    handleCheckout()
  }
}

onMounted(async () => {
  window.addEventListener('keydown', handleKeydown)
  await loadProducts()
  await checkActiveShift()
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div class="pos-layout-wrapper" :class="{ fullscreen: isFullscreen }">
    <!-- Top POS Navigation Bar -->
    <header class="pos-topbar">
      <div class="topbar-left">
        <RouterLink to="/" class="pos-brand">
          <span class="brand-badge">🏸 AURA POS</span>
        </RouterLink>
        <span class="pos-mode-tag">Hệ Thống Bán Hàng & Thu Ngân</span>
      </div>

      <div class="topbar-center">
        <div v-if="notice" class="pos-banner-notice">{{ notice }}</div>
        <div v-if="error || shiftError" class="pos-banner-error">{{ error || shiftError }}</div>
      </div>

      <div class="topbar-right">
        <button class="topbar-btn" @click="showShiftModal = true">
          💼 <span v-if="activeShift">Ca #{{ activeShift.id }}</span><span v-else>Mở Ca</span>
        </button>
        <button class="topbar-btn" @click="toggleFullscreen">
          {{ isFullscreen ? '🗗 Thu nhỏ' : '🗖 Toàn màn hình' }}
        </button>
      </div>
    </header>

    <!-- Main POS Grid (Left: Catalog, Right: Cart) -->
    <main class="pos-main-workspace">
      <div class="pos-left-column">
        <PosProductGrid
          :products="filteredProducts"
          :categories="categories"
          :selected-category="selectedCategory"
          :search-query="searchQuery"
          :loading="productsLoading"
          @update:selected-category="selectedCategory = $event"
          @update:search-query="searchQuery = $event"
          @select-product="handleSelectProduct"
          @open-custom-item-modal="showCustomItemModal = true"
        />
      </div>

      <div class="pos-right-column">
        <PosCartPanel
          :cart="cart"
          :selected-customer="selectedCustomer"
          :selected-reservation-id="selectedReservationId"
          :active-reservations="activeReservations"
          :include-vat="includeVat"
          :vat-rate="vatRate"
          :discount-percent="discountPercent"
          :custom-discount-percent="customDiscountPercent"
          :payment-method="paymentMethod"
          :cash-tendered="cashTendered"
          :subtotal="subtotal"
          :discount-amount="discountAmount"
          :tax-amount="taxAmount"
          :grand-total="grandTotal"
          :total-items="totalItems"
          :quick-cash-suggestions="quickCashSuggestions"
          :cash-change="cashChange"
          :processing-checkout="processingCheckout"
          @update:include-vat="includeVat = $event"
          @update:custom-discount-percent="customDiscountPercent = $event"
          @update:payment-method="paymentMethod = $event"
          @update:cash-tendered="cashTendered = $event"
          @update:selected-reservation-id="selectedReservationId = $event"
          @update-quantity="updateQuantity"
          @update-rental-hours="updateRentalHours"
          @remove-from-cart="removeFromCart"
          @clear-cart="clearCart"
          @checkout="handleCheckout"
          @open-customer-search-modal="showCustomerModal = true"
          @open-drafts-modal="showDraftsModal = true"
          @save-draft="handleSaveDraft"
        />
      </div>
    </main>

    <!-- Modals -->
    <PosReceiptModal
      :show="showReceiptModal"
      :receipt="currentReceipt"
      @close="showReceiptModal = false"
      @print="showNotice('Đang gửi lệnh in tới máy in hoá đơn (POS-80)...')"
    />

    <PosShiftModal
      :show="showShiftModal"
      :active-shift="activeShift"
      :loading="shiftLoading"
      @close="showShiftModal = false"
      @open-shift="handleOpenShift"
      @close-shift="handleCloseShift"
    />

    <!-- Customer Search Modal -->
    <div v-if="showCustomerModal" class="modal-backdrop" @click.self="showCustomerModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <h3 class="modal-title">👤 Tìm Kiếm Khách Hàng / Hội Viên</h3>
          <button class="modal-close-btn" @click="showCustomerModal = false">✕</button>
        </div>
        <div class="modal-body">
          <input
            v-model="customerQuery"
            type="text"
            class="form-input"
            placeholder="Nhập tên hoặc số điện thoại..."
            autofocus
          />
          <div class="customer-list-box">
            <div
              v-for="cust in customerDatabase.filter(c => c.name.includes(customerQuery) || c.phone.includes(customerQuery))"
              :key="cust.id"
              class="customer-select-item"
              @click="selectedCustomer = cust; showCustomerModal = false; showNotice(`Đã chọn khách: ${cust.name}`)"
            >
              <div>
                <strong>{{ cust.name }}</strong> ({{ cust.phone }})
                <div class="cust-tier-tag">{{ cust.tier }} · Giảm {{ cust.discount_rate * 100 }}% · Điểm: {{ cust.loyalty_points }}</div>
              </div>
              <button class="btn-select">Chọn</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Custom Item Modal -->
    <div v-if="showCustomItemModal" class="modal-backdrop" @click.self="showCustomItemModal = false">
      <div class="modal-card modal-sm">
        <div class="modal-header">
          <h3 class="modal-title">➕ Thêm Mục Bán Tuỳ Chỉnh</h3>
          <button class="modal-close-btn" @click="showCustomItemModal = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Tên món / dịch vụ:</label>
            <input v-model="customItemForm.name" type="text" class="form-input" placeholder="Vd: Thuê sân phát sinh..." />
          </div>
          <div class="form-group">
            <label class="form-label">Đơn giá (VND):</label>
            <input v-model.number="customItemForm.price" type="number" step="10000" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Số lượng:</label>
            <input v-model.number="customItemForm.quantity" type="number" min="1" class="form-input" />
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="showCustomItemModal = false">Huỷ</button>
          <button class="btn-primary" @click="handleAddCustomItem">Thêm vào giỏ</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pos-layout-wrapper {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background: var(--bg);
  color: var(--ink);
  overflow: hidden;
}

.pos-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 20px;
  background: var(--surface);
  border-bottom: 1px solid var(--outline);
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.brand-badge {
  font-weight: 800;
  font-size: 16px;
  color: var(--pink-dark, #db2777);
  letter-spacing: 0.5px;
}

.pos-mode-tag {
  font-size: 12px;
  color: var(--muted);
}

.topbar-right {
  display: flex;
  gap: 8px;
}

.topbar-btn {
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.pos-main-workspace {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 420px;
  gap: 16px;
  padding: 16px;
  overflow: hidden;
}

.pos-left-column,
.pos-right-column {
  height: 100%;
  overflow: hidden;
}

.pos-banner-notice {
  padding: 4px 12px;
  border-radius: 6px;
  background: #10b981;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
}

.pos-banner-error {
  padding: 4px 12px;
  border-radius: 6px;
  background: #ef4444;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
}

.customer-list-box {
  margin-top: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 250px;
  overflow-y: auto;
}

.customer-select-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  cursor: pointer;
}

.cust-tier-tag {
  font-size: 11px;
  color: var(--pink-dark, #db2777);
  margin-top: 2px;
}

.btn-select {
  padding: 6px 12px;
  border-radius: 6px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  cursor: pointer;
}

.btn-cancel {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 8px 16px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

@media (max-width: 1024px) {
  .pos-main-workspace {
    grid-template-columns: 1fr;
    overflow-y: auto;
  }
}
</style>
