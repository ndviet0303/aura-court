<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue'
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
import PosQrModal from '../components/PosQrModal.vue'
import { AuraButton } from '@/shared/components'
import { useTenantCapabilities } from '@/features/admin/composables/useTenantCapabilities'

const {
  isEnabled,
  isPosEnabled,
  isPosDisabled,
  fetchFailed: capabilityFetchFailed,
  error: capabilityError,
  fetchCapabilities,
} = useTenantCapabilities()
const { t, d } = useI18n()
const auth = useAuthStore()

const {
  products,
  selectedCategory,
  searchQuery,
  loading: productsLoading,
  error: productsError,
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
  getCheckoutKey,
  resetCheckoutKey,
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
const showQrModal = ref(false)
const pendingQrOrder = ref<any | null>(null)
const showCustomItemModal = ref(false)
const showCustomerModal = ref(false)
const currentReceipt = ref<OrderReceipt | null>(null)
const drafts = ref<Draft[]>([])
const activeReservations = ref<CourtReservation[]>([])
const customerDatabase = ref<CustomerProfile[]>([])
const currentCourtTab = ref<any>(null)

async function fetchCurrentCourtTab(resId: number | null) {
  if (!resId) {
    currentCourtTab.value = null
    return
  }
  try {
    const res = await posApi.getTab(resId)
    currentCourtTab.value = res?.tab || null
  } catch {
    currentCourtTab.value = null
  }
}

watch(selectedReservationId, (val) => {
  fetchCurrentCourtTab(val)
})

async function handleSettleTab(tabId: number) {
  if (!currentCourtTab.value) return
  processingCheckout.value = true
  try {
    const res = await posApi.settleTab(tabId, {
      // Direct-owner cohorts settle tabs in cash only; counter QR is rung up as a POS order.
      payment_method:
        paymentMethod.value === 'court_tab' || isEnabled('direct_owner_payments')
          ? 'cash'
          : (paymentMethod.value as any),
      cash_tendered: paymentMethod.value === 'cash' ? cashTendered.value : undefined,
      customer_id: selectedCustomer.value?.id,
    })
    showNotice(t('admin.pos.tabSettled') || 'Tab sân đã được thanh toán thành công.')
    currentCourtTab.value = null
    await Promise.allSettled([loadProducts(), checkActiveShift()])

    const serverReceipt = res?.receipt || res?.order?.receipt
    if (serverReceipt) {
      currentReceipt.value = {
        order_code: serverReceipt.order_code || `POS-${res?.order?.id || Date.now()}`,
        created_at: serverReceipt.created_at
          ? d(new Date(serverReceipt.created_at), 'datetime')
          : d(new Date(), 'datetime'),
        cashier_name: serverReceipt.cashier_name || auth.user?.name || t('admin.pos.tblCashier'),
        customer_name: serverReceipt.customer_name || selectedCustomer.value?.name,
        court_info: serverReceipt.court_info,
        items: Array.isArray(serverReceipt.items)
          ? serverReceipt.items.map((i: any) => ({
              name: i.name,
              quantity: i.quantity,
              price: i.price ?? i.unit_price ?? 0,
              total: i.total ?? i.line_total ?? 0,
            }))
          : [],
        subtotal: serverReceipt.subtotal ?? 0,
        discount_amount: 0,
        tax_amount: 0,
        grand_total: serverReceipt.grand_total ?? 0,
        payment_method: serverReceipt.payment_method || 'cash',
        cash_tendered: serverReceipt.cash_tendered,
        change_due: serverReceipt.change_due,
      }
      showReceiptModal.value = true
    }
  } catch (err: any) {
    showError(err?.message || 'Lỗi khi thanh toán tab sân.')
  } finally {
    processingCheckout.value = false
  }
}

function showNotice(msg: string) {
  error.value = ''
  notice.value = msg
  setTimeout(() => {
    if (notice.value === msg) notice.value = ''
  }, 4000)
}

function selectCustomer(customer: CustomerProfile) {
  selectedCustomer.value = customer
  showCustomerModal.value = false
  showNotice(t('admin.pos.selectedCustomerNotice', { name: customer.name }))
}

function showError(msg: string) {
  notice.value = ''
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

function handleAddCustomItem(payload: {
  name: string
  price: number
  quantity: number
  vat_rate: number
}) {
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
    name: selectedCustomer.value?.name || `Draft ${d(new Date(), 'time')}`,
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
    saved_at: d(new Date(), 'time'),
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
  if (!isPosEnabled.value) {
    showError(t('admin.pos.disabledNotice') || 'Tính năng POS chưa được kích hoạt cho tổ chức này.')
    return
  }
  processingCheckout.value = true
  error.value = ''

  if (paymentMethod.value === 'court_tab') {
    if (!selectedReservationId.value) {
      showError(
        t('admin.pos.selectReservationFirst') || 'Vui lòng chọn lịch đặt sân để ghi vào tab.',
      )
      processingCheckout.value = false
      return
    }

    try {
      const tabPayload = {
        reservation_id: selectedReservationId.value,
        items: cart.value.map((c) => ({
          product_id: c.product.id,
          quantity: c.quantity,
        })),
      }

      await posApi.addItemToTab(tabPayload)
      showNotice(
        t('admin.pos.tabItemsAdded') ||
          'Đã ghi nhận đồ dùng vào tab sân và giữ chỗ tồn kho thành công.',
      )
      clearCart()
      resetCheckoutKey()
      await Promise.allSettled([loadProducts(), fetchCurrentCourtTab(selectedReservationId.value)])
    } catch (err: any) {
      showError(err?.message || 'Không thể ghi vào tab sân.')
    } finally {
      processingCheckout.value = false
    }
    return
  }

  try {
    const payload = {
      items: cart.value.map((c) => ({
        product_id: c.product.id,
        quantity: c.quantity,
        rental_duration_hours: c.rental_hours,
        rental_hours: c.rental_hours,
        vat_rate: c.vat_rate,
      })),
      customer_id: selectedCustomer.value?.id,
      reservation_id: selectedReservationId.value,
      payment_method: paymentMethod.value as 'cash' | 'qr' | 'wallet',
      discount_amount: discountAmount.value,
      cash_tendered: paymentMethod.value === 'cash' ? cashTendered.value : undefined,
      payment_reference: undefined,
    }
    const stableKey = getCheckoutKey()
    const response = await posApi.completeOrder(payload, stableKey)
    const orderObj = response?.order || response

    if (orderObj?.status === 'pending_payment') {
      pendingQrOrder.value = { ...orderObj, payment_attempt: response?.payment_attempt }
      showQrModal.value = true
      showNotice(t('admin.pos.qrOrderWaiting'))
      watchPendingQr(orderObj.id)
      return
    }

    const serverReceipt = response?.receipt || response?.order?.receipt || response?.order
    const receiptData: OrderReceipt = serverReceipt
      ? {
          order_code: serverReceipt.order_code || `POS-${response?.order?.id || Date.now()}`,
          created_at: serverReceipt.created_at
            ? d(new Date(serverReceipt.created_at), 'datetime')
            : d(new Date(), 'datetime'),
          cashier_name: serverReceipt.cashier_name || auth.user?.name || t('admin.pos.tblCashier'),
          customer_name: serverReceipt.customer_name || selectedCustomer.value?.name,
          court_info:
            serverReceipt.court_info ||
            activeReservations.value.find((r) => r.id === selectedReservationId.value)?.court_name,
          items: Array.isArray(serverReceipt.items)
            ? serverReceipt.items.map((i: any) => ({
                name: i.name,
                quantity: i.quantity,
                price: i.price ?? i.unit_price ?? 0,
                total: i.total ?? i.line_total ?? (i.price ?? i.unit_price ?? 0) * i.quantity,
                vat_rate: i.vat_rate,
              }))
            : cart.value.map((c) => ({
                name: c.product.name,
                quantity: c.quantity,
                price: c.product.price,
                total: c.product.price * c.quantity * (c.rental_hours || 1),
                vat_rate: c.vat_rate,
              })),
          subtotal: serverReceipt.subtotal ?? subtotal.value,
          discount_amount: serverReceipt.discount_amount ?? discountAmount.value,
          tax_amount: serverReceipt.tax_amount ?? taxAmount.value,
          vat_breakdown: serverReceipt.vat_breakdown ?? vatBreakdown.value,
          grand_total: serverReceipt.grand_total ?? serverReceipt.total_amount ?? grandTotal.value,
          payment_method: (serverReceipt.payment_method || paymentMethod.value) as any,
          cash_tendered:
            serverReceipt.cash_tendered ??
            (paymentMethod.value === 'cash' ? cashTendered.value : undefined),
          change_due: serverReceipt.change_due ?? (cashChange.value || undefined),
        }
      : {
          order_code: `POS-${Date.now()}`,
          created_at: d(new Date(), 'datetime'),
          cashier_name: auth.user?.name || t('admin.pos.tblCashier'),
          customer_name: selectedCustomer.value?.name,
          court_info: activeReservations.value.find((r) => r.id === selectedReservationId.value)
            ?.court_name,
          items: cart.value.map((c) => ({
            name: c.product.name,
            quantity: c.quantity,
            price: c.product.price,
            total: c.product.price * c.quantity * (c.rental_hours || 1),
            vat_rate: c.vat_rate,
          })),
          subtotal: subtotal.value,
          discount_amount: discountAmount.value,
          tax_amount: taxAmount.value,
          vat_breakdown: vatBreakdown.value,
          grand_total: grandTotal.value,
          payment_method: paymentMethod.value,
          cash_tendered: paymentMethod.value === 'cash' ? cashTendered.value : undefined,
          change_due: cashChange.value || undefined,
        }

    cart.value.forEach((item) => {
      adjustProductStock(item.product.id, -item.quantity)
    })

    currentReceipt.value = receiptData
    showReceiptModal.value = true
    clearCart()
    resetCheckoutKey()
    showNotice(t('admin.pos.orderSuccess'))
  } catch (e) {
    // Retain cart on rejection! Do not clear cart!
    showError(errorMessage(e, t('admin.pos.orderError')))
  } finally {
    processingCheckout.value = false
  }
}

let qrPoll: ReturnType<typeof setInterval> | undefined
function stopQrPoll() {
  clearInterval(qrPoll)
  qrPoll = undefined
}

// Only the venue owner's verified bank receipt completes a QR order; poll it until it lands or lapses.
function watchPendingQr(orderId: number) {
  stopQrPoll()
  qrPoll = setInterval(async () => {
    const response = await posApi.getOrder(orderId).catch(() => null)
    if (response?.order?.status === 'completed') finishQrOrder(response)
    if (response?.order?.status === 'cancelled') {
      stopQrPoll()
      showQrModal.value = false
      pendingQrOrder.value = null
      showError(t('admin.pos.qrExpiredNotice'))
    }
  }, 3000)
}

function finishQrOrder(response: { receipt: OrderReceipt }) {
  stopQrPoll()
  cart.value.forEach((item) => adjustProductStock(item.product.id, -item.quantity))
  currentReceipt.value = response.receipt
  showQrModal.value = false
  pendingQrOrder.value = null
  showReceiptModal.value = true
  clearCart()
  resetCheckoutKey()
  showNotice(t('admin.pos.orderSuccess'))
}

async function handleCancelQr() {
  if (!pendingQrOrder.value) return
  try {
    await posApi.cancelQrPayment(pendingQrOrder.value.id, { reason: 'cashier_cancelled' })
    stopQrPoll()
    showQrModal.value = false
    pendingQrOrder.value = null
    showNotice(t('admin.pos.qrCancelledNotice'))
  } catch (e) {
    showError(errorMessage(e, t('admin.pos.qrCancelFailed')))
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

async function initPosData() {
  const userAny = auth.user as any
  const activeOrgId =
    userAny?.capabilities?.active_organization_id ||
    userAny?.organizations?.[0]?.organization_id ||
    userAny?.organizations?.[0]?.id
  await fetchCapabilities(activeOrgId)

  if (capabilityFetchFailed.value) {
    showError(capabilityError.value || 'Không thể xác thực quyền hạn tính năng POS.')
    return
  }

  const [productsRes, shiftRes, resList, custList] = await Promise.allSettled([
    loadProducts(),
    isPosEnabled.value ? checkActiveShift() : Promise.resolve(null),
    posApi.getActiveReservations(),
    posApi.searchCustomers(),
  ])
  if (productsRes.status === 'rejected') {
    showError(productsError.value || 'Không thể tải danh mục sản phẩm POS.')
  }
  if (shiftRes.status === 'rejected') {
    showError(shiftError.value || 'Không thể kiểm tra trạng thái ca làm việc.')
  }
  if (resList.status === 'fulfilled') activeReservations.value = resList.value
  if (custList.status === 'fulfilled') customerDatabase.value = custList.value
}

onMounted(async () => {
  window.addEventListener('keydown', handleKeydown)
  await initPosData()
})
onUnmounted(() => {
  stopQrPoll()
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
    <div v-if="capabilityFetchFailed" class="pos-disabled-banner pos-error-banner" role="alert">
      <span class="material-symbols-outlined icon-24">cloud_off</span>
      <div class="banner-text">
        <strong>{{ t('admin.pos.capabilityFetchFailedTitle') || 'Lỗi kết nối quyền hạn' }}</strong>
        <p>
          {{
            capabilityError ||
            t('admin.pos.capabilityFetchFailedDesc') ||
            'Không thể xác thực quyền hạn POS. Vui lòng thử lại.'
          }}
        </p>
      </div>
      <AuraButton variant="secondary" size="sm" @click="initPosData">{{
        t('common.retry') || 'Thử lại'
      }}</AuraButton>
    </div>
    <div v-else-if="isPosDisabled" class="pos-disabled-banner" role="alert">
      <span class="material-symbols-outlined icon-24">lock</span>
      <div class="banner-text">
        <strong>{{ t('admin.pos.disabledTitle') }}</strong>
        <p>{{ t('admin.pos.disabledNotice') }}</p>
      </div>
    </div>
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
          :active-tab="currentCourtTab"
          @settle-tab="handleSettleTab"
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
          :disabled="!isPosEnabled"
          :hide-qr="!activeShift?.qr_available"
          :hide-wallet="isEnabled('direct_owner_payments')"
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

    <PosQrModal
      :show="showQrModal"
      :order="pendingQrOrder"
      :loading="processingCheckout"
      @close="showQrModal = false"
      @cancel="handleCancelQr"
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
      @select="selectCustomer"
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
.pos-disabled-banner {
  margin: 12px 16px 0;
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #ef4444;
  padding: 12px 16px;
  border-radius: 8px;
}

.pos-disabled-banner .banner-text strong {
  display: block;
  font-size: 14px;
}

.pos-disabled-banner .banner-text p {
  margin: 2px 0 0;
  font-size: 12px;
  color: #94a3b8;
}

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
