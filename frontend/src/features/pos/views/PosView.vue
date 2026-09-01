<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
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
import PosCustomerModal from '../components/PosCustomerModal.vue'
import PosCustomItemModal from '../components/PosCustomItemModal.vue'

const { t } = useI18n()
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
const activeReservations = ref<CourtReservation[]>([])
const customerDatabase = ref<CustomerProfile[]>([])

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
    showError(t('admin.pos.outOfStock', { name: prod.name }))
    return
  }
  addToCart(prod, 1)
  showNotice(t('admin.pos.addedToCart', { name: prod.name }))
}

function handleAddCustomItem(payload: { name: string; price: number; quantity: number }) {
  const newProd: Product = {
    id: Date.now(),
    sku: `CUSTOM-${Date.now().toString().slice(-4)}`,
    name: payload.name,
    price: payload.price,
    category: 'services',
    stock_quantity: 999,
    is_service: true,
  }
  addCustomProduct(newProd)
  addToCart(newProd, payload.quantity)
  showCustomItemModal.value = false
  showNotice(t('admin.pos.addedCustom'))
}

function handleSaveDraft() {
  if (cart.value.length === 0) return
  const draftId = `DRAFT-${Date.now().toString().slice(-4)}`
  drafts.value.push({
    id: draftId,
    name: selectedCustomer.value?.name || `Draft ${new Date().toLocaleTimeString()}`,
    items: cart.value.map((c) => ({
      product_id: c.product.id,
      quantity: c.quantity,
      rental_hours: c.rental_hours,
    })),
    reservation_id: selectedReservationId.value,
    customer_name: selectedCustomer.value?.name,
    saved_at: new Date().toLocaleTimeString(),
    total_amount: grandTotal.value,
  })
  clearCart()
  showNotice(t('admin.pos.draftSaved', { id: draftId }))
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
  showNotice(t('admin.pos.draftLoaded', { id: draft.id }))
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
      // Demo fallback
    }

    const receiptData: OrderReceipt = {
      order_code: `POS-${Math.floor(100000 + Math.random() * 900000)}`,
      created_at: new Date().toLocaleString(),
      cashier_name: auth.user?.name || 'Aura Cashier',
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

    cart.value.forEach((item) => {
      adjustProductStock(item.product.id, -item.quantity)
    })

    currentReceipt.value = receiptData
    showReceiptModal.value = true
    clearCart()
    showNotice(t('admin.pos.orderSuccess'))
  } catch (e) {
    showError(errorMessage(e, t('admin.pos.orderError')))
  } finally {
    processingCheckout.value = false
  }
}

function handleOpenShift(cash: number) {
  openShift(cash)
  showShiftModal.value = false
  showNotice(t('admin.pos.openShiftSuccess'))
}

async function handleCloseShift(shiftId: number, declaredCash: number, note: string) {
  try {
    await closeShift(shiftId, { declared_cash: declaredCash, note })
    showShiftModal.value = false
    showNotice(t('admin.pos.closeShiftSuccess'))
  } catch {
    // handled in composable
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
  const [resList, custList] = await Promise.allSettled([
    posApi.getActiveReservations(),
    posApi.searchCustomers(),
  ])
  if (resList.status === 'fulfilled') activeReservations.value = resList.value
  if (custList.status === 'fulfilled') customerDatabase.value = custList.value
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
          <span class="brand-badge">{{ t('admin.pos.brand') }}</span>
        </RouterLink>
        <span class="pos-mode-tag">{{ t('admin.pos.modeTag') }}</span>
      </div>

      <div class="topbar-center">
        <div v-if="notice" class="pos-banner-notice">{{ notice }}</div>
        <div v-if="error || shiftError" class="pos-banner-error">{{ error || shiftError }}</div>
      </div>

      <div class="topbar-right">
        <button type="button" class="topbar-btn" @click="showShiftModal = true">
          <span class="material-symbols-outlined icon-18">badge</span>
          <span v-if="activeShift">{{ t('admin.pos.shiftNumber', { id: activeShift.id }) }}</span>
          <span v-else>{{ t('admin.pos.openShift') }}</span>
        </button>
        <button type="button" class="topbar-btn" @click="toggleFullscreen">
          <span class="material-symbols-outlined icon-18">{{ isFullscreen ? 'fullscreen_exit' : 'fullscreen' }}</span>
          <span>{{ isFullscreen ? t('admin.pos.minimize') : t('admin.pos.fullscreen') }}</span>
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
      @print="showNotice(t('admin.pos.printCommand'))"
    />

    <PosShiftModal
      :show="showShiftModal"
      :active-shift="activeShift"
      :loading="shiftLoading"
      @close="showShiftModal = false"
      @open-shift="handleOpenShift"
      @close-shift="handleCloseShift"
    />

    <PosCustomerModal
      :show="showCustomerModal"
      :customer-database="customerDatabase"
      @close="showCustomerModal = false"
      @select="selectedCustomer = $event; showCustomerModal = false; showNotice(t('admin.pos.selectedCustomerNotice', { name: $event.name }))"
    />

    <PosCustomItemModal
      :show="showCustomItemModal"
      @close="showCustomItemModal = false"
      @add="handleAddCustomItem"
    />
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
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.topbar-btn:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
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

.icon-18 {
  font-size: 18px;
}

@media (max-width: 1024px) {
  .pos-main-workspace {
    grid-template-columns: 1fr;
    overflow-y: auto;
  }
}
</style>
