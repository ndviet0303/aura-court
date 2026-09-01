<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { CartItem, CustomerProfile, CourtReservation } from '../types/pos'

defineProps<{
  cart: CartItem[]
  selectedCustomer: CustomerProfile | null
  selectedReservationId: number | null
  activeReservations: CourtReservation[]
  includeVat: boolean
  vatRate: number
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
}>()

const emit = defineEmits<{
  (e: 'update:includeVat', val: boolean): void
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
}>()

const { t } = useI18n()

function formatMoney(val: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
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
          <span v-if="selectedCustomer" class="cust-info">
            <strong>{{ selectedCustomer.name }}</strong>
            <span class="cust-tier">({{ selectedCustomer.tier }} - {{ selectedCustomer.discount_rate * 100 }}%)</span>
          </span>
          <span v-else class="cust-placeholder">{{ t('admin.pos.walkInCustomer') }}</span>
        </button>

        <select
          :value="selectedReservationId || ''"
          class="court-tab-select"
          @change="emit('update:selectedReservationId', ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
        >
          <option value="">{{ t('admin.pos.attachToCourt') }}</option>
          <option
            v-for="res in activeReservations"
            :key="res.id"
            :value="res.id"
          >
            {{ res.court_name }} - {{ res.customer_name }}
          </option>
        </select>
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
        <div
          v-for="(item, idx) in cart"
          :key="idx"
          class="cart-item-row"
        >
          <div class="item-primary-info">
            <h5 class="item-name">{{ item.product.name }}</h5>
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
              <button
                type="button"
                class="qty-btn"
                @click="emit('updateRentalHours', idx, 1)"
              >
                +
              </button>
            </div>

            <div class="item-qty-stepper">
              <button
                type="button"
                class="qty-btn"
                @click="emit('updateQuantity', idx, -1)"
              >
                -
              </button>
              <span class="qty-num">{{ item.quantity }}</span>
              <button
                type="button"
                class="qty-btn"
                @click="emit('updateQuantity', idx, 1)"
              >
                +
              </button>
            </div>

            <div class="item-total-col">
              <span class="item-total-price">
                {{ formatMoney(item.product.price * item.quantity * (item.rental_hours || 1)) }}
              </span>
              <button
                type="button"
                class="remove-item-btn"
                title="Remove"
                @click="emit('removeFromCart', idx)"
              >
                <span class="material-symbols-outlined icon-16">delete</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary & Checkout Footer -->
    <div class="cart-footer">
      <!-- Discount & VAT Controls -->
      <div class="cart-modifiers-row">
        <label class="modifier-check">
          <input
            :checked="includeVat"
            type="checkbox"
            @change="emit('update:includeVat', ($event.target as HTMLInputElement).checked)"
          />
          <span>{{ t('admin.pos.vatCheckbox') }}</span>
        </label>

        <div class="discount-input-wrap">
          <span class="disc-label">{{ t('admin.pos.discountLabel') }}</span>
          <input
            :value="customDiscountPercent"
            type="number"
            min="0"
            max="100"
            class="disc-input"
            @input="emit('update:customDiscountPercent', Number(($event.target as HTMLInputElement).value) || 0)"
          />
        </div>
      </div>

      <!-- Financial Calculations -->
      <div class="cart-totals-breakdown">
        <div class="total-row subtotal">
          <span>{{ t('admin.pos.subtotal') }}</span>
          <span>{{ formatMoney(subtotal) }}</span>
        </div>
        <div v-if="discountAmount > 0" class="total-row discount">
          <span>{{ t('admin.pos.discountSummary', { percent: discountPercent }) }}</span>
          <span class="discount-val">-{{ formatMoney(discountAmount) }}</span>
        </div>
        <div v-if="includeVat" class="total-row vat">
          <span>{{ t('admin.pos.vatSummary') }}</span>
          <span>+{{ formatMoney(taxAmount) }}</span>
        </div>
        <div class="total-row grand-total">
          <span class="gt-label">{{ t('admin.pos.totalPayment') }}</span>
          <span class="gt-amount">{{ formatMoney(grandTotal) }}</span>
        </div>
      </div>

      <!-- Payment Methods -->
      <div class="payment-method-selector">
        <button
          type="button"
          class="pay-btn"
          :class="{ active: paymentMethod === 'cash' }"
          @click="emit('update:paymentMethod', 'cash')"
        >
          <span class="material-symbols-outlined icon-16">payments</span>
          <span>{{ t('admin.pos.payCash') }}</span>
        </button>
        <button
          type="button"
          class="pay-btn"
          :class="{ active: paymentMethod === 'qr' }"
          @click="emit('update:paymentMethod', 'qr')"
        >
          <span class="material-symbols-outlined icon-16">qr_code_2</span>
          <span>{{ t('admin.pos.payQr') }}</span>
        </button>
        <button
          type="button"
          class="pay-btn"
          :class="{ active: paymentMethod === 'wallet' }"
          @click="emit('update:paymentMethod', 'wallet')"
        >
          <span class="material-symbols-outlined icon-16">account_balance_wallet</span>
          <span>{{ t('admin.pos.payWallet') }}</span>
        </button>
        <button
          type="button"
          class="pay-btn"
          :class="{ active: paymentMethod === 'court_tab' }"
          @click="emit('update:paymentMethod', 'court_tab')"
        >
          <span class="material-symbols-outlined icon-16">receipt_long</span>
          <span>{{ t('admin.pos.payCourtTab') }}</span>
        </button>
      </div>

      <!-- Cash Tendered Quick Input -->
      <div v-if="paymentMethod === 'cash' && grandTotal > 0" class="cash-tendered-box">
        <div class="cash-input-row">
          <span>{{ t('admin.pos.cashTenderedLabel') }}</span>
          <input
            :value="cashTendered"
            type="number"
            step="1000"
            class="cash-input"
            @input="emit('update:cashTendered', Number(($event.target as HTMLInputElement).value) || 0)"
          />
        </div>

        <div class="quick-cash-tags">
          <button
            v-for="amt in quickCashSuggestions"
            :key="amt"
            type="button"
            class="quick-cash-tag"
            @click="emit('update:cashTendered', amt)"
          >
            {{ formatMoney(amt) }}
          </button>
        </div>

        <div v-if="cashChange > 0" class="cash-change-row">
          <span>{{ t('admin.pos.cashChangeLabel') }}</span>
          <strong class="change-amount">{{ formatMoney(cashChange) }}</strong>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="cart-actions-row">
        <button
          type="button"
          class="draft-btn"
          :title="t('admin.pos.saveDraft')"
          :disabled="cart.length === 0"
          @click="emit('saveDraft')"
        >
          <span class="material-symbols-outlined icon-16">save</span>
          <span>{{ t('admin.pos.saveDraft') }}</span>
        </button>
        <button
          type="button"
          class="draft-btn"
          :title="t('admin.pos.viewDrafts')"
          @click="emit('openDraftsModal')"
        >
          <span class="material-symbols-outlined icon-16">folder_open</span>
          <span>{{ t('admin.pos.viewDrafts') }}</span>
        </button>
        <button
          type="button"
          class="checkout-submit-btn"
          :disabled="cart.length === 0 || processingCheckout"
          @click="emit('checkout')"
        >
          <span v-if="processingCheckout">{{ t('admin.pos.checkoutProcessing') }}</span>
          <span v-else>{{ t('admin.pos.checkoutBtn') }}</span>
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
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.cart-header {
  padding: 16px;
  border-bottom: 1px solid var(--outline);
  background: var(--surface);
}

.cart-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.cart-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}

.cart-count-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  border-radius: 11px;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
}

.clear-cart-link {
  background: transparent;
  border: 0;
  color: var(--danger, #ef4444);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.cart-meta-bar {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.customer-tag-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 13px;
  cursor: pointer;
  text-align: left;
}

.customer-tag-btn.selected {
  border-color: #3b82f6;
  background: rgba(59, 130, 246, 0.06);
}

.cust-icon {
  font-size: 18px;
  color: var(--muted);
}

.cust-tier {
  color: var(--pink-dark, #db2777);
  font-weight: 600;
}

.court-tab-select {
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  font-size: 13px;
  background: var(--surface);
  color: var(--ink);
}

.cart-items-container {
  flex: 1;
  overflow-y: auto;
  padding: 12px 16px;
}

.empty-cart-view {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  text-align: center;
  color: var(--muted);
  padding: 40px 20px;
}

.empty-cart-icon {
  font-size: 40px;
  margin-bottom: 8px;
  color: var(--muted);
}

.cart-items-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cart-item-row {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.item-primary-info {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.item-name {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}

.item-unit-price {
  font-size: 12px;
  color: var(--muted);
  white-space: nowrap;
}

.item-controls-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.rental-hours-pill,
.item-qty-stepper {
  display: flex;
  align-items: center;
  gap: 4px;
  border: 1px solid var(--outline);
  border-radius: 8px;
  padding: 2px 4px;
}

.ctrl-label {
  font-size: 11px;
  color: var(--muted);
  margin-left: 2px;
}

.qty-btn {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 0;
  background: transparent;
  color: var(--ink);
  font-weight: 700;
  cursor: pointer;
  border-radius: 4px;
}

.qty-btn:hover:not(:disabled) {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.08));
}

.qty-num {
  font-size: 12px;
  font-weight: 700;
  min-width: 22px;
  text-align: center;
}

.item-total-col {
  display: flex;
  align-items: center;
  gap: 8px;
}

.item-total-price {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.remove-item-btn {
  border: 0;
  background: transparent;
  cursor: pointer;
  padding: 4px;
  color: var(--muted);
  display: flex;
  align-items: center;
  justify-content: center;
}

.remove-item-btn:hover {
  color: var(--danger, #ef4444);
}

.cart-footer {
  padding: 16px;
  border-top: 1px solid var(--outline);
  background: var(--surface);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cart-modifiers-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
}

.modifier-check {
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
}

.discount-input-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
}

.disc-input {
  width: 50px;
  padding: 4px 6px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  text-align: center;
  font-size: 12px;
}

.cart-totals-breakdown {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 13px;
}

.total-row {
  display: flex;
  justify-content: space-between;
  color: var(--muted);
}

.discount-val {
  color: var(--danger, #ef4444);
  font-weight: 600;
}

.grand-total {
  margin-top: 4px;
  padding-top: 6px;
  border-top: 1px dashed var(--outline);
  font-weight: 700;
  color: var(--ink);
}

.gt-amount {
  font-size: 16px;
  color: var(--pink-dark, #db2777);
}

.payment-method-selector {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 6px;
}

.pay-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 6px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.pay-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.cash-tendered-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 8px 10px;
  border-radius: 8px;
  background: rgba(236, 72, 153, 0.04);
  border: 1px solid var(--outline);
}

.cash-input-row,
.cash-change-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
}

.cash-input {
  width: 110px;
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  text-align: right;
  font-weight: 700;
  font-size: 13px;
}

.quick-cash-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.quick-cash-tag {
  padding: 3px 6px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 10px;
  font-weight: 600;
  cursor: pointer;
}

.quick-cash-tag:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.change-amount {
  color: #10b981;
  font-size: 14px;
}

.cart-actions-row {
  display: flex;
  gap: 8px;
}

.draft-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.checkout-submit-btn {
  flex: 1;
  padding: 12px 16px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.1s;
}

.checkout-submit-btn:hover:not(:disabled) {
  opacity: 0.95;
  transform: translateY(-1px);
}

.checkout-submit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.icon-16 {
  font-size: 16px;
}

.icon-20 {
  font-size: 20px;
}
</style>
