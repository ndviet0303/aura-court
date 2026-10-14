<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { CartItem, CustomerProfile, CourtReservation, VatBreakdown } from '../types/pos'
import SelectDropdown from '@/shared/components/forms/SelectDropdown.vue'

const props = defineProps<{
  cart: CartItem[]
  selectedCustomer: CustomerProfile | null
  selectedReservationId: number | null
  activeReservations: CourtReservation[]
  vatBreakdown: VatBreakdown[]
  discountPercent: number
  customDiscountPercent: number
  paymentMethod: 'cash' | 'qr' | 'wallet' | 'court_tab'
  cashTendered: number
  subtotal: number
  discountAmount: number
  taxAmount: number
  grandTotal: number
  totalItems: number
  quickCashSuggestions: number[]
  cashChange: number
  processingCheckout?: boolean
  disabled?: boolean
  activeTab?: any
  hideQr?: boolean
  hideWallet?: boolean
}>()

const emit = defineEmits<{
  (e: 'updateItemVat', idx: number, val: number): void
  (e: 'applyBulkTax', val: number): void
  (e: 'update:customDiscountPercent', val: number): void
  (e: 'update:paymentMethod', val: 'cash' | 'qr' | 'wallet' | 'court_tab'): void
  (e: 'update:cashTendered', val: number): void
  (e: 'update:selectedReservationId', val: number | null): void
  (e: 'updateQuantity', idx: number, delta: number): void
  (e: 'updateRentalHours', idx: number, delta: number): void
  (e: 'removeFromCart', idx: number): void
  (e: 'clearCart'): void
  (e: 'checkout'): void
  (e: 'openCustomerSearchModal'): void
  (e: 'openDraftsModal'): void
  (e: 'saveDraft'): void
  (e: 'settleTab', tabId: number): void
}>()

const { t } = useI18n()
const tenders = computed(() =>
  (
    [
      ['cash', 'payments', 'admin.pos.payCash'],
      ['qr', 'qr_code_scanner', 'admin.pos.payQr'],
      ['wallet', 'account_balance_wallet', 'admin.pos.payWallet'],
      ['court_tab', 'sports_tennis', 'admin.pos.payCourtTab'],
    ] as const
  ).filter(([key]) => !(key === 'qr' && props.hideQr) && !(key === 'wallet' && props.hideWallet)),
)
const quickCashScrollRef = ref<HTMLElement | null>(null)

function cycleItemVat(idx: number, currentVat: number) {
  let next = 0.08
  if (Math.abs(currentVat - 0.08) < 0.001) next = 0.1
  else if (Math.abs(currentVat - 0.1) < 0.001) next = 0
  else if (currentVat === 0) next = 0.08
  emit('updateItemVat', idx, next)
}

function onQuickCashWheel(e: WheelEvent) {
  if (!quickCashScrollRef.value) return
  if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
    quickCashScrollRef.value.scrollLeft += e.deltaY
  }
}

function formatMoney(val: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}

const courtOptions = computed(() => {
  const defaultOpt = {
    value: '',
    label: t('admin.pos.attachToCourt'),
    sublabel: 'Không gắn sân',
    icon: 'sports_tennis',
  }
  const mapped = props.activeReservations.map((res) => ({
    value: res.id,
    label: res.court_name,
    sublabel: `${res.customer_name} • ${res.time_slot}`,
    icon: 'sports_tennis',
  }))
  return [defaultOpt, ...mapped]
})

function handleCourtChange(val: string | number) {
  emit('update:selectedReservationId', val ? Number(val) : null)
}
</script>

<template>
  <div class="pos-cart-panel">
    <!-- Cart Header -->
    <div class="cart-header">
      <div class="cart-title-row">
        <h3 class="cart-title">
          <span class="material-symbols-outlined icon-20">shopping_cart</span>
          <span>{{ t('admin.pos.currentOrder') }}</span>
          <span v-if="totalItems > 0" class="cart-count-badge">{{ totalItems }}</span>
        </h3>
        <button
          v-if="cart.length > 0"
          type="button"
          class="clear-cart-link"
          :title="t('admin.pos.clearCartConfirm')"
          @click="emit('clearCart')"
        >
          {{ t('admin.pos.clearCart') }}
        </button>
      </div>

      <!-- Customer & Court Tab Bar -->
      <div class="cart-meta-bar">
        <button
          type="button"
          class="customer-tag-btn"
          :class="{ selected: !!selectedCustomer }"
          @click="emit('openCustomerSearchModal')"
        >
          <span class="material-symbols-outlined cust-icon">person</span>
          <div v-if="selectedCustomer" class="cust-info">
            <span class="cust-label-prefix">{{ t('admin.pos.customer') }}:</span>
            <strong class="cust-name">{{ selectedCustomer.name }}</strong>
            <span class="cust-tier"
              >({{ selectedCustomer.tier }} -{{
                Math.round(selectedCustomer.discount_rate * 100)
              }}%)</span
            >
          </div>
          <div v-else class="cust-info">
            <span class="cust-placeholder">{{ t('admin.pos.walkInCustomer') }}</span>
            <span class="cust-hint">(F3 để tìm)</span>
          </div>
          <span class="material-symbols-outlined action-icon">search</span>
        </button>

        <div class="court-dropdown-wrap">
          <SelectDropdown
            :model-value="selectedReservationId || ''"
            :options="courtOptions"
            :placeholder="t('admin.pos.attachToCourt')"
            variant="bordered"
            @change="handleCourtChange"
          />
        </div>
      </div>

      <div v-if="activeTab && activeTab.total_amount > 0" class="active-tab-banner">
        <div class="tab-banner-left">
          <span class="material-symbols-outlined tab-icon">receipt_long</span>
          <div>
            <span class="tab-label"
              >{{ t('admin.pos.unpaidCourtTab') || 'Tab sân chưa trả:' }}
            </span>
            <strong class="tab-amount">{{ formatMoney(activeTab.total_amount) }}</strong>
            <span class="tab-count"> ({{ activeTab.items?.length || 0 }} món)</span>
          </div>
        </div>
        <button type="button" class="btn-settle-tab" @click="emit('settleTab', activeTab.id)">
          {{ t('admin.pos.settleTabBtn') || 'Thanh toán Tab' }}
        </button>
      </div>
    </div>

    <!-- Cart Items Scroll Area -->
    <div class="cart-items-container">
      <div v-if="cart.length === 0" class="empty-cart-view">
        <span class="material-symbols-outlined empty-cart-icon">shopping_cart</span>
        <h4>{{ t('admin.pos.emptyCart') }}</h4>
        <p>{{ t('admin.pos.emptyCartSub') }}</p>
      </div>

      <div v-else class="cart-items-list">
        <div v-for="(item, idx) in cart" :key="idx" class="cart-item-row">
          <div class="item-primary-info">
            <div class="item-name-wrap">
              <h5 class="item-name">{{ item.product.name }}</h5>
              <button
                type="button"
                class="item-vat-badge"
                :class="`vat-${Math.round((item.vat_rate ?? 0.08) * 100)}`"
                :title="`Thuế VAT: ${Math.round((item.vat_rate ?? 0.08) * 100)}% (Bấm để đổi)`"
                @click="cycleItemVat(idx, item.vat_rate ?? 0.08)"
              >
                {{ Math.round((item.vat_rate ?? 0.08) * 100) }}% VAT
              </button>
            </div>
            <span class="item-unit-price">{{ formatMoney(item.product.price) }}</span>
          </div>

          <!-- Controls: Quantity & Rental Hours -->
          <div class="item-controls-row">
            <div v-if="item.product.is_rental" class="rental-hours-pill">
              <span class="ctrl-label">{{ t('admin.pos.rentalHours') }}:</span>
              <button
                type="button"
                class="qty-btn"
                :disabled="(item.rental_hours || 1) <= 1"
                @click="emit('updateRentalHours', idx, -1)"
              >
                -
              </button>
              <span class="qty-num">{{ item.rental_hours || 1 }}h</span>
              <button type="button" class="qty-btn" @click="emit('updateRentalHours', idx, 1)">
                +
              </button>
            </div>

            <div class="item-qty-stepper">
              <button type="button" class="qty-btn" @click="emit('updateQuantity', idx, -1)">
                <span class="material-symbols-outlined icon-14" v-if="item.quantity === 1"
                  >delete</span
                >
                <span v-else>-</span>
              </button>
              <span class="qty-num">{{ item.quantity }}</span>
              <button type="button" class="qty-btn" @click="emit('updateQuantity', idx, 1)">
                +
              </button>
            </div>

            <strong class="item-line-total">
              {{ formatMoney(item.product.price * item.quantity * (item.rental_hours || 1)) }}
            </strong>
          </div>
        </div>
      </div>
    </div>

    <!-- Cart Summary & Payment -->
    <div class="cart-footer-area">
      <!-- Calculations Section -->
      <div class="calculations-box">
        <div class="calc-row">
          <span class="calc-label">{{ t('admin.pos.subtotal') }}</span>
          <span class="calc-val">{{ formatMoney(subtotal) }}</span>
        </div>

        <div class="discount-vat-controls">
          <div class="discount-input-wrap">
            <label class="ctrl-sublabel">{{ t('admin.pos.discountLabel') }}</label>
            <input
              :value="customDiscountPercent"
              type="number"
              min="0"
              max="100"
              class="discount-input"
              @input="
                emit(
                  'update:customDiscountPercent',
                  Number(($event.target as HTMLInputElement).value || 0),
                )
              "
            />
            <span class="unit-pct">%</span>
          </div>

          <div class="vat-rate-selector">
            <label class="ctrl-sublabel">{{ t('admin.pos.vatRateLabel') }}</label>
            <div class="vat-pill-group">
              <button
                type="button"
                class="vat-pill-btn"
                title="Đặt tất cả món thành 0% VAT"
                @click="emit('applyBulkTax', 0)"
              >
                0%
              </button>
              <button
                type="button"
                class="vat-pill-btn"
                title="Đặt tất cả món thành 8% VAT"
                @click="emit('applyBulkTax', 0.08)"
              >
                8%
              </button>
              <button
                type="button"
                class="vat-pill-btn"
                title="Đặt tất cả món thành 10% VAT"
                @click="emit('applyBulkTax', 0.1)"
              >
                10%
              </button>
            </div>
          </div>
        </div>

        <div v-if="discountAmount > 0" class="calc-row discount-row">
          <span class="calc-label">{{
            t('admin.pos.discountSummary', { percent: discountPercent })
          }}</span>
          <span class="calc-val text-green">-{{ formatMoney(discountAmount) }}</span>
        </div>

        <div v-for="b in vatBreakdown" :key="b.rate" class="calc-row vat-row">
          <span class="calc-label">{{ t('admin.pos.vatSummary', { percent: b.percent }) }}</span>
          <span class="calc-val">+{{ formatMoney(b.amount) }}</span>
        </div>

        <div class="calc-row grand-total-row">
          <span class="calc-label">{{ t('admin.pos.totalPayment') }}</span>
          <strong class="grand-total-val">{{ formatMoney(grandTotal) }}</strong>
        </div>
      </div>

      <!-- Payment Method Selector -->
      <div class="payment-methods-grid">
        <button
          v-for="[key, icon, label] in tenders"
          :key="key"
          type="button"
          class="pay-method-btn"
          :class="{ active: paymentMethod === key }"
          @click="emit('update:paymentMethod', key)"
        >
          <span class="material-symbols-outlined method-icon">{{ icon }}</span>
          <span class="method-text">{{ t(label) }}</span>
        </button>
      </div>

      <!-- Cash Tendered & Quick Suggestions (If Cash Selected) -->
      <div v-if="paymentMethod === 'cash'" class="cash-tendered-section">
        <div class="cash-input-row">
          <label class="cash-label">{{ t('admin.pos.cashTenderedLabel') }}</label>
          <input
            :value="cashTendered || ''"
            type="number"
            step="1000"
            class="cash-tendered-input"
            :placeholder="formatMoney(grandTotal)"
            @input="
              emit('update:cashTendered', Number(($event.target as HTMLInputElement).value || 0))
            "
          />
        </div>

        <div ref="quickCashScrollRef" class="quick-cash-chips" @wheel.passive="onQuickCashWheel">
          <button
            v-for="sug in quickCashSuggestions"
            :key="sug"
            type="button"
            class="cash-chip"
            @click="emit('update:cashTendered', sug)"
          >
            {{ formatMoney(sug) }}
          </button>
        </div>

        <div v-if="cashChange > 0" class="cash-change-alert">
          <span>{{ t('admin.pos.cashChangeLabel') }}</span>
          <strong class="change-amount">{{ formatMoney(cashChange) }}</strong>
        </div>
      </div>

      <!-- Action Buttons (Drafts, Checkout) -->
      <div class="cart-actions-row">
        <button
          type="button"
          class="draft-action-btn"
          :disabled="cart.length === 0"
          :title="t('admin.pos.saveDraft')"
          @click="emit('saveDraft')"
        >
          <span class="material-symbols-outlined icon-18">bookmark_add</span>
          <span>{{ t('admin.pos.saveDraft') }}</span>
        </button>

        <button
          type="button"
          class="draft-action-btn"
          :title="t('admin.pos.viewDrafts')"
          @click="emit('openDraftsModal')"
        >
          <span class="material-symbols-outlined icon-18">folder_open</span>
          <span>{{ t('admin.pos.viewDrafts') }}</span>
        </button>

        <button
          type="button"
          class="checkout-submit-btn"
          :disabled="cart.length === 0 || processingCheckout || disabled"
          @click="emit('checkout')"
        >
          <span class="material-symbols-outlined icon-20">check_circle</span>
          <span>{{
            processingCheckout
              ? t('admin.pos.checkoutProcessing')
              : paymentMethod === 'court_tab'
                ? t('admin.pos.recordToTab') || 'Ghi vào Tab Sân'
                : t('admin.pos.checkoutBtn')
          }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pos-cart-panel {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  background: var(--surface, #1e293b);
  border-radius: 16px;
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  overflow: hidden;
}

.cart-header {
  position: relative;
  z-index: 25;
  padding: 14px 16px;
  flex-shrink: 0;
  border-bottom: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  background: var(--surface, #1e293b);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.active-tab-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(245, 158, 11, 0.15);
  border: 1px solid rgba(245, 158, 11, 0.35);
  border-radius: 8px;
  padding: 8px 12px;
  margin-top: 4px;
}
.tab-banner-left {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}
.tab-icon {
  color: #f59e0b;
  font-size: 20px;
}
.tab-amount {
  color: #f59e0b;
  font-weight: 700;
}
.tab-count {
  color: var(--muted, #94a3b8);
  font-size: 12px;
}
.btn-settle-tab {
  background: #f59e0b;
  color: #0f172a;
  font-weight: 600;
  font-size: 12px;
  padding: 4px 10px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  transition: background 0.15s;
}
.btn-settle-tab:hover {
  background: #d97706;
}

.cart-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.cart-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ink, #ffffff);
}

.cart-count-badge {
  background: var(--primary, #10b981);
  color: #ffffff;
  padding: 2px 7px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
}

.clear-cart-link {
  background: transparent;
  border: none;
  color: var(--muted, #94a3b8);
  font-size: 12px;
  cursor: pointer;
  text-decoration: underline;
  padding: 0;
}

.clear-cart-link:hover {
  color: #ef4444;
}

.cart-meta-bar {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.customer-tag-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 8px;
  background: var(--surface-hover, rgba(255, 255, 255, 0.04));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  color: var(--ink, #ffffff);
  cursor: pointer;
  text-align: left;
  font-size: 13px;
  min-height: 40px;
  transition: all 0.2s ease;
  width: 100%;
  box-sizing: border-box;
}

.customer-tag-btn:hover {
  border-color: var(--primary, #10b981);
  background: var(--surface-hover, rgba(255, 255, 255, 0.07));
}

.customer-tag-btn.selected {
  background: rgba(16, 185, 129, 0.1);
  border-color: rgba(16, 185, 129, 0.3);
  color: var(--primary, #10b981);
}

.cust-icon {
  font-size: 20px;
  color: var(--primary, #10b981);
  flex-shrink: 0;
}

.cust-info {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  flex: 1;
  min-width: 0;
  line-height: 1.3;
}

.cust-label-prefix {
  font-size: 11.5px;
  color: var(--muted, #94a3b8);
}

.cust-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #ffffff);
}

.cust-tier {
  font-size: 11px;
  color: #10b981;
  font-weight: 600;
}

.cust-placeholder {
  color: var(--ink, #ffffff);
  font-size: 13px;
  font-weight: 600;
}

.cust-hint {
  font-size: 11.5px;
  color: var(--muted, #94a3b8);
}

.action-icon {
  font-size: 18px;
  color: var(--muted, #94a3b8);
  flex-shrink: 0;
}

.court-dropdown-wrap {
  width: 100%;
  min-width: 0;
}

.court-dropdown-wrap :deep(.custom-dropdown-trigger) {
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13px;
  min-height: 40px;
  background: var(--surface-hover, rgba(255, 255, 255, 0.04));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  color: var(--ink, #ffffff);
}

.court-dropdown-wrap :deep(.custom-dropdown-trigger:hover) {
  border-color: var(--primary, #10b981);
}

.cart-items-container {
  flex: 1;
  overflow-y: auto;
  padding: 12px 16px;
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.15) transparent;
}

.cart-items-container::-webkit-scrollbar {
  width: 5px;
}

.cart-items-container::-webkit-scrollbar-track {
  background: transparent;
}

.cart-items-container::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.15);
  border-radius: 4px;
}

.cart-items-container::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.3);
}

.empty-cart-view {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 10px;
  text-align: center;
  color: var(--muted, #94a3b8);
}

.empty-cart-icon {
  font-size: 40px;
  margin-bottom: 8px;
  opacity: 0.4;
}

.empty-cart-view h4 {
  margin: 0 0 4px;
  color: var(--ink, #ffffff);
  font-size: 14px;
}

.empty-cart-view p {
  margin: 0;
  font-size: 12px;
}

.cart-items-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cart-item-row {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--surface-hover, rgba(255, 255, 255, 0.02));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.06));
}

.item-primary-info {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}

.item-name-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  min-width: 0;
}

.item-name {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #ffffff);
  line-height: 1.3;
}

.item-vat-badge {
  border: none;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.15s ease;
  line-height: 1.2;
}

.item-vat-badge.vat-10 {
  background: rgba(245, 158, 11, 0.15);
  color: #f59e0b;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.item-vat-badge.vat-8 {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.item-vat-badge.vat-0 {
  background: rgba(255, 255, 255, 0.05);
  color: var(--muted, #94a3b8);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.item-vat-badge:hover {
  filter: brightness(1.2);
}

.item-unit-price {
  font-size: 12px;
  color: var(--muted, #94a3b8);
  white-space: nowrap;
}

.item-controls-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.rental-hours-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.2);
  border-radius: 6px;
  padding: 2px 6px;
  font-size: 11px;
  color: #f59e0b;
}

.item-qty-stepper {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-hover, rgba(255, 255, 255, 0.05));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 8px;
  padding: 2px;
}

.qty-btn {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  background: var(--surface, #1e293b);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  color: var(--ink, #ffffff);
  font-size: 14px;
  font-weight: bold;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.qty-btn:hover {
  background: var(--surface-hover, rgba(255, 255, 255, 0.1));
  border-color: var(--primary, #10b981);
}

.qty-num {
  min-width: 20px;
  text-align: center;
  font-size: 12px;
  font-weight: 700;
  color: var(--ink, #ffffff);
}

.item-line-total {
  font-size: 13px;
  font-weight: 700;
  color: var(--primary, #10b981);
}

.cart-footer-area {
  padding: 12px 14px;
  flex-shrink: 0;
  background: var(--surface, #1e293b);
  border-top: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.calculations-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
}

.calc-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.calc-label {
  color: var(--muted, #94a3b8);
}

.calc-val {
  color: var(--ink, #ffffff);
  font-weight: 600;
}

.discount-vat-controls {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 4px 0;
}

.discount-input-wrap {
  display: flex;
  align-items: center;
  gap: 4px;
}

.ctrl-sublabel {
  font-size: 11px;
  color: var(--muted, #94a3b8);
}

.discount-input {
  width: 50px;
  padding: 4px 6px;
  background: var(--surface-hover, rgba(255, 255, 255, 0.05));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 6px;
  color: var(--ink, #ffffff);
  font-size: 12px;
  font-weight: bold;
  text-align: right;
  outline: none;
}

.unit-pct {
  font-size: 11px;
  color: var(--muted, #94a3b8);
}

.vat-rate-selector {
  display: flex;
  align-items: center;
  gap: 6px;
}

.vat-pill-group {
  display: inline-flex;
  align-items: center;
  background: var(--surface-hover, rgba(255, 255, 255, 0.05));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 8px;
  padding: 2px;
  gap: 2px;
}

.vat-pill-btn {
  border: none;
  background: transparent;
  color: var(--muted, #94a3b8);
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
  line-height: 1.2;
}

.vat-pill-btn:hover {
  color: var(--ink, #ffffff);
}

.vat-pill-btn.active {
  background: var(--primary, #10b981);
  color: #ffffff;
}

.text-green {
  color: #10b981 !important;
}

.grand-total-row {
  margin-top: 6px;
  padding-top: 8px;
  border-top: 1px dashed var(--outline, rgba(255, 255, 255, 0.15));
}

.grand-total-val {
  font-size: 18px;
  color: var(--primary, #10b981);
  font-weight: 800;
}

.payment-methods-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}

.pay-method-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 8px 4px;
  border-radius: 10px;
  background: var(--surface-hover, rgba(255, 255, 255, 0.03));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.08));
  color: var(--muted, #94a3b8);
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.pay-method-btn:hover {
  background: var(--surface-hover, rgba(255, 255, 255, 0.08));
  color: var(--ink, #ffffff);
}

.pay-method-btn.active {
  background: rgba(16, 185, 129, 0.15);
  border-color: var(--primary, #10b981);
  color: var(--primary, #10b981);
}

.method-icon {
  font-size: 20px;
}

.cash-tendered-section {
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: var(--surface-hover, rgba(255, 255, 255, 0.02));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.08));
  border-radius: 10px;
  padding: 10px;
}

.cash-input-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.cash-label {
  font-size: 12px;
  color: var(--muted, #94a3b8);
}

.cash-tendered-input {
  flex: 1;
  padding: 6px 10px;
  background: var(--surface, #1e293b);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 8px;
  color: var(--ink, #ffffff);
  font-size: 13px;
  font-weight: bold;
  text-align: right;
  outline: none;
}

.quick-cash-chips {
  display: flex;
  gap: 5px;
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-width: none;
  -ms-overflow-style: none;
  -webkit-overflow-scrolling: touch;
  scroll-behavior: smooth;
  padding-bottom: 2px;
}

.quick-cash-chips::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.cash-chip {
  padding: 4px 8px;
  border-radius: 6px;
  background: var(--surface, #1e293b);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  color: var(--ink, #ffffff);
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.cash-chip:hover {
  background: rgba(16, 185, 129, 0.1);
  border-color: var(--primary, #10b981);
}

.cash-change-alert {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 10px;
  border-radius: 6px;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.2);
  font-size: 12px;
  color: #10b981;
}

.change-amount {
  font-size: 14px;
  font-weight: 800;
}

.cart-actions-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.draft-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--surface-hover, rgba(255, 255, 255, 0.04));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  color: var(--ink, #ffffff);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.draft-action-btn:hover:not(:disabled) {
  background: var(--surface-active, rgba(255, 255, 255, 0.08));
  border-color: var(--outline-strong, rgba(255, 255, 255, 0.2));
}

.draft-action-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.checkout-submit-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--primary, #10b981) 0%, #059669 100%);
  border: none;
  color: #ffffff;
  font-size: 14px;
  font-weight: 800;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
  transition: all 0.2s ease;
}

.checkout-submit-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

.checkout-submit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

.icon-14 {
  font-size: 14px;
}

.icon-18 {
  font-size: 18px;
}

.icon-20 {
  font-size: 20px;
}
</style>
