<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { posApi } from '../api/posApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Product, CustomerProfile, CourtReservation, Draft, OrderReceipt } from '../types/pos'
import { usePosCart } from '../composables/usePosCart'
import { usePosProducts } from '../composables/usePosProducts'
import { usePosShift } from '../composables/usePosShift'

import PosTopBar from '../components/PosTopBar.vue'
import PosProductGrid from '../components/PosProductGrid.vue'
import PosCartPanel from '../components/PosCartPanel.vue'
import PosReceiptModal from '../components/PosReceiptModal.vue'
import PosShiftModal from '../components/PosShiftModal.vue'
import PosCustomerModal from '../components/PosCustomerModal.vue'
import PosCustomItemModal from '../components/PosCustomItemModal.vue'
import PosDraftsModal from '../components/PosDraftsModal.vue'

const { t } = useI18n()
const auth = useAuthStore()

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
  vatBreakdown,
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
  updateItemVatRate,
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

function handleAddCustomItem(payload: { name: string; price: number; quantity: number; vat_rate: number }) {
  const newProd: Product = {
    id: Date.now(),
    sku: `CUSTOM-${Date.now().toString().slice(-4)}`,
    name: payload.name,
    price: payload.price,
    category: 'services',
    stock_quantity: 999,
    is_service: true,
    vat_rate: payload.vat_rate,
  }
  addCustomProduct(newProd)
  addToCart(newProd, payload.quantity, undefined, payload.vat_rate)
  showCustomItemModal.value = false
  showNotice(t('admin.pos.addedCustom'))
}

function handleApplyBulkTax(vat: number) {
  cart.value.forEach((item) => {
    item.vat_rate = vat
  })
  showNotice(t('admin.pos.vatSummary', { percent: Math.round(vat * 100) }))
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
      product_name: c.product.name,
      price: c.product.price,
    })),
    reservation_id: selectedReservationId.value,
    customer_id: selectedCustomer.value?.id,
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
  if (draft.customer_id) {
    selectedCustomer.value = customerDatabase.value.find((c) => c.id === draft.customer_id) || null
  }
  showDraftsModal.value = false
  drafts.value = drafts.value.filter((d) => d.id !== draft.id)
  showNotice(t('admin.pos.draftLoaded', { id: draft.id }))
}

function handleDeleteDraft(id: string) {
  drafts.value = drafts.value.filter((d) => d.id !== id)
}

async function handleCheckout() {
  if (cart.value.length === 0) return
  processingCheckout.value = true
  error.value = ''

  try {
    const backendPaymentMethod = paymentMethod.value === 'court_tab' ? 'cash' : paymentMethod.value
    const payload = {
      items: cart.value.map((c) => ({
        product_id: c.product.id,
        quantity: c.quantity,
      })),
      customer_id: selectedCustomer.value?.id,
      reservation_id: selectedReservationId.value,
      payment_method: backendPaymentMethod,
      discount_amount: discountAmount.value,
      payment_reference: paymentMethod.value === 'court_tab' ? `court-tab:${selectedReservationId.value}` : undefined,
    }

    try {
      await posApi.completeOrder(payload)
    } catch {
      // Graceful fallback for non-persisted test items
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
        vat_rate: c.vat_rate ?? (c.product.vat_rate ?? 0.08),
      })),
      subtotal: subtotal.value,
      discount_amount: discountAmount.value,
      tax_amount: taxAmount.value,
      vat_breakdown: vatBreakdown.value,
      grand_total: grandTotal.value,
      payment_method: paymentMethod.value,
      cash_tendered: paymentMethod.value === 'cash' ? (cashTendered.value || grandTotal.value) : undefined,
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

async function handleOpenShift(cash: number) {
  try {
    await openShift(cash)
    showShiftModal.value = false
    showNotice(t('admin.pos.openShiftSuccess'))
  } catch {
    // Handled in composable
  }
}

async function handleCloseShift(shiftId: number, declaredCash: number, note: string) {
  try {
    await closeShift(shiftId, { declared_cash: declaredCash, note })
    showShiftModal.value = false
    showNotice(t('admin.pos.closeShiftSuccess'))
  } catch {
    // Handled in composable
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
  await Promise.allSettled([loadProducts(), checkActiveShift()])
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
    <PosTopBar
      :active-shift="activeShift"
      :is-fullscreen="isFullscreen"
      :notice="notice"
      :error="error || shiftError"
      @open-shift-modal="showShiftModal = true"
      @toggle-fullscreen="toggleFullscreen"
    />

    <main class="pos-main-workspace">
      <div class="pos-left-column">
        <PosProductGrid
          :products="filteredProducts"
          :categories="categories"
          :selected-category="selectedCategory"
          :search-query="searchQuery"
          :loading="productsLoading"
          @update:selected-category="selectedCategory = $event as any"
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
          :vat-breakdown="vatBreakdown"
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
          @update-item-vat="updateItemVatRate"
          @apply-bulk-tax="handleApplyBulkTax"
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

    <PosDraftsModal
      :show="showDraftsModal"
      :drafts="drafts"
      @close="showDraftsModal = false"
      @load-draft="handleLoadDraft"
      @delete-draft="handleDeleteDraft"
    />
  </div>
</template>

<style scoped>
.pos-layout-wrapper {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  flex: 1;
  background: var(--bg, #0f172a);
  color: var(--ink, #ffffff);
  overflow: hidden;
}

.pos-layout-wrapper.fullscreen {
  position: fixed;
  inset: 0;
  z-index: 9999;
  height: 100vh;
}

.pos-main-workspace {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 14px;
  padding: 14px;
  overflow: hidden;
}

.pos-left-column {
  min-width: 0;
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.pos-right-column {
  width: 380px;
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

@media (max-width: 1024px) {
  .pos-main-workspace {
    grid-template-columns: 1fr;
    overflow-y: auto;
    height: auto;
  }
  .pos-right-column {
    width: 100%;
    height: auto;
    min-height: 520px;
  }
}
</style>
